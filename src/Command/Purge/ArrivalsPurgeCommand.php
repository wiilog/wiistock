<?php

namespace App\Command\Purge;

use App\Entity\Arrivage;
use App\Entity\Dispute;
use App\Entity\ReceiptAssociation;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Helper\FileSystem;
use App\Service\ArrivageService;
use App\Service\PurgeService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
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

    private const BATCH_SIZE = 500;

    private FileSystem $filesystem;
    private StyleInterface $io;
    private string $absoluteArchiveDirPath;


    public function __construct(
        private EntityManagerInterface $entityManager,
        private ArrivageService        $arrivalService,
        private PurgeService           $purgeService,
        KernelInterface                $kernel,
    ) {
        parent::__construct();
        $this->absoluteArchiveDirPath = $kernel->getProjectDir() . PurgeAllCommand::ARCHIVE_DIR;
        $this->filesystem = new FileSystem($this->absoluteArchiveDirPath);
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
            TrackingMovement::class,
            Pack::class,
            ReceiptAssociation::class,
            Dispute::class
        ])
            ->keyMap(fn($entityToArchive) => [
                $entityToArchive,
                $this->purgeService->generateDataPurgeFileName(
                    $this->purgeService->getEntityName($entityToArchive),
                    $dateToArchive
                ),
            ])
            ->toArray();

        return $this->purgeService->createAndOpenPurgeFiles($fileNames, $this->filesystem, $this->absoluteArchiveDirPath, $arrivageExportableColumnsSorted);
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

        /** @var Arrivage $arrival */

        foreach ($arrivalsArchive as $arrival) {
            $this->arrivalService->putArrivalLine(
                $files[Arrivage::class],
                $arrival,
                $columnsSorted['codes']
            );

            foreach ($arrival->getPacks() as $pack) {
                $this->purgeService->archivePack($this->entityManager, $pack, $files);
            }

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
