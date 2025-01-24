<?php

namespace App\Command\Purge;

use App\Entity\Dispute;
use App\Entity\ReceiptAssociation;
use App\Entity\Tracking\Pack;
use App\Helper\FileSystem;
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


#[AsCommand(
    name: ReceiptAssociationsPurgeCommand::COMMAND_NAME,
    description: 'Archives receipt associations in batches of 500 until memory usage reaches 75% of the limit or no more arrivals are found.'
)]
class ReceiptAssociationsPurgeCommand extends Command {
    public const COMMAND_NAME = 'app:purge:receipt-associations';

    private const BATCH_SIZE = 500;

    private FileSystem $filesystem;
    private StyleInterface $io;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PurgeService           $purgeService,
        KernelInterface                $kernel,
    ) {
        parent::__construct();
        $this->filesystem = new FileSystem($kernel->getProjectDir() . PurgeAllCommand::ARCHIVE_DIR);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $this->io = new SymfonyStyle($input, $output);

        $this->io->title('Archiving receiptAssociations in batches of ' . self::BATCH_SIZE);

        ini_set('memory_limit', (string)PurgeAllCommand::MEMORY_LIMIT);

        $dateToArchive = new DateTime('-' . PurgeAllCommand::PURGE_ITEMS_OLDER_THAN . PurgeAllCommand::DATA_PURGE_THRESHOLD);

        $files = $this->purgeService->createAndOpenPurgeFiles(
            $dateToArchive,
            [
                Pack::class,
                ReceiptAssociation::class,
                Dispute::class
            ],
            $this->filesystem,
            []
        );

        $this->processReceiptAssociations($dateToArchive, $files);

        $this->finalizeFiles($files);
        $this->io->success('Arrival archiving completed.');

        return Command::SUCCESS;
    }

    private function processReceiptAssociations(DateTime $dateToArchive,
                                                array    $files): void {

        $receiptAssociationRepository = $this->entityManager->getRepository(ReceiptAssociation::class);
        $totalToArchive = $receiptAssociationRepository->countReceiptAssociationToArchive($dateToArchive);
        $this->io->progressStart($totalToArchive);

        $batchCount = 0;

        $receiptAssociationsToArchive = $receiptAssociationRepository->iterateReceiptAssociationToArchive($dateToArchive);

        foreach ($receiptAssociationsToArchive as $receiptAssociation) {
            if ($receiptAssociation->getLogisticUnits()->isEmpty()) {
                $this->purgeService->archiveReceiptAssociation($this->entityManager, $receiptAssociation, null, $files);
            }
            else {
                foreach ($receiptAssociation->getLogisticUnits() as $logisticUnit) {
                    $this->purgeService->archivePack($this->entityManager, $logisticUnit, $files);
                }
            }

            $batchCount++;
            $this->io->progressAdvance();

            if ($batchCount === self::BATCH_SIZE) {
                $this->flushBatch($files);

                $batchCount = 0;

                if ($this->isMemoryLimitReached()) {
                    $this->io->warning('Memory limit reached. Stopping further processing.');
                    break;
                }
            }
        }

        $this->flushBatch($files);

        $this->io->progressFinish();
    }

    private function flushBatch(array $files): void {
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->flushFiles($files);
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
