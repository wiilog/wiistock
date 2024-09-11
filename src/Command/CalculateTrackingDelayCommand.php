<?php

namespace App\Command;

use App\Entity\Pack;
use App\Service\TrackingDelayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;


//TODO WIIS-11851 remove ? Pas sur ça peut être bien
#[AsCommand(
    name: 'app:tracking:calculate-delay',
    description: 'This commands generate the yaml translations.'
)]
class CalculateTrackingDelayCommand extends Command {

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

        $this->trackingDelayService->persistTrackingDelay($this->entityManager, $pack, ["force" => true]);
        $this->entityManager->flush();

        return 0;
    }

}
