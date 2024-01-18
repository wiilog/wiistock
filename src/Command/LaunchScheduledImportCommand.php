<?php
// At each minute
// * * * * *

namespace App\Command;

use App\Entity\CategorieStatut;
use App\Entity\ScheduledTask\Import;
use App\Entity\ScheduledTask\ScheduleRule\ImportScheduleRule;
use App\Entity\ScheduledTask\ScheduleRule\ScheduleRule;
use App\Entity\Statut;
use App\Service\CacheService;
use App\Service\FTPService;
use App\Service\ImportService;
use App\Service\ScheduleRuleService;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use phpseclib3\Exception\UnableToConnectException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

#[AsCommand(
    name: "app:launch:scheduled-imports",
    description: "This command executes scheduled imports.",
)]
class LaunchScheduledImportCommand extends Command {

    #[Required]
    public EntityManagerInterface $em;

    #[Required]
    public ImportService $importService;

    #[Required]
    public FTPService $ftpService;

    #[Required]
    public CacheService $cacheService;

    #[Required]
    public ScheduleRuleService $scheduleRuleService;

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $importRepository = $this->getEntityManager()->getRepository(Import::class);

        $importsCache = $this->importService->getScheduledCache($this->getEntityManager());
        $currentKeyImport = $this->importService->getScheduleImportKeyCache(new DateTime());

        if (isset($importsCache[$currentKeyImport])) {
            $imports = $importRepository->findBy(["id" => $importsCache[$currentKeyImport]]);

            foreach ($imports as $import) {
                $this->import($output, $import);
            }

            $this->importService->saveScheduledImportsCache($this->getEntityManager());
        }

        return 0;
    }

    public function import(OutputInterface $output, Import $import): void {
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
            $nextExecutionDate = $this->scheduleRuleService->calculateNextExecutionDate($import->getScheduleRule());
            $import
                ->setForced(false)
                ->setNextExecutionDate($nextExecutionDate)
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
        }
    }

    private function expandScheduledImport(Import $import): array {
        $start = new DateTime();

        $rule = $import->getScheduleRule();
        $clones = [];

        if ($import->getFTPConfig()) {
            $filePathMask = $rule->getFilePath();
            $files = $this->ftpService->glob($import->getFTPConfig(), $filePathMask);
        }
        else {
            /** @var string[] $files */
            $files = glob($rule->getFilePath()) ?: [];
            if (empty($files)) {
                $filePath = $rule->getFilePath();
                if (file_exists($filePath)) {
                    $files[] = $filePath;
                }
            }
        }

        foreach ($files as $file) {
            $clonedRule = (new ImportScheduleRule())
                ->setBegin($rule->getBegin())
                ->setWeekDays($rule->getWeekDays())
                ->setPeriod($rule->getPeriod())
                ->setWeekDays($rule->getWeekDays())
                ->setMonthDays($rule->getMonthDays())
                ->setMonths($rule->getMonths())
                ->setIntervalTime($rule->getIntervalTime())
                ->setFrequency($rule->getFrequency())
                ->setFilePath($file);

            $clones[] = (new Import())
                ->setType($import->getType())
                ->setFTPConfig($import->getFTPConfig())
                ->setLabel($import->getLabel() . " - " . $start->format("d/m/Y H:i"))
                ->setColumnToField($import->getColumnToField())
                ->setCsvFile(null)
                ->setEntity($import->getEntity())
                ->setUser($import->getUser())
                ->setScheduleRule($clonedRule);
        }

        return $clones;
    }

    private function getEntityManager(): EntityManagerInterface {
        return $this->em->isOpen()
            ? $this->em
            : new EntityManager($this->em->getConnection(), $this->em->getConfiguration());
    }

}
