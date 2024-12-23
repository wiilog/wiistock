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
    name: PackArchivingCommand::COMMAND_NAME,
    description: 'Archiving Pack and TrackingMovement'
)]
class PackArchivingCommand extends Command {
    const COMMAND_NAME = 'app:purge:pack';

    const ARCHIVE_PACK_OLDER_THAN = 2; // year
    const FILE_NAME_DATE_FORMAT = 'Y-m-d';
    const TEMPORARY_FOLDER = '/var/dataArchiving/';
    const BATCH_SIZE = 1000;

    public function __construct(
        private readonly EntityManagerInterface  $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $trackingMovementRepository = $this->entityManager->getRepository(TrackingMovement::class);
        $io = new SymfonyStyle($input, $output);

        $io->success('Start archiving Pack and TrackingMovement');
        try {
            $dateToArchive = new DateTime('-' . self::ARCHIVE_PACK_OLDER_THAN . ' years');
        } catch (\Exception $e) {
            $io->error('Error while creating date to archive');
            return 1;
        }

        $toTreatCount = $trackingMovementRepository->countOlderThan($dateToArchive);
        $io->info('TrackingMovement to treat: ' . $toTreatCount);
        do {
            $io->warning('archiving TrackingMovement');

            exec('php bin/console ' . PackBatchArchivingCommand::COMMAND_NAME, $output, $returnVar);


            $toTreatCount = $trackingMovementRepository->countOlderThan($dateToArchive);
            $io->info('TrackingMovement to treat: ' . $toTreatCount);
        } while ($returnVar === self::SUCCESS && $toTreatCount > 0);

        $io->success('End archiving Pack and TrackingMovement');

        return 0;
    }
}
