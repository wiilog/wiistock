<?php

namespace App\Command\Purge;

use App\Entity\CategorieCL;
use App\Entity\Dispute;
use App\Entity\ReceiptAssociation;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Helper\FileSystem;
use App\Serializer\SerializerUsageEnum;
use App\Service\FreeFieldService;
use App\Service\PurgeService;
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


#[AsCommand(
    name: PacksPurgeCommand::COMMAND_NAME,
    description: 'Purge Pack and TrackingMovement on batch of 1000. The function end when there is no more TrackingMovement to archive of when the memory has reached 75% of the limit',
)]
class PacksPurgeCommand extends Command {
    public const COMMAND_NAME = 'app:purge:packs';

    private const BATCH_SIZE = 1000;

    private FileSystem $filesystem;


    public function __construct(
        private EntityManagerInterface  $entityManager,
        private TrackingMovementService $trackingMovementService,
        private FreeFieldService        $freeFieldService,
        private SerializerInterface     $serializer,
        private PurgeService            $purgeService,
        KernelInterface                 $kernel,
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->filesystem = new FileSystem($kernel->getProjectDir() . PurgeAllCommand::ARCHIVE_DIR);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $trackingMovementRepository = $this->entityManager->getRepository(TrackingMovement::class);
        $trackingMovementFreeFieldsConfig = $this->freeFieldService->createExportArrayConfig($this->entityManager, [CategorieCL::MVT_TRACA]);

        $io = new SymfonyStyle($input, $output);

        $functionMemoryLimit = PurgeAllCommand::MEMORY_LIMIT * PurgeAllCommand::MEMORY_USAGE_THRESHOLD;
        // allow more memory Usage
        ini_set('memory_limit', PurgeAllCommand::MEMORY_LIMIT);

        $io->title("Archiving Pack and TrackingMovement on batch of" . self::BATCH_SIZE);

        // all tracking movement older than ARCHIVE_PACK_OLDER_THAN years will be archived
        // archiving means that the data will be added to an CSV file and then deleted from the database
        // the CSV file will be stored in the TEMPORARY_FOLDER folder temporarily

        $dateToArchive = new DateTime('-' . PurgeAllCommand::PURGE_ITEMS_OLDER_THAN . PurgeAllCommand::DATA_PURGE_THRESHOLD);
        $io->warning(TrackingMovement::class);

        $sortedColumns = [
            TrackingMovement::class => $this->trackingMovementService->getTrackingMovementExportableColumnsSorted($this->entityManager)
        ];

        $files = $this->purgeService->createAndOpenPurgeFiles(
            $dateToArchive,
            [
                TrackingMovement::class,
                Pack::class,
                ReceiptAssociation::class,
                Dispute::class
            ],
            $this->filesystem,
            $sortedColumns
        );

        // init progress bar
        $io->progressStart($trackingMovementRepository->countOlderThan($dateToArchive));

        $batch = 0;
        $packs = [];
        $iteratorTrackingToArchive = $trackingMovementRepository->iterateOlderThan($dateToArchive);
        foreach ($iteratorTrackingToArchive as $trackingMovement) {

            $this->trackingMovementService->putMovementLine(
                $files[TrackingMovement::class],
                $this->serializer->normalize($trackingMovement, null, ["usage" => SerializerUsageEnum::CSV_EXPORT]),
                $sortedColumns[TrackingMovement::class]["codes"],
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

    /**
     * @param Pack[] $packs
     * @param resource[]  $files
     */
    private function treatPackAndFLush(array $packs, array $files): void {
        $groups = [];
        foreach ($packs as $pack) {
            if ($pack->getGroupIteration()) {
                $groups[$pack->getId()] ??= $pack;
            } else {
                $this->purgeService->archivePack($this->entityManager, $pack, $files);
            }
        }

        foreach ($groups as $group) {
            $this->purgeService->archivePack($this->entityManager, $group, $files);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
