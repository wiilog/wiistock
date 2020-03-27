<?php

namespace App\Service;


use App\Entity\Translation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
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

    public function __construct(KernelInterface $kernel,
                                EntityManagerInterface $entityManager) {
        $this->kernel = $kernel;
        $this->entityManager = $entityManager;
    }

    /**
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function generateTranslationsFile() {
        $projectDir = $this->kernel->getProjectDir();
        $translationFile = $projectDir . '/translations/messages.' . $_SERVER['APP_LOCALE'] . '.yaml';

        $translationRepository = $this->entityManager->getRepository(Translation::class);

        if ($translationRepository->countUpdatedRows() > 0 ||
            !file_exists($translationFile)) {
            $translations = $translationRepository->findAll();

            $menus = [];
            foreach ($translations as $translation) {
                $menus[$translation->getMenu()][$translation->getLabel()] = $translation->getTranslation();
            }

            $yaml = Yaml::dump($menus);

            file_put_contents($translationFile, $yaml);

            $translationRepository->clearUpdate();

            $this->cacheClearWarmUp();
            $this->chmod($translationFile, 'w');
        }
    }

    /**
     * @throws Exception
     */
    public function cacheClearWarmUp() {
        $env = $this->kernel->getEnvironment();
		$command = $env == 'dev' ? 'warmup' : 'clear';

        $application = new Application($this->kernel);
        $application->setAutoExit(false);

		$input = new ArrayInput(array(
			'command' => 'cache:' . $command,
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
		$process = Process::fromShellCommandline('chmod a+' . $right . ' ' . $file);
		$process->run();
	}

    /**
     * @param $menu
     * @param $label
     * @return mixed
     * @throws NonUniqueResultException
     */
	public function getTranslation($menu, $label)
	{
        $translationRepository = $this->entityManager->getRepository(Translation::class);
		$translation = $translationRepository->getTranslationByMenuAndLabel($menu, $label);
		return !empty($translation) ? $translation : $label;
	}
}
