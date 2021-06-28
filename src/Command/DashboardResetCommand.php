<?php

namespace App\Command;

use App\Service\WiilockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class DashboardResetCommand extends Command {

    protected static $defaultName = 'app:dashboards:reset';

    /** @Required */
    public EntityManagerInterface $entityManager;

    /** @Required */
    public WiilockService $wiilockService;

    protected function configure(): void {
        $this->setDescription('This command reset wiilock for dashboards feed.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int {
        if($this->wiilockService->dashboardIsBeingFed($this->entityManager)) {
            $output->writeln("Dashboards are being fed...");
            $this->wiilockService->toggleFeedingDashboard($this->entityManager, false);
            $this->entityManager->flush();
        }
        $output->writeln("Wiilock reset...");

        return 0;
    }

}
