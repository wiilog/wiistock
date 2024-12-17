<?php

namespace App\Command\dataArchiving;

use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Helper\FileSystem;
use App\Service\CSVExportService;
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
use WiiCommon\Helper\Stream;

#[AsCommand(
    name: 'app:purge:pack',
    description: 'Archiving Pack and TrackingMovement'
)]
class PackArchiving extends Command {

    const ARCHIVE_PACK_OLDER_THAN = 2; // year
    const FILE_NAME_DATE_FORMAT = 'Y-m-d';
    const TEMPORARY_FOLDER = '/var/dataArchiving/';

    private FileSystem $filesystem;
    private string $absoluteCachePath;

    public function __construct(
        private readonly EntityManagerInterface  $entityManager,
        private readonly CSVExportService        $csvExportService,
        private readonly PackService             $packService,
        private readonly TrackingMovementService $trackingMovementService,

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
        $io = new SymfonyStyle($input, $output);
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
            ->keymap(function (string $fileName,string $entityToArchive) use ($trackingMovementExportableColumnsSorted, $io): array {
                // if repository self::TEMPORARY_FOLDER does not exist, create it
                if (!$this->filesystem->isDir()) {
                    $this->filesystem->mkdir();
                }

                $fileExists = $this->filesystem->exists($fileName);

                $file = fopen(".".self::TEMPORARY_FOLDER . $fileName, 'w');

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
                }
                return [$entityToArchive, $file];
            });

        $trackingMovementsToArchive = $trackingMovementRepository->findBy(['datetime' => $dateToArchive]);
        foreach ($trackingMovementsToArchive as $trackingMovement) {
           $io->text('Archiving TrackingMovement ' . $trackingMovement->getId());
        }

        //close the file
        foreach ($files as $entityToArchive => $file) {
            fclose($file);
        }

        $io->success('Pack and TrackingMovement archiving done');
        return 0;
    }

    private function generateFileName(string $entityToArchive, DateTime $dateToArchive): string {
        return $_ENV['APP_LOCALE'] . $entityToArchive . (new DateTime())->format(self::FILE_NAME_DATE_FORMAT) . '_' . $dateToArchive->format(self::FILE_NAME_DATE_FORMAT) . '.csv';
    }

}
