<?php

namespace App\Command\DataArchiving;

use App\Entity\CategorieCL;
use App\Entity\Dispute;
use App\Entity\ReceiptAssociation;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Helper\FileSystem;
use App\Serializer\SerializerUsageEnum;
use App\Service\CSVExportService;
use App\Service\DataExportService;
use App\Service\DisputeService;
use App\Service\FormatService;
use App\Service\FreeFieldService;
use App\Service\PackService;
use App\Service\ReceiptAssociationService;
use App\Service\TrackingMovementService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Serializer\SerializerInterface;
use WiiCommon\Helper\Stream;

#[AsCommand(
    name: PackBatchArchivingCommand::COMMAND_NAME,
    description: 'Archiving Pack and TrackingMovement on batch of 1000. The function end when there is no more TrackingMovement to archive of when the memory has reached 75% of the limit',
)]
class PackBatchArchivingCommand extends Command {
    const COMMAND_NAME = 'app:purge:batch-pack';

    const BATCH_SIZE = 1000;

    private FileSystem $filesystem;
    private string $absoluteCachePath;

    public function __construct(
        private readonly EntityManagerInterface    $entityManager,
        private readonly CSVExportService          $csvExportService,
        private readonly PackService               $packService,
        private readonly TrackingMovementService   $trackingMovementService,
        private readonly FreeFieldService          $freeFieldService,
        private readonly SerializerInterface       $serializer,
        private readonly ReceiptAssociationService $receiptAssociationService,
        private readonly FormatService             $formatService,
        private readonly DisputeService            $disputeService,
        KernelInterface                            $kernel, private readonly DataExportService $dataExportService,
    ) {
        parent::__construct();
        $this->absoluteCachePath = $kernel->getProjectDir() . ArchivingCommand::TEMPORARY_DIR;
        $this->filesystem = new FileSystem($this->absoluteCachePath);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $trackingMovementRepository = $this->entityManager->getRepository(TrackingMovement::class);
        $trackingMovementExportableColumnsSorted = $this->trackingMovementService->getTrackingMovementExportableColumnsSorted($this->entityManager);
        $trackingMovementFreeFieldsConfig = $this->freeFieldService->createExportArrayConfig($this->entityManager, [CategorieCL::MVT_TRACA]);

        $io = new SymfonyStyle($input, $output);

        $functionMemoryLimit = ArchivingCommand::MEMORY_LIMIT * ArchivingCommand::MEMORY_USAGE_THRESHOLD;
        // allow more memory Usage
        ini_set('memory_limit', ArchivingCommand::MEMORY_LIMIT);

        $io->title("Archiving Pack and TrackingMovement on batch of" . self::BATCH_SIZE);

        // all tracking movement older than ARCHIVE_PACK_OLDER_THAN years will be archived
        // archiving means that the data will be added to an CSV file and then deleted from the database
        // the CSV file will be stored in the TEMPORARY_FOLDER folder temporarily

        $dateToArchive = new DateTime('-' . ArchivingCommand::ARCHIVE_ARRIVALS_OLDER_THAN . ArchivingCommand::DATA_ARCHIVING_THRESHOLD);
        $io->warning(TrackingMovement::class);

        $fileNames = Stream::from([
            TrackingMovement::class,
            Pack::class,
            ReceiptAssociation::class,
            Dispute::class
        ])
            ->keyMap(fn ($entityToArchive) => [
                $entityToArchive,
                $this->dataExportService->generateDataArchichingFileName($this->getEntityName($entityToArchive), $dateToArchive),
            ])
            ->toArray();

        // the file normally should not exist
        // if the file already exists we keep it and add the new data to it (without deleting the old data, and without rewriting headers)
        $files = $this->csvExportService->createAndOpenDataArchivingFiles($fileNames, $this->filesystem, $this->absoluteCachePath, $trackingMovementExportableColumnsSorted);

        // init progress bar
        $io->progressStart($trackingMovementRepository->countOlderThan($dateToArchive));

        $batch = 0;
        $packs = [];
        $iteratorTrackingToArchive = $trackingMovementRepository->iterateOlderThan($dateToArchive);
        foreach ($iteratorTrackingToArchive as $trackingMovement) {

            $this->trackingMovementService->putMovementLine(
                $files[TrackingMovement::class],
                $this->serializer->normalize($trackingMovement, null, ["usage" => SerializerUsageEnum::CSV_EXPORT]),
                $trackingMovementExportableColumnsSorted["codes"],
                $trackingMovementFreeFieldsConfig
            );
            // we need to keep the pack to delete it later
            $pack = $trackingMovement->getPack();
            if($pack) {
                $packs[$pack->getId()] ??= $pack;
            }

            // in case on problem the flush is not called so we need to remove the tracking movement manually from the relation
            $trackingMovement->setPack(null);

            $this->entityManager->remove($trackingMovement);

            $io->progressAdvance();
            $batch ++;

            if($batch === self::BATCH_SIZE) {
                $batch = 0;

                $this->treatPackAndFLush($packs, $files);

                foreach ($files as $entityToArchive => $file) {
                    fflush($file);
                }
                gc_collect_cycles();

                $io->text('Memory usage: ' . memory_get_usage());
                if (memory_get_usage() > $functionMemoryLimit) {
                    $io->warning('Memory limit reached');
                    break;
                }

                $packs = [];
            }
        }

        $this->treatPackAndFLush($packs, $files);

        //close the file
        foreach ($files as $file) {
            fclose($file);
        }

        $io->success('Pack and TrackingMovement archiving done');
        return Command::SUCCESS;
    }

    private function archivePack(Pack $pack, array $files): void {
        foreach ($pack->getReceiptAssociations() as $receiptAssociation) {
            $this->archiveReceiptAssociation($receiptAssociation, $pack, $files);
        }

        foreach ($pack->getProjectHistoryRecords() as $projectHistoryRecord) {
            $this->entityManager->remove($projectHistoryRecord);
        }

        foreach ($pack->getTransportHistories() as $transportHistory) {
            $this->entityManager->remove($transportHistory);
        }

        foreach ($pack->getPairings() as $pairing) {
            $this->entityManager->remove($pairing);
        }

        foreach ($pack->getDisputes() as $dispute) {
            $this->archiveDispute($dispute, $pack, $files);
        }

        $this->packService->putPackLine(
            $files[Pack::class],
            $this->serializer->normalize($pack, null, ["usage" => SerializerUsageEnum::CSV_EXPORT])
        );

        $pack->setGroup(null);
        $this->entityManager->remove($pack);
    }

    private function archiveReceiptAssociation(ReceiptAssociation $receiptAssociation, Pack $pack, array $files): void {
        $this->receiptAssociationService->putReceiptAssociationLine(
            $files[ReceiptAssociation::class],
            [
                ...$this->serializer->normalize($receiptAssociation, null, ["usage" => SerializerUsageEnum::CSV_EXPORT]),
                "lastActionDate" => $this->formatService->datetime($pack->getLastAction()?->getDatetime()),
                "lastActionLocation" => $this->formatService->location($pack->getLastAction()?->getEmplacement()),
                "logisticUnit" => $this->formatService->pack($pack),
            ]
        );


        $receiptAssociation->removePack($pack);

        if($receiptAssociation->getLogisticUnits()->isEmpty()) {
            $receiptAssociation->setUser(null);
            $this->entityManager->remove($receiptAssociation);
        }
    }

    private function archiveDispute(Dispute $dispute, Pack $pack, array $files): void {
        $this->disputeService->putDisputeLine(
            $files[Dispute::class],
            $dispute,
            [
                "packs" => [$pack],
            ]
        );

        $dispute->removePack($pack);

        if($dispute->getPacks()->isEmpty()) {
            $this->entityManager->remove($dispute);
        }
    }

    private function treatPackAndFLush(array $packs, array $files): void {
        $trackingMovementRepository = $this->entityManager->getRepository(TrackingMovement::class);

        $groups = [];
        foreach ($packs as $pack) {
            // if the pack does not have any more tracking movement, we can archive it
            if($pack->getTrackingMovements()->isEmpty() && !$trackingMovementRepository->findOneBy(["packGroup" => $pack]) ) {
                if ($pack->isBasicUnit()) {
                    if ($pack->getGroupIteration()) {
                        $groups[$pack->getId()] ??= $pack;
                    } else {
                        $this->archivePack($pack, $files);
                    }
                }
            }
        }

        foreach ($groups as $group) {
            if($group->getTrackingMovements()->isEmpty() && $group->getContent()->isEmpty()) {
                $this->archivePack($group, $files);
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private function getEntityName(string $entity): string {
        return Stream::explode("\\", $entity)->last();
    }

}
