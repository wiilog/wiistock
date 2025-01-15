<?php

namespace App\Command\Purge;

use App\Entity\Arrivage;
use App\Helper\FileSystem;
use App\Service\ArrivageService;
use App\Service\CSVExportService;
use App\Service\DataExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use WiiCommon\Helper\Stream;

#[AsCommand(
    name: ArrivalsPurgeCommand::COMMAND_NAME,
    description: 'Archives arrivals in batches of 1000 until memory usage reaches 75% of the limit or no more arrivals are found.'
)]
class ArrivalsPurgeCommand extends Command {
    public const COMMAND_NAME = 'app:purge:arrivals';

    private const BATCH_SIZE = 1000;

    private FileSystem $filesystem;
    private StyleInterface $io;
    private string $absoluteCachePath;


    public function __construct(
        private EntityManagerInterface $entityManager,
        private CSVExportService       $csvExportService,
        private DataExportService      $dataExportService,
        private ArrivageService        $arrivalService,
        KernelInterface                $kernel
    ) {
        parent::__construct();
        $this->absoluteCachePath = $kernel->getProjectDir() . PurgeAllCommand::ARCHIVE_DIR;
        $this->filesystem = new FileSystem($this->absoluteCachePath);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $this->io = new SymfonyStyle($input, $output);

        $this->io->title('Archiving arrivals in batches of ' . self::BATCH_SIZE);

        ini_set('memory_limit', (string)PurgeAllCommand::MEMORY_LIMIT);

        $dateToArchive = new DateTime('-' . PurgeAllCommand::PURGE_ITEMS_OLDER_THAN . PurgeAllCommand::DATA_PURGE_THRESHOLD);
        $arrivageExportableColumnsSorted = $this->arrivalService->getArrivalExportableColumnsSorted($this->entityManager);

        $files = $this->initializeFiles($dateToArchive, $arrivageExportableColumnsSorted);

        $this->processArrivals($dateToArchive, $arrivageExportableColumnsSorted, $files);

        $this->finalizeFiles($files);
        $this->io->success('Arrival archiving completed.');

        return Command::SUCCESS;
    }

    private function initializeFiles(DateTime $dateToArchive, array $arrivageExportableColumnsSorted): array {
        $fileNames = Stream::from([
            Arrivage::class,
        ])
            ->keyMap(fn($entityToArchive) => [
                $entityToArchive,
                $this->dataExportService->generateDataArchichingFileName(
                    $this->dataExportService->getEntityName($entityToArchive),
                    $dateToArchive
                ),
            ])
            ->toArray();

        return $this->csvExportService->createAndOpenDataArchivingFiles($fileNames, $this->filesystem, $this->absoluteCachePath, $arrivageExportableColumnsSorted);
    }

    private function processArrivals(DateTime     $dateToArchive,
                                     array        $columnsSorted,
                                     array        $files): void {

        $arrivalRepository = $this->entityManager->getRepository(Arrivage::class);
        $totalToArchive = $arrivalRepository->countOlderThan($dateToArchive);
        $this->io->progressStart($totalToArchive);

        $batch = [];
        $batchCount = 0;

        $to = new DateTime();

        $this->arrivalService->launchExportCache($this->entityManager, $dateToArchive, $to);
        $arrivalsArchive = $arrivalRepository->iterateOlderThan($dateToArchive);

        foreach ($arrivalsArchive as $arrival) {
            $this->arrivalService->putArrivalLine(
                $files[Arrivage::class],
                $arrival,
                $columnsSorted['codes']
            );

            $batch[] = $arrival;
            $batchCount++;
            $this->io->progressAdvance();

            if ($batchCount === self::BATCH_SIZE) {
                $this->archiveBatch($batch);
                $this->flushFiles($files);
                $batch = [];
                $batchCount = 0;

                if ($this->isMemoryLimitReached()) {
                    $this->io->warning('Memory limit reached. Stopping further processing.');
                    break;
                }
            }
        }

        if (!empty($batch)) {
            $this->archiveBatch($batch);
        }

        $this->io->progressFinish();
    }

    private function archiveBatch(array $arrivals): void {
        foreach ($arrivals as $arrival) {
            $this->detachArrivalRelationships($arrival);
            $this->entityManager->remove($arrival);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private function detachArrivalRelationships(Arrivage $arrival): void {
        if ($reception = $arrival->getReception()) {
            $reception->setArrival(null);
        }

        foreach ($arrival->getAttachments() as $attachment) {
            $attachment->setArrivage(null);
        }

        foreach ($arrival->getUrgences() as $urgence) {
            $urgence->setLastArrival(null);
        }

        foreach ($arrival->getPacks() as $pack) {
            $pack->setArrivage(null);
        }
    }

    private function finalizeFiles(array $files): void {
        foreach ($files as $file) {
            fclose($file);
        }
    }

    private function flushFiles(array $files): void {
        foreach ($files as $file) {
            fflush($file);
        }
    }

    private function isMemoryLimitReached(): bool {
        return memory_get_usage() > (PurgeAllCommand::MEMORY_LIMIT * PurgeAllCommand::MEMORY_USAGE_THRESHOLD);
    }
}
