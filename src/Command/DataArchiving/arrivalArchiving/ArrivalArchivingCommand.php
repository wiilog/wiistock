<?php

namespace App\Command\DataArchiving\arrivalArchiving;

use App\Entity\Arrivage;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: ArrivalArchivingCommand::COMMAND_NAME,
    description: 'Archiving Arrival'
)]
class ArrivalArchivingCommand extends Command {
    const COMMAND_NAME = 'app:purge:arrival';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    /**
     * Executes the command to archive arrivals by calling the batch archiving command repeatedly
     * until all arrivals older than the specified date are archived.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $arrivageRepository = $this->entityManager->getRepository(Arrivage::class);
        $io = new SymfonyStyle($input, $output);

        // Notify the user that the archiving process has started
        $io->success('Start archiving Pack and Arrivage');

        // Calculate the date threshold for archiving
        $dateToArchive = new DateTime('-' . ArrivalBatchArchivingCommand::ARCHIVE_ARRIVALS_OLDER_THAN . ArrivalBatchArchivingCommand::DATA_ARCHIVING_THRESHOLD);

        // Count the number of arrivals to process
        $toTreatCount = $arrivageRepository->countOlderThan($dateToArchive);
        $io->info('Arrivage to treat: ' . $toTreatCount);

        // Loop until no more arrivals remain or an error occurs
        do {
            $io->warning('Archiving Arrivage');

            // Execute the batch archiving command
            exec('php bin/console ' . ArrivalBatchArchivingCommand::COMMAND_NAME, $output, $returnVar);

            // Update the count of arrivals to process
            $toTreatCount = $arrivageRepository->countOlderThan($dateToArchive);
            $io->info('Arrivage to treat: ' . $toTreatCount);

        } while ($returnVar === self::SUCCESS && $toTreatCount > 0);

        // Notify the user that the archiving process has completed
        $io->success('End archiving Pack and Arrivage');

        return Command::SUCCESS;
    }
}
