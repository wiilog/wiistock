<?php
// At every 5th minute
// */5 * * * *

namespace App\Command;

use App\Entity\CategorieStatut;
use App\Entity\Import;
use App\Entity\Statut;
use App\Exceptions\ImportException;
use App\Service\ImportService;
use DateTime;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\TransactionRequiredException;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class LaunchUniqueImportCommand extends Command
{
    protected static $defaultName = 'app:launch:unique-imports';

    private $entityManager;
    private $importService;

    public function __construct(EntityManagerInterface $entityManager,
                                ImportService $importService)
    {
        parent::__construct(self::$defaultName);
        $this->entityManager = $entityManager;
        $this->importService = $importService;
    }

    protected function configure()
    {
        $this->setDescription('This command executes planified in next 30 minutes imports.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $importRepository = $this->getEntityManager()->getRepository(Import::class);
        $statutRepository = $this->getEntityManager()->getRepository(Statut::class);

        $upcomingImports = $importRepository->findByStatusLabel(Import::STATUS_UPCOMING);

        $now = new DateTime('now');

        $nowHours = (int)$now->format('G'); // 0-23
        $nowMinutes = (int)$now->format('i'); // 0-59

        $statusEnCours = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_IN_PROGRESS);

        $importsToLaunch = [];
        // si on est au alentours de minuit => on commence tous les imports sinon uniquement ceux qui sont forcés
        $runOnlyForced = ($nowHours !== 0 || $nowMinutes >= 30);
        foreach ($upcomingImports as $import) {
            if (!$runOnlyForced
                || $import->isForced()) {
                $import->setStatus($statusEnCours);
                $importsToLaunch[] = $import;
            }
        }
        $this->getEntityManager()->flush();

        foreach ($importsToLaunch as $import) {
            $this->importService->treatImport($this->getEntityManager(), $import, ImportService::IMPORT_MODE_RUN);
        }
        $this->getEntityManager()->getConnection()->getConfiguration()->setMiddlewares([new Middleware(new NullLogger())]);
        $this->getEntityManager()->flush();

        // nettoyage des éventuels imports en brouillon
        $drafts = $importRepository->findByStatusLabel(Import::STATUS_DRAFT);
        foreach ($drafts as $draft) {
            $this->getEntityManager()->remove($draft);
        }

        $this->getEntityManager()->flush();

        // 0 si tout s'est bien passé
        return 0;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager->isOpen()
            ? $this->entityManager
            : new EntityManager($this->entityManager->getConnection(), $this->entityManager->getConfiguration());
    }
}
