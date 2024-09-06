<?php

namespace App\Command;

use App\Entity\Pack;
use App\Service\TrackingDelayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: 'app:pipi',
    description: 'This commands generate the yaml translations.'
)]
class PIPICommand extends Command {

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public TrackingDelayService $trackingDelayService;

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $packRepository = $this->entityManager->getRepository(Pack::class);
        $elapsedTimeSeconds = $this->trackingDelayService->calculatePackElapsedTime(
            $this->entityManager,
            $packRepository->find(15)
        );

        $secondInDay = 24 * 60 * 60;
        dump($elapsedTimeSeconds);




        return 0;
    }

}
