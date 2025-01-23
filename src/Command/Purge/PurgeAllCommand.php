<?php

namespace App\Command\Purge;

use App\Entity\Arrivage;
use App\Entity\ReceiptAssociation;
use App\Entity\Tracking\TrackingMovement;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: PurgeAllCommand::COMMAND_NAME,
    description: 'Archiving Pack and TrackingMovement'
)]
class PurgeAllCommand extends Command {

    public const DATA_PURGE_THRESHOLD = "years";
    public const PURGE_ITEMS_OLDER_THAN = 2;

    public const ARCHIVE_DIR = '/var/data-archiving/';
    public const COMMAND_NAME = 'app:purge:all';

    public const MEMORY_LIMIT = 2147483648;
    public const MEMORY_USAGE_THRESHOLD = 0.75;


    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct(self::COMMAND_NAME);
    }

    /**
     * Executes the command to archive arrivals by calling the batch archiving command repeatedly
     * until all arrivals older than the specified date are archived.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $trackingMovementRepository = $this->entityManager->getRepository(TrackingMovement::class);
        $arrivalRepository = $this->entityManager->getRepository(Arrivage::class);
        $receiptAssociationRepository = $this->entityManager->getRepository(ReceiptAssociation::class);
        $io = new SymfonyStyle($input, $output);

        // Calculate the date threshold for archiving
        $dateToArchive = new DateTime('-' . self::PURGE_ITEMS_OLDER_THAN . self::DATA_PURGE_THRESHOLD);

        $allPurges = [
            [
                "entity" => "Pack",
                "commandName" => PacksPurgeCommand::COMMAND_NAME,
                "count" => fn() => $trackingMovementRepository->countOlderThan($dateToArchive),
            ],
            [
                "entity" => "Arrivals",
                "commandName" => ArrivalsPurgeCommand::COMMAND_NAME,
                "count" => fn() => $arrivalRepository->countOlderThan($dateToArchive),
            ],
            [
                "entity" => "ReceiptAssociation",
                "commandName" => ReceiptAssociationsPurgeCommand::COMMAND_NAME,
                "count" => fn() => $receiptAssociationRepository->countOlderThan($dateToArchive),
            ],
        ];

        foreach ($allPurges as $purgeConfig) {
            $toTreatCount = $purgeConfig["count"]();
            $io->info($purgeConfig["entity"] . ' to treat: ' . $toTreatCount);

            do {
                $io->warning('Purge ' . $purgeConfig["entity"]);

                // Execute the batch archiving command
                exec('php bin/console ' . $purgeConfig["commandName"], $output, $returnVar);

                // Update the count element to process
                $toTreatCount = $purgeConfig["count"]();
                $io->info($purgeConfig["entity"] . ' to treat: ' . $toTreatCount);

            } while ($returnVar === self::SUCCESS && $toTreatCount > 0);

        }

        $io->success('Purge ended successfully');

        return Command::SUCCESS;
    }
}
