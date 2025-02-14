<?php

namespace App\Command;

use App\Entity\Tracking\Pack;
use App\Service\TrackingDelayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;


#[AsCommand(
    name: CalculateTrackingDelayCommand::COMMAND_NAME,
    description: 'Calculate the tracking delay of the given logistic unit and save it in database.'
)]
class CalculateTrackingDelayCommand extends Command {
    public const COMMAND_NAME = 'app:tracking:calculate-delay';

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public TrackingDelayService $trackingDelayService;

    protected function configure(): void {
        $this
            ->addArgument('code', InputArgument::REQUIRED, 'The logistic unit code to recalculate delay.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $packRepository = $this->entityManager->getRepository(Pack::class);

        $pack = $packRepository->findOneBy([
            "code" => $input->getArgument("code"),
        ]);

        $this->trackingDelayService->updatePackTrackingDelay($this->entityManager, $pack);
        $this->entityManager->flush();

        return 0;
    }

}
