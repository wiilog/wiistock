<?php

namespace App\Command;

use App\Entity\Wiilock;
use App\Service\WiilockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: 'app:dashboards:reset',
    description: 'Resets wiilock for dashboards feed.'
)]
class DashboardResetCommand extends Command {

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public WiilockService $wiilockService;

    protected function execute(InputInterface $input, OutputInterface $output): ?int {
        if($this->wiilockService->dashboardIsBeingFed($this->entityManager)) {
            $output->writeln("Dashboards are being fed...");
            $this->wiilockService->toggleFeedingCommand($this->entityManager, false, Wiilock::DASHBOARD_FED_KEY);
            $this->entityManager->flush();
        }
        $output->writeln("Wiilock reset...");

        return 0;
    }

}
