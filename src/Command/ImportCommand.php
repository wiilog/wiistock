<?php


namespace App\Command;

use App\Entity\CategorieStatut;
use App\Entity\Import;
use App\Entity\Statut;
use App\Service\ImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends Command
{
    protected static $defaultName = 'app:launch:imports';

    private $em;
    private $importService;

    public function __construct(string $name = null,
                                EntityManagerInterface $entityManager,
                                ImportService $importService)
    {
        parent::__construct($name);

        $this->em = $entityManager;
        $this->importService = $importService;
    }

    protected function configure()
    {
		$this->setDescription('This command executes planified imports.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $importRepository = $this->em->getRepository(Import::class);
        $statutRepository = $this->em->getRepository(Statut::class);

        $importsToExecute = $importRepository->findByStatusLabel(Import::STATUS_PLANNED);

        foreach ($importsToExecute as $import) {
            $this->importService->loadData($import, true);
            $import
                ->setStatus($statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_FINISHED))
                ->setEndDate(new DateTime('now'));
            $this->em->flush();
        }

        // nettoyage des éventuels imports en brouillon
        $drafts = $importRepository->findByStatusLabel(Import::STATUS_DRAFT);
        foreach ($drafts as $draft) {
            $this->em->remove($draft);
            $this->em->flush();
        }
    }
}
