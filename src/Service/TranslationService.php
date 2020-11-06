<?php

namespace App\Service;

use App\Entity\Translation;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class TranslationService {

    private $kernel;
    private $entityManager;
    private $appLocale;

    public function __construct(KernelInterface $kernel,
                                EntityManagerInterface $entityManager,
                                string $appLocale) {
        $this->kernel = $kernel;
        $this->entityManager = $entityManager;
        $this->appLocale = $appLocale;
    }

    /**
     * @throws Exception
     */
    public function generateTranslationsFile() {
        $projectDir = $this->kernel->getProjectDir();
        $translationYAML = $projectDir . '/translations/messages.' . $this->appLocale . '.yaml';
        $translationJS = $projectDir . '/public/generated/translations.js';

        $translationRepository = $this->entityManager->getRepository(Translation::class);

        if($translationRepository->countUpdatedRows() > 0
            || !file_exists($translationYAML)
            || !file_exists($translationJS)) {
            $translations = $translationRepository->findAll();

            $this->generateYamlTranslations($translationYAML, $translations);
            $this->generateJavascriptTranslations($translationJS, $translations);

            $translationRepository->clearUpdate();

            $this->chmod($translationYAML, 'w');
            $this->chmod($translationJS, 'w');
        }
    }

    private function generateYamlTranslations(string $directory, array $translations) {
        $menus = [];
        foreach($translations as $translation) {
            $menus[$translation->getMenu()][$translation->getLabel()] = $translation->getTranslation() ?: $translation->getLabel();
        }

        file_put_contents($directory, Yaml::dump($menus));
    }

    private function generateJavascriptTranslations(string $directory, array $translations) {
        $translationsArray = array_reduce(
            $translations,
            function(array $carry, Translation $translation) {
                $key = $translation->getMenu() . "." . $translation->getLabel();
                $carry[$key] = [
                    'original' => $translation->getLabel(),
                    'translated' => $translation->getTranslation() ?: $translation->getLabel()
                ];
                return $carry;
            },
            []
        );

        file_put_contents($directory, "const TRANSLATIONS = " . json_encode($translationsArray) . ";");
    }

    /**
     * @throws Exception
     */
    public function cacheClearWarmUp() {
        $env = $this->kernel->getEnvironment();
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $projectDir = $this->kernel->getProjectDir();

        // Delete the translations folder
        $this->rrmdir($projectDir . "/var/cache/$env/translations");

        $input = new ArrayInput(array(
            'command' => 'cache:warmup',
            '--env' => $env
        ));

        $output = new BufferedOutput();
        $application->run($input, $output);
    }

    /**
     * @param string $file
     * @param string $right
     */
    public function chmod($file, $right) {
        if(PHP_OS_FAMILY != "Windows") {
            $process = Process::fromShellCommandline('chmod a+' . $right . ' ' . $file);
            $process->run();
        }
    }

    /**
     * Recursively delete all sub-folders and files from a folder passed as parameter.
     * @param $dir
     */
    private function rrmdir(string $dir) {
        if(is_dir($dir)) {
            $objects = scandir($dir);
            foreach($objects as $object) {
                if($object != "." && $object != "..") {
                    if(is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object)) {
                        $this->rrmdir($dir . DIRECTORY_SEPARATOR . $object);
                    } else {
                        unlink($dir . DIRECTORY_SEPARATOR . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }

}
