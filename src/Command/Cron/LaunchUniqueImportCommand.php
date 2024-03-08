<?php
// At every 30th minute
// */30 * * * *

namespace App\Command\Cron;

use App\Entity\CategorieStatut;
use App\Entity\ScheduledTask\Import;
use App\Entity\Statut;
use App\Service\ImportService;
use DateTime;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: LaunchUniqueImportCommand::COMMAND_NAME,
    description: "This command executes planified in next 30 minutes imports.",
)]
class LaunchUniqueImportCommand extends Command
{
    public const COMMAND_NAME = 'app:launch:unique-imports';

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public ImportService $importService;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $importRepository = $this->getEntityManager()->getRepository(Import::class);
        $statutRepository = $this->getEntityManager()->getRepository(Statut::class);

        $upcomingImports = $importRepository->findByStatusLabel(Import::STATUS_UPCOMING);
        $statusInProgress = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_IN_PROGRESS);

        $now = new DateTime('now');

        $nowHours = (int) $now->format('G'); // 0-23
        $nowMinutes = (int) $now->format('i'); // 0-59

        $importsToLaunch = [];
        // si on est au alentours de minuit => on commence tous les imports sinon uniquement ceux qui sont forcés
        $runOnlyForced = ($nowHours !== 0 || $nowMinutes >= 30);
        foreach ($upcomingImports as $import) {
            if (!$runOnlyForced
                || $import->isForced()) {
                $import->setStatus($statusInProgress);
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

        return 0;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager->isOpen()
            ? $this->entityManager
            : new EntityManager($this->entityManager->getConnection(), $this->entityManager->getConfiguration());
    }
}
