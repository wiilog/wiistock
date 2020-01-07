<?php


namespace App\Command;


use App\Repository\TranslationRepository;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class UpdateTranslationsCommand extends Command
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
	/**
	 * @var TranslationRepository
	 */
    private $translationRepository;

    public function __construct(
    	EntityManagerInterface $entityManager,
		TranslationRepository $translationRepository
	)
	{
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->translationRepository = $translationRepository;
    }

    protected function configure()
    {
		$this->setName('app:update:translations');
		$this->setDescription('This commands generate the yaml translations.');
        $this->setHelp('This command is supposed to be executed every night.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
    	if ($this->translationRepository->countUpdatedRows() > 0) {
    		$translations = $this->translationRepository->findAll();

    		$menus = [];
    		foreach ($translations as $translation) {
    			$menus[$translation->getMenu()][$translation->getLabel()] = $translation->getTranslation();
			}

    		$yaml = Yaml::dump($menus);

    		file_put_contents('translations/messages.' . $_SERVER['APP_LOCALE'] . '.yaml', $yaml);
		}
    }
}