<?php

namespace App\Command\Cron;

use App\Entity\Dashboard;
use App\Entity\Wiilock;
use App\Messenger\Dashboard\CalculateComponentsWithDelayMessage;
use App\Messenger\Dashboard\CalculateDashboardFeedingMessage;
use App\Messenger\Dashboard\CalculateLatePackComponentsMessage;
use App\Service\WiilockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: DashboardFeedCommand::COMMAND_NAME,
    description: 'Feeds the dashboard data.'
)]
class DashboardFeedCommand extends Command {
    public const COMMAND_NAME = 'app:feed:dashboards';

    public function __construct(private EntityManagerInterface  $entityManager,
                                private MessageBusInterface     $messageBus,
                                private WiilockService          $wiilockService) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if(!$this->wiilockService->dashboardNeedsFeeding($this->entityManager)) {
            $output->writeln("Dashboards are being fed, aborting");
            return 0;
        }
        $this->wiilockService->toggleFeedingCommand($this->entityManager, false, Wiilock::DASHBOARD_FED_KEY);
        $this->entityManager->flush();

        $dashboardComponentRepository = $this->entityManager->getRepository(Dashboard\Component::class);

        $components = $dashboardComponentRepository->findAll();

        $latePackComponentIds = [];
        $componentsWithDelayIds = [];

        foreach ($components as $component) {
            if($component->getType()->getMeterKey() === Dashboard\ComponentType::LATE_PACKS) {
                $latePackComponentIds[] = $component->getId();
//            } else if (in_array($component->getType()->getMeterKey(), [Dashboard\ComponentType::ENTRIES_TO_HANDLE_BY_TRACKING_DELAY, Dashboard\ComponentType::ONGOING_PACKS_WITH_TRACKING_DELAY])) {
            } else if (in_array($component->getType()->getMeterKey(), [Dashboard\ComponentType::ENTRIES_TO_HANDLE_BY_TRACKING_DELAY])) {
                $componentsWithDelayIds[] = $component->getId();
            } else {
                $this->messageBus->dispatch(new CalculateDashboardFeedingMessage($component->getId()));
                $this->entityManager->flush();
            }
        }

        if (count($componentsWithDelayIds) > 0) {
            $this->messageBus->dispatch(new CalculateComponentsWithDelayMessage($componentsWithDelayIds));
            $this->entityManager->flush();
        }

        if (count($latePackComponentIds) > 0) {
            $this->messageBus->dispatch(new CalculateLatePackComponentsMessage($latePackComponentIds));
            $this->entityManager->flush();
        }

        return 0;
    }
}
