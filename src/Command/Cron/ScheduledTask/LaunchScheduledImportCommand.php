<?php
// At each minute
// * * * * *

namespace App\Command\Cron\ScheduledTask;

use App\Entity\CategorieStatut;
use App\Entity\ScheduledTask\Import;
use App\Entity\Statut;
use App\Service\Cache\CacheService;
use App\Service\FTPService;
use App\Service\ImportService;
use App\Service\ScheduledTaskService;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: LaunchScheduledImportCommand::COMMAND_NAME,
    description: "This command executes scheduled imports.",
)]
class LaunchScheduledImportCommand extends Command {
    public const COMMAND_NAME = 'app:launch:scheduled-imports';

    #[Required]
    public EntityManagerInterface $em;

    #[Required]
    public ImportService $importService;

    #[Required]
    public ScheduledTaskService $scheduledTaskService;

    #[Required]
    public FTPService $ftpService;

    #[Required]
    public CacheService $cacheService;

    protected function execute(InputInterface $input, OutputInterface $output): int {
        return $this->scheduledTaskService->launchScheduledTasks(
            $this->getEntityManager(),
            Import::class,
            function (Import $import, DateTime $taskExecution) use ($output) {
                $this->import($output, $import, $taskExecution);
            }
        );
    }

    public function import(OutputInterface $output,
                           Import          $import,
                           DateTime        $taskExecution): void {
        $entityManager = $this->getEntityManager();
        $statusRepository = $entityManager->getRepository(Statut::class);
        $importRepository = $entityManager->getRepository(Import::class);

        $inProgressImport = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_IN_PROGRESS);
        $import = $importRepository->find($import->getId());
        $start = new DateTime();

        $clones = $this->expandScheduledImport($import);

        if (empty($clones)) {
            $import->setLastErrorMessage("Aucun fichier source n'a été trouvé lors de l'exécution de l'import");
            $entityManager->flush();
        }
        else {
            $import
                ->setForced(false)
                ->setLastErrorMessage(null);

            $entityManager->flush();

            foreach ($clones as $clone) {
                $entityManager->persist($clone);
                $clone
                    ->setStatus($inProgressImport)
                    ->setStartDate($start);
                $output->writeln("Starting import {$clone->getId()} at {$start->format('d/m/Y H:i:s')}");
            }

            $entityManager->flush();

            foreach ($clones as $clone) {
                $this->importService->treatImport($entityManager, $clone, ImportService::IMPORT_MODE_RUN);

                $clone = $this->importService->getImport();
                $endDate = $clone->getEndDate();
                $endDateStr = $endDate ? $endDate->format('d/m/Y H:i:s') : '';
                $output->writeln("Finished import {$import->getId()} at $endDateStr");
            }

            $import->setLastRun($taskExecution);
            $entityManager->flush();
        }
    }

    private function expandScheduledImport(Import $import): array {
        $start = new DateTime();

        $rule = $import->getScheduleRule();
        $clones = [];

        if ($import->getFTPConfig()) {
            $filePathMask = $import->getFilePath();
            $files = $this->ftpService->glob($import->getFTPConfig(), $filePathMask);
        }
        else {
            /** @var string[] $files */
            $files = glob($import->getFilePath()) ?: [];
            if (empty($files)) {
                $filePath = $import->getFilePath();
                if (file_exists($filePath)) {
                    $files[] = $filePath;
                }
            }
        }

        foreach ($files as $file) {
            $clones[] = (new Import())
                ->setType($import->getType())
                ->setFTPConfig($import->getFTPConfig())
                ->setLabel($import->getLabel() . " - " . $start->format("d/m/Y H:i"))
                ->setColumnToField($import->getColumnToField())
                ->setCsvFile(null)
                ->setEntity($import->getEntity())
                ->setUser($import->getUser())
                ->setFilePath($file)
                ->setScheduleRule($rule->clone());
        }

        return $clones;
    }

    private function getEntityManager(): EntityManagerInterface {
        return $this->em->isOpen()
            ? $this->em
            : new EntityManager($this->em->getConnection(), $this->em->getConfiguration());
    }

}
