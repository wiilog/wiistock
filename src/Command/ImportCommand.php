<?php
/**
 * Commande Cron exécutée toute les 30 min
 */

namespace App\Command;

use App\Entity\CategorieStatut;
use App\Entity\Import;
use App\Entity\Statut;
use App\Service\ImportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\ORMException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends Command
{
    protected static $defaultName = 'app:launch:imports';

    private $em;
    private $importService;

    public function __construct(EntityManagerInterface $entityManager,
                                ImportService $importService)
    {
        parent::__construct(self::$defaultName);

        $this->em = $entityManager;
        $this->importService = $importService;
    }

    protected function configure()
    {
		$this->setDescription('This command executes planified imports.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws ORMException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $importRepository = $this->em->getRepository(Import::class);
        $statutRepository = $this->em->getRepository(Statut::class);

        $importsToExecute = $importRepository->findByStatusLabel(Import::STATUS_PLANNED);

        $now = new DateTime('now');

        $nowHours = (int) $now->format('G'); // 0-23
        $nowMinutes = (int) $now->format('i'); // 0-59

        // si on est au alentours de minuit => on commence tous les imports sinon uniquement ceux qui sont forcés
        $runOnlyForced = ($nowHours !== 0 || $nowMinutes >= 30);

        foreach ($importsToExecute as $import) {
            if (!$runOnlyForced
                || $import->isForced()) {
                $this->importService->treatImport($import, ImportService::IMPORT_MODE_RUN);
                $import
                    ->setStatus($statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_FINISHED))
                    ->setEndDate(new DateTime('now'));
            }
        }

        // nettoyage des éventuels imports en brouillon
        $drafts = $importRepository->findByStatusLabel(Import::STATUS_DRAFT);
        foreach ($drafts as $draft) {
            $this->em->remove($draft);
        }

        $this->em->flush();
    }
}
