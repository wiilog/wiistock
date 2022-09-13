<?php

namespace App\Service;

use App\Entity\Language;
use App\Entity\Translation;
use App\Entity\TranslationSource;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\Deprecated;
use RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Service\Attribute\Required;

class TranslationService {

    #[Required]
    public KernelInterface $kernel;

    #[Required]
    public CacheService $cacheService;

    #[Required]
    public EntityManagerInterface $manager;

    #[Required]
    public TokenStorageInterface $tokenStorage;

    #[Required]
    public LanguageService $languageService;

    private array $translations = [];

    #[Deprecated]
    public function trans(string $in): string {
        return "BUG TICKET: $in";
    }

    /**
     * Translates the given input
     * The function expects from 1 to 4 strings, then an array of
     * strings to replace or a user or both in any order.
     *
     * Example usage with more or less string inputs and with and without custom user and params array :
     * translate("Traçabilité", "Unités logistiques", "Onglet \"Groupes\"", "Groupes")
     * translate("Traçabilité", "Unités logistiques", "Onglet \"Groupes\"", "Groupes", $customUser)
     *
     * translate("Traçabilité", "Unités logistiques", "Onglet \"Groupes\"", "Mouvementé la dernière fois le {1}", [1 => "DATE"])
     * translate("Traçabilité", "Unités logistiques", "Onglet \"Groupes\"", "Mouvementé la dernière fois le {1}", [1 => "DATE"], $customUser)
     *
     * translate("Référentiel", "Natures", "Sélectionner une nature")
     * translate("Référentiel", "Natures", "Sélectionner une nature", $customUser)
     *
     * translate("Référentiel", "Natures", "La nature {1} a bien été créée", [1 => "NOMNATURE"])
     * translate("Référentiel", "Natures", "La nature {1} a bien été créée", [1 => "NOMNATURE"], $customUser)
     *
     * @param mixed ...$args Arguments
     * @return string Translated input
     */
    public function translate(mixed ...$args): string {
        $user = null;
        foreach($args as $arg) {
            if ($arg instanceof Utilisateur) {
                $user = $arg;
            }
        }

        if(!isset($user)) {
            $user = $this->tokenStorage->getToken()->getUser();
        }

        $slug = $user?->getLanguage()?->getSlug();

        $defaultSlug = Language::DEFAULT_LANGUAGE_TRANSLATIONS[$slug] ?? $this->languageService->getDefaultSlug();

        return (
            $this->translateIn($slug, $defaultSlug, false, ...$args)
            ?? $this->translateIn($defaultSlug, $defaultSlug, true, ...$args)
        );
    }

    public function translateIn(string $slug, string $defaultSlug, bool $lastResort, mixed ...$args): ?string {
        $variables = ["category", "menu", "submenu", "input"];
        foreach($variables as $variable) {
            $$variable = null;
        }

        $enableTooltip = true;
        foreach($args as $arg) {
            if(is_array($arg)) {
                $params = $arg;
            }if(is_bool($arg)) {
                $enableTooltip = $arg;
            } else if(!($arg instanceof Utilisateur)) {
                if(empty($variables)) {
                    throw new RuntimeException("Too many arguments, expected at most 4 strings, 1 array, 1 boolean and 1 user");
                }

                ${array_shift($variables)} = $arg;
            }
        }

        if(!isset($this->translations[$slug])) {
            $this->generateCache($slug);
            $this->generateJavascripts();
        }

        $transCategory = $this->translations[$slug][$category ?: null] ?? null;
        if(!is_array($transCategory)) {
            $output = $transCategory ?? ($lastResort ? ($input ?? $submenu ?? $menu ?? $category ): null);
        }

        if(!isset($output)) {
            $transMenu = $transCategory[$menu ?: null] ?? null;
            if (!is_array($transMenu)) {
                $output = $transMenu ?? ($lastResort ? ($input ?? $submenu ?? $menu) : null);
            }
        }

        if(!isset($output)) {
            $transSubmenu = $transMenu[$submenu ?: null] ?? null;
            if (!is_array($transSubmenu)) {
                $output = $transSubmenu ?? ($lastResort ? ($input ?? $submenu) : null);
            }
        }

        if(!isset($output)) {
            $output = $transSubmenu[$input] ?? ($lastResort ? $input : null);
        }

        if($output === null) {
            return null;
        }

        if(isset($params)) {
            foreach($params as $key => $value) {
                $output = str_replace( '{' . $key . '}', $value, $output);
            }
        }

        if($slug === $defaultSlug) {
            $tooltip = htmlspecialchars($output);
        } else {
            $tooltip = htmlspecialchars($this->translateIn($defaultSlug, $defaultSlug, true, false, ...$args));
        }

        return $enableTooltip ? "<span title='$tooltip'>$output</span>" : $output;
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
        $translationSourceRepository = $this->manager->getRepository(TranslationSource::class);

        $languages = $slug ? $languageRepository->findBy(["slug" => $slug]) : $languageRepository->findAll();
        $sources = $translationSourceRepository->findAll();

        /** @var Language $language */
        foreach($languages as $language) {
            $slug = $language->getSlug();
            $this->translations[$slug] = [];

            /** @var Translation $translation */
            foreach($sources as $source) {
                //no category means it's a translation for natures, types, etc
                if($source->getCategory() === null) {
                    continue;
                }

                $original = $source->getTranslationIn(Language::FRENCH_DEFAULT_SLUG);
                $translation = $source->getTranslationIn($slug) ?? null;

                $zoomedTranslations = &$this->translations[$slug];
                $stack = $translation ? $this->createCategoryStack($translation) : [];
                foreach($stack as $category) {
                    if(!isset($zoomedTranslations[$category->getLabel()])) {
                        $zoomedTranslations[$category->getLabel()] = [];
                    }

                    $zoomedTranslations = &$zoomedTranslations[$category->getLabel()];
                }

                if($translation) {
                    $zoomedTranslations[$original->getTranslation()] = $translation?->getTranslation();
                }
            }

            $this->cacheService->set(CacheService::TRANSLATIONS, $slug, $this->translations[$slug]);
            if ($language->getSelected()) {
                $this->cacheService->set(CacheService::TRANSLATIONS, "default", $this->translations[$slug]);
            }
        }
    }

    public function generateJavascripts() {
        $languageRepository = $this->manager->getRepository(Language::class);
        $outputDirectory = "{$this->kernel->getProjectDir()}/public/generated";

        $languages = $languageRepository->findAll();

        /** @var Language $language */
        foreach($languages as $language) {
            $slug = $language->getSlug();
            if(!isset($this->translations[$slug])) {
                $this->generateCache($slug);
            }
        }

        $content = "//generated file for translations\n";
        $content .= "const DEFAULT_SLUG = `{$this->languageService->getDefaultSlug()}`;\n";
        $content .= "const TRANSLATIONS = " . json_encode($this->translations) . ";\n";

        file_put_contents("$outputDirectory/translations.js", $content);
    }

    public function editEntityTranslations(EntityManagerInterface $entityManager,
                                           array $labels,
                                           TranslationSource $labelTranslationSource) {
        foreach ($labels as $label) {
            $labelLanguage = $entityManager->find(Language::class, $label["language-id"]);
            $currentTranslation = $labelTranslationSource->getTranslationIn($labelLanguage);

            if (!$currentTranslation) {
                $newTranslation = new Translation();
                $newTranslation
                    ->setTranslation($label['label'] ?? '')
                    ->setSource($labelTranslationSource)
                    ->setLanguage($labelLanguage);

                $labelTranslationSource->addTranslation($newTranslation);
                $entityManager->persist($newTranslation);
            } else {
                $currentTranslation->setTranslation($label['label']);
            }
        }
    }

    public function setFirstTranslation(EntityManagerInterface $entityManager,
                                        int $entityId,
                                        string $classe,
                                        string $firstLabel) {
        $entityRepository = $entityManager->getRepository($classe);
        $entity = $entityRepository->find($entityId);

        $labelTranslation = new TranslationSource();
        $entityManager->persist($labelTranslation);
        $frenchTranslation = new Translation();
        $entityManager->persist($frenchTranslation);

        $frenchTranslation
            ->setLanguage($entityManager->getRepository(Language::class)->find(1))
            ->setSource($labelTranslation)
            ->setTranslation($firstLabel);
        $labelTranslation->addTranslation($frenchTranslation);
        $entity->setLabelTranslation($labelTranslation);
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
