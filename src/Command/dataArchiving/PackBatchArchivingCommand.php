<?php

namespace App\Command\dataArchiving;

use App\Entity\CategorieCL;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Helper\FileSystem;
use App\Serializer\SerializerUsageEnum;
use App\Service\CSVExportService;
use App\Service\FreeFieldService;
use App\Service\PackService;
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
    description: 'Archiving Pack and TrackingMovement'
)]
class PackBatchArchivingCommand extends Command {
    const COMMAND_NAME = 'app:purge:batch-pack';

    const ARCHIVE_PACK_OLDER_THAN = 2; // year
    const FILE_NAME_DATE_FORMAT = 'Y-m-d';
    const TEMPORARY_FOLDER = '/var/dataArchiving/';
    const BATCH_SIZE = 1000;

    private FileSystem $filesystem;
    private string $absoluteCachePath;

    public function __construct(
        private readonly EntityManagerInterface  $entityManager,
        private readonly CSVExportService        $csvExportService,
        private readonly PackService             $packService,
        private readonly TrackingMovementService $trackingMovementService,
        private readonly FreeFieldService        $freeFieldService,
        private readonly SerializerInterface     $serializer,

        KernelInterface                          $kernel,
    ) {
        parent::__construct();
        $this->absoluteCachePath = $kernel->getProjectDir() . self::TEMPORARY_FOLDER;
        $this->filesystem = new FileSystem($this->absoluteCachePath);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $trackingMovementRepository = $this->entityManager->getRepository(TrackingMovement::class);
        $packRepository = $this->entityManager->getRepository(Pack::class);

        $trackingMovementExportableColumnsSorted = $this->trackingMovementService->getTrackingMovementExportableColumnsSorted($this->entityManager);
        $trackingMovementFreeFieldsConfig = $this->freeFieldService->createExportArrayConfig($this->entityManager, [CategorieCL::MVT_TRACA]);

        $io = new SymfonyStyle($input, $output);

        // allow more memory Usage
        ini_set('memory_limit', '2147483648');

        $io->title('Archiving Pack and TrackingMovement');

        // all tracking movement older than ARCHIVE_PACK_OLDER_THAN years will be archived
        // archiving means that the data will be added to an CSV file and then deleted from the database
        // the CSV file will be stored in the TEMPORARY_FOLDER folder temporarily

        // get all tracking movement older than ARCHIVE_PACK_OLDER_THAN years
        try {
            $dateToArchive = new DateTime('-' . self::ARCHIVE_PACK_OLDER_THAN . ' years');
        } catch (\Exception $e) {
            $io->error('Error while creating date to archive');
            return 1;
        }

        // file name =  APP_LOCALE (env) + entityToArchive + today's date + _ + $dateToArchive + .csv
        // date format = FILE_NAME_DATE_FORMAT
        $fileNames = [
            TrackingMovement::class => $this->generateFileName("TrackingMovement", $dateToArchive),
            Pack::class => $this->generateFileName("Pack", $dateToArchive)
        ];

        // the file normally should not exist
        // if the file already exists we keep it and add the new data to it (without deleting the old data, and without rewriting headers)
        $files = Stream::from($fileNames)
            ->keymap(function (string $fileName, string $entityToArchive) use ($trackingMovementExportableColumnsSorted, $io): array {
                // if repository self::TEMPORARY_FOLDER does not exist, create it
                if (!$this->filesystem->isDir()) {
                    $this->filesystem->mkdir();
                }

                $fileExists = $this->filesystem->exists($fileName);

                $file = fopen("." . self::TEMPORARY_FOLDER . $fileName, 'a');

                if ($fileExists) {
                    $io->warning('File ' . $fileName . ' already exists. The new data will be added to it.');
                } else {
                    $io->text('Creating file ' . $fileName);

                    //generate the header for the file based on the entity
                    $fileHeader = match ($entityToArchive) {
                        TrackingMovement::class => $trackingMovementExportableColumnsSorted["labels"],
                        Pack::class => $this->packService->getCsvHeader(),
                    };

                    $this->csvExportService->putLine($file, $fileHeader);
                    unset($fileHeader);
                }
                return [$entityToArchive, $file];
            })
            ->toArray();

        // init progress bar
        $io->progressStart($trackingMovementRepository->countOlderThan($dateToArchive));

        $batch = 0;
        $packs = [];
        foreach ($trackingMovementRepository->iterateOlderThan($dateToArchive) as $trackingMovement) {

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
            if(++$batch === self::BATCH_SIZE) {
                $batch = 0;
                $groups = [];
                foreach ($packs as $pack) {
                    // if the pack does not have any more tracking movement, we can archive it
                    if($pack->getTrackingMovements()->isEmpty()) {
                        if ($pack->isBasicUnit()) {
                            if ($pack->getGroupIteration()) {
                                $groups[$pack->getId()] ??= $pack;
                            } else {
                                if ($pack->getGroup()) {
                                    $groups[$pack->getGroup()->getId()] ??= $pack->getGroup();
                                }
                                $this->archivePack($pack, $files);
                            }
                        }
                    }
                }

                foreach ($groups as $group) {
                    if($group->getTrackingMovements()->isEmpty() && $group->getContent()) {
                        $this->archivePack($group, $files);
                    }
                }
                $this->entityManager->flush();
                $this->entityManager->clear();

                foreach ($files as $entityToArchive => $file) {
                    fflush($file);
                }
                gc_collect_cycles();

                $io->text('Memory usage: ' . memory_get_usage());
                if (memory_get_usage() > 1500000000) {
                    $io->warning('Memory limit reached');
                    break;
                }

                $packs = [];
            }
        }

        //close the file
        foreach ($files as $entityToArchive => $file) {
            fclose($file);
        }

        $io->success('Pack and TrackingMovement archiving done');
        return Command::SUCCESS;
    }

    private function generateFileName(string $entityToArchive, DateTime $dateToArchive): string {
        return $_ENV['APP_LOCALE'] . $entityToArchive . (new DateTime())->format(self::FILE_NAME_DATE_FORMAT) . '_' . $dateToArchive->format(self::FILE_NAME_DATE_FORMAT) . '.csv';
    }

    private function archivePack(Pack $pack, array $files): void {
        $this->packService->putPackLine(
            $files[Pack::class],
            $this->serializer->normalize($pack, null, ["usage" => SerializerUsageEnum::CSV_EXPORT])
        );

        $pack->setGroup(null);
        $this->entityManager->remove($pack);
    }

}
