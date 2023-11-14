<?php

namespace App\Command;

use App\Entity\CategorieStatut;
use App\Entity\Import;
use App\Entity\ImportScheduleRule;
use App\Entity\ScheduleRule;
use App\Entity\Statut;
use App\Service\CacheService;
use App\Service\ImportService;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

class LaunchScheduledImportCommand extends Command {

    private const DEFAULT_NAME = "app:launch:scheduled-imports";

    #[Required]
    public EntityManagerInterface $em;

    #[Required]
    public ImportService $importService;

    #[Required]
    public CacheService $cacheService;

    protected function configure(): void {
        $this->setName(self::DEFAULT_NAME)
            ->setDescription("This command executes scheduled imports.");
    }

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
        $inProgressImport = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_IN_PROGRESS);
        $import = $entityManager->getRepository(Import::class)->find($import->getId());
        $start = new DateTime();

        $rule = $import->getScheduleRule();

        if ($rule->getFrequency() === ScheduleRule::ONCE) {
            $clones = $this->expandSingleImport($import);
        } else {
            $clones = $this->expandScheduledImport($import);
        }

        foreach($clones as $clone) {
            $import->setForced(false);
            $clone
                ->setStatus($inProgressImport)
                ->setStartDate($start);

            $nextExecutionDate = $this->importService->calculateNextExecutionDate($import);
            $import->setNextExecutionDate($nextExecutionDate);
            $entityManager->persist($clone);
            $entityManager->flush();

            $output->writeln("Starting import {$import->getId()} at {$clone->getStartDate()->format('d/m/Y H:i:s')}");

            $this->importService->treatImport($entityManager, $clone, ImportService::IMPORT_MODE_RUN);

            $clone = $this->importService->getImport();
            $endDate = $clone->getEndDate();
            $endDateStr = $endDate ? $endDate->format('d/m/Y H:i:s') : '';
            $output->writeln("Finished import {$import->getId()} at $endDateStr");
        }
    }

    private function expandSingleImport(Import $import): array {
        return [$import];
    }

    private function expandScheduledImport(Import $import): array {
        $start = new DateTime();

        $rule = $import->getScheduleRule();
        $clones = [];

        $files = glob($rule->getFilePath());

        if (empty($files)) {
            $files[] = $rule->getFilePath();
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
                ->setLabel($import->getLabel() . " - " . $start->format("d/m/Y H:i"))
                ->setColumnToField($import->getColumnToField())
                ->setCsvFile(null)
                ->setEntity($import->getEntity())
                ->setUser(null)
                ->setScheduleRule($clonedRule);
        }

        return $clones;
    }

    private function getEntityManager(): EntityManagerInterface {
        return $this->em->isOpen()
            ? $this->em
            : EntityManager::create($this->em->getConnection(), $this->em->getConfiguration());
    }

}
