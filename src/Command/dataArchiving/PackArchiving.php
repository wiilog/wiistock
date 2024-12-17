<?php

namespace App\Command\dataArchiving;

use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Service\TranslationService;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: 'app:purge:pack',
    description: 'Archiving Pack and TrackingMovement'
)]
class PackArchiving extends Command {

    const ARCHIVE_PACK_OLDER_THAN = 2; // year

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $trackingMovementRepository = $this->entityManager->getRepository(TrackingMovement::class);
        $packRepository = $this->entityManager->getRepository(Pack::class);

        $io = new SymfonyStyle($input, $output);
        $io->title('Archiving Pack and TrackingMovement');

        // all tracking movement older than ARCHIVE_PACK_OLDER_THAN years will be archived
        // archiving means that the data will be added to an CSV file and then deleted from the database
        // the CSV file will be stored in the /var/dataArchiving folder temporarily

        // get all tracking movement older than ARCHIVE_PACK_OLDER_THAN years
        try {
            $dateToArchive = new DateTime('-' . self::ARCHIVE_PACK_OLDER_THAN . ' years');
        } catch (\Exception $e) {
            $io->error('Error while creating date to archive');
            return 1;
        }

        $trackingMovementsToArchive = $trackingMovementRepository->findBy(['datetime' => $dateToArchive]);

        $io->success('Pack and TrackingMovement archiving done');
        return 0;
    }

}
