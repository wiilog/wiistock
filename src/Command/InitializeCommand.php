<?php

namespace App\Command;

use App\Service\SettingsService;
use App\Service\WiilockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitializeCommand extends Command {

    protected static $defaultName = "app:initialize";

    /** @Required */
    public SettingsService $settingsService;

    /** @Required */
    public EntityManagerInterface $entityManager;

    /** @Required */
    public WiilockService $wiilockService;

    protected function configure(): void {
        $this->setDescription("Initializes the application");
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int {
        if ($this->wiilockService->dashboardIsBeingFed($this->entityManager)) {
            $output->writeln("Dashboards were locked, unlocking");
            $this->wiilockService->toggleFeedingDashboard($this->entityManager, false);
        } else {
            $output->writeln("Dashboards were not locked");
        }

        $this->settingsService->generateFontSCSS();
        $this->settingsService->generateSessionConfig();

        $this->entityManager->flush();

        return 0;
    }

}
