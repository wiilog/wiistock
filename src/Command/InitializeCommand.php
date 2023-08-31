<?php

namespace App\Command;

use App\Entity\Wiilock;
use App\Service\SettingsService;
use App\Service\WiilockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: 'app:initialize',
    description: 'This command initializes the application'
)]
class InitializeCommand extends Command {

    #[Required]
    public SettingsService $settingsService;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public WiilockService $wiilockService;

    protected function execute(InputInterface $input, OutputInterface $output): ?int {
        if ($this->wiilockService->dashboardIsBeingFed($this->entityManager)) {
            $output->writeln("Dashboards were locked, unlocking");
            $this->wiilockService->toggleFeedingCommand($this->entityManager, false, Wiilock::DASHBOARD_FED_KEY);
        } else {
            $output->writeln("Dashboards were not locked");
        }

        $this->settingsService->generateFontSCSS();
        $this->settingsService->generateSessionConfig();

        $this->entityManager->flush();

        return 0;
    }

}
