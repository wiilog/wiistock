<?php


namespace App\Command;


use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateTranslationsCommand extends Command
{
    private $entityManager;
    private $translationService;

    public function __construct(EntityManagerInterface $entityManager,
                                TranslationService $translationService) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->translationService = $translationService;
    }

    protected function configure()
    {
		$this->setName('app:update:translations');
		$this->setDescription('This commands generate the yaml translations.');
        $this->setHelp('This command is supposed to be executed every night.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->translationService->generateTranslationsFile();
    }
}
