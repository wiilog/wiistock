<?php

namespace App\Service;


use App\Repository\TranslationRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;


class TranslationService {

    private $kernel;
    private $translationRepository;

    public function __construct(KernelInterface $kernel,
                                TranslationRepository $translationRepository) {
        $this->kernel = $kernel;
        $this->translationRepository = $translationRepository;
    }

    /**
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function generateTranslationsFile() {
        $projectDir = $this->kernel->getProjectDir();
        $translationFile = $projectDir . '/translations/messages.' . $_SERVER['APP_LOCALE'] . '.yaml';
        if ($this->translationRepository->countUpdatedRows() > 0 ||
            !file_exists($translationFile)) {
            $translations = $this->translationRepository->findAll();

            $menus = [];
            foreach ($translations as $translation) {
                $menus[$translation->getMenu()][$translation->getLabel()] = $translation->getTranslation();
            }

            $yaml = Yaml::dump($menus);

            file_put_contents($translationFile, $yaml);

            $this->translationRepository->clearUpdate();

            $this->cacheClearWarmUp();
        }
    }

    /**
     * @throws Exception
     */
    private function cacheClearWarmUp() {
        $env = $this->kernel->getEnvironment();

        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $command = isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] == 'dev' ? 'warmup' : 'clear';

		$input = new ArrayInput(array(
			'command' => 'cache:' . $command,
			'--env' => $env
        ));

        $output = new BufferedOutput();
        $application->run($input, $output);
    }
}
