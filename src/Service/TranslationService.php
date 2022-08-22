<?php

namespace App\Service;

use App\Entity\Language;
use App\Entity\Translation;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class TranslationService {

    #[Required]
    public KernelInterface $kernel;

    #[Required]
    public CacheService $cacheService;

    #[Required]
    public EntityManagerInterface $manager;

    #[Required]
    public TokenStorageInterface $tokenStorage;

    private array $translations = [];

    public function trans(string $in): string {
            return "BUG TICKET: $in";
    }

    public function translate(?string $category, ?string $menu, ?string $submenu, ?string $input, ?Utilisateur $user = null): string {
        if(!$user) {
            $user = $this->tokenStorage->getToken()->getUser();
            $slug = $user?->getLanguage()?->getSlug();
        }

        $slug = $slug ?? "default";
        if(!isset($translations[$slug])) {
            $this->translations[$slug] = $this->cacheService->get(CacheService::TRANSLATIONS, $slug, function() {
                $this->generateCache();
                $this->generateJavascripts();
            }) ?? [];
        }

        $transCategory = $this->translations[$slug][$category ?: null] ?? null;
        if(!is_array($transCategory)) {
            return $transCategory ?? $input ?? $submenu ?? $menu ?? $category;
        }

        $transMenu = $transCategory[$menu ?: null] ?? null;
        if(!is_array($transMenu)) {
            return $transMenu ?? $input ?? $submenu ?? $menu;
        }

        $transSubmenu = $transMenu[$submenu ?: null] ?? null;
        if(!is_array($transSubmenu)) {
            return $transSubmenu ?? $input ?? $submenu;
        }

        return $transSubmenu[$input ?: null] ?? $input;
    }

    private function createCategoryStack(Translation $translation): array {
        $stack = [];

        $category = $translation->getSource()->getCategory();
        while($category) {
            $stack[] = $category;
            $category = $category->getParent();
        }

        return array_reverse($stack);
    }

    public function generateCache(?string $slug = null) {
        $languageRepository = $this->manager->getRepository(Language::class);
        $translationRepository = $this->manager->getRepository(Translation::class);

        $languages = $slug ? $languageRepository->findBy(["slug" => $slug]) : $languageRepository->findAll();

        /** @var Language $language */
        foreach($languages as $language) {
            $slug = $language->getSlug();
            $this->translations[$slug] = [];

            /** @var Translation $translation */
            foreach($translationRepository->findBy(["language" => $language]) as $translation) {
                $original = $translation->getSource()->getTranslationIn("french")->getTranslation();

                $zoomedTranslations = &$this->translations[$slug];
                $stack = $this->createCategoryStack($translation);
                foreach($stack as $category) {
                    if(!isset($zoomedTranslations[$category->getLabel()])) {
                        $zoomedTranslations[$category->getLabel()] = [];
                    }

                    $zoomedTranslations = &$zoomedTranslations[$category->getLabel()];
                }

                $zoomedTranslations[$original] = $translation->getTranslation();
            }

            $this->cacheService->set(CacheService::TRANSLATIONS, $slug, $this->translations[$slug]);
            if($language->getSelected()) {
                $this->cacheService->set(CacheService::TRANSLATIONS, "default", $this->translations[$slug]);
            }
        }
    }

    public function generateJavascripts(?string $slug = null) {
        $languageRepository = $this->manager->getRepository(Language::class);
        $outputDirectory = "{$this->kernel->getProjectDir()}/public/generated";

        $languages = $slug ? $languageRepository->findBy(["slug" => $slug]) : $languageRepository->findAll();

        /** @var Language $language */
        foreach($languages as $language) {
            $slug = $language->getSlug();
            $translations = $this->cacheService->get(CacheService::TRANSLATIONS, $slug) ?? [];
            $content = "const TRANSLATIONS = " . json_encode($translations) . ";";

            file_put_contents("$outputDirectory/translations.$slug.js", $content);
            if($language->getSelected()) {
                file_put_contents(
                    "$outputDirectory/translations.default.js",
                    "const DEFAULT_TRANSLATIONS = " . json_encode($translations) . ";"
                );
            }
        }
    }

//    private $kernel;
//    private $entityManager;
//    private $appLocale;
//
//    public function __construct(KernelInterface $kernel,
//                                EntityManagerInterface $entityManager,
//                                string $appLocale) {
//        $this->kernel = $kernel;
//        $this->entityManager = $entityManager;
//        $this->appLocale = $appLocale;
//    }
//
//    /**
//     * @throws Exception
//     */
//    public function generateTranslationsFile() {
//        $projectDir = $this->kernel->getProjectDir();
//        $translationYAML = $projectDir . '/translations/messages.' . $this->appLocale . '.yaml';
//        $translationJS = $projectDir . '/public/generated/translations.js';
//
//        $translationRepository = $this->entityManager->getRepository(Translation::class);
//
//        if($translationRepository->countUpdatedRows() > 0
//            || !file_exists($translationYAML)
//            || !file_exists($translationJS)) {
//            $translations = $translationRepository->findAll();
//
//            $this->generateYamlTranslations($translationYAML, $translations);
//            $this->generateJavascriptTranslations($translationJS, $translations);
//
//            $translationRepository->clearUpdate();
//
//            $this->chmod($translationYAML, 'w');
//            $this->chmod($translationJS, 'w');
//        }
//    }
//
//    private function generateYamlTranslations(string $directory, array $translations) {
//        $menus = [];
//        foreach($translations as $translation) {
//            $menus[$translation->getMenu()][$translation->getLabel()] = $translation->getTranslation() ?: $translation->getLabel();
//        }
//
//        file_put_contents($directory, Yaml::dump($menus));
//    }
//
//    private function generateJavascriptTranslations(string $directory, array $translations) {
//        $translationsArray = array_reduce(
//            $translations,
//            function(array $carry, Translation $translation) {
//                $key = $translation->getMenu() . "." . $translation->getLabel();
//                $carry[$key] = [
//                    'original' => $translation->getLabel(),
//                    'translated' => $translation->getTranslation() ?: $translation->getLabel()
//                ];
//                return $carry;
//            },
//            []
//        );
//
//        file_put_contents($directory, "const TRANSLATIONS = " . json_encode($translationsArray) . ";");
//    }
//
//    /**
//     * @throws Exception
//     */
//    public function cacheClearWarmUp() {
//        $env = $this->kernel->getEnvironment();
//        $application = new Application($this->kernel);
//        $application->setAutoExit(false);
//        $projectDir = $this->kernel->getProjectDir();
//
//        // Delete the translations folder
//        $this->rrmdir($projectDir . "/var/cache/$env/translations");
//
//        $input = new ArrayInput(array(
//            'command' => 'cache:warmup',
//            '--env' => $env
//        ));
//
//        $output = new BufferedOutput();
//        $application->run($input, $output);
//    }
//
//    /**
//     * @param string $file
//     * @param string $right
//     */
//    public function chmod($file, $right) {
//        if(PHP_OS_FAMILY != "Windows") {
//            $process = Process::fromShellCommandline('chmod a+' . $right . ' ' . $file);
//            $process->run();
//        }
//    }
//
//    /**
//     * Recursively delete all sub-folders and files from a folder passed as parameter.
//     * @param $dir
//     */
//    private function rrmdir(string $dir) {
//        if(is_dir($dir)) {
//            $objects = scandir($dir);
//            foreach($objects as $object) {
//                if($object != "." && $object != "..") {
//                    if(is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object)) {
//                        $this->rrmdir($dir . DIRECTORY_SEPARATOR . $object);
//                    } else {
//                        unlink($dir . DIRECTORY_SEPARATOR . $object);
//                    }
//                }
//            }
//            rmdir($dir);
//        }
//    }

}
