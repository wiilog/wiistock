<?php

namespace App\Command\DataArchiving;

use App\Entity\Arrivage;
use App\Entity\Tracking\TrackingMovement;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: ArchivingCommand::COMMAND_NAME,
    description: 'Archiving Pack and TrackingMovement'
)]
class ArchivingCommand extends Command {
    const COMMAND_NAME = 'app:purge';

    const ARCHIVE_ARRIVALS_OLDER_THAN = 2;
    public const DATA_ARCHIVING_THRESHOLD = "years";

    const TEMPORARY_DIR = '/var/dataArchiving/';

    // 2GB
    const MEMORY_LIMIT = 2147483648;
    const MEMORY_USAGE_THRESHOLD = 0.75;



    public function __construct(
        private readonly EntityManagerInterface  $entityManager,
    ) {
        parent::__construct();
    }

    /**
     * Executes the command to archive arrivals by calling the batch archiving command repeatedly
     * until all arrivals older than the specified date are archived.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $trackingMovementRepository = $this->entityManager->getRepository(TrackingMovement::class);
        $arrivalRepository = $this->entityManager->getRepository(Arrivage::class);
        $io = new SymfonyStyle($input, $output);


        // Calculate the date threshold for archiving
        $dateToArchive = new DateTime('-' . self::ARCHIVE_ARRIVALS_OLDER_THAN . self::DATA_ARCHIVING_THRESHOLD);

        foreach ([
            [
                "entity" => "Pack",
                "commandName" => PackBatchArchivingCommand::COMMAND_NAME,
                "count" => fn () => $trackingMovementRepository->countOlderThan($dateToArchive),
            ],
            [
                "entity" => "Arrivals",
                "commandName" => ArrivalBatchArchivingCommand::COMMAND_NAME,
                "count" => fn () => $arrivalRepository->countOlderThan($dateToArchive),
            ],
        ] as $archivingConfig) {
            $toTreatCount = $archivingConfig["count"]();
            $io->info( $archivingConfig["entity"].' to treat: ' . $toTreatCount);

            do {
                $io->warning('Archiving '. $archivingConfig["entity"]);

                // Execute the batch archiving command
                exec('php bin/console ' . $archivingConfig["commandName"], $output, $returnVar);

                // Update the count of arrivals to process
                $toTreatCount = $archivingConfig["count"]();
                $io->info($archivingConfig["entity"].' to treat: ' . $toTreatCount);

            } while ($returnVar === self::SUCCESS && $toTreatCount > 0);

        }

    $io->success('End archiving Pack and Arrivals');

        return Command::SUCCESS;
    }



}
