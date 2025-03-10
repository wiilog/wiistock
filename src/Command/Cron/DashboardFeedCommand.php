<?php

namespace App\Command\Cron;

use App\Entity\Dashboard;
use App\Entity\Wiilock;
use App\Messenger\Dashboard\FeedDashboardComponentMessage;
use App\Messenger\Dashboard\FeedMultipleDashboardComponentMessage;
use App\Service\Dashboard\DashboardService;
use App\Service\Dashboard\MultipleDashboardComponentGenerator\MultipleDashboardComponentGenerator;
use App\Service\ExceptionLoggerService;
use App\Service\WiilockService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
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

    public function __construct(private EntityManagerInterface $entityManager,
                                private ExceptionLoggerService $loggerService,
                                private MessageBusInterface    $messageBus,
                                private WiilockService         $wiilockService,
                                private DashboardService       $dashboardService) {
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

        $multipleComponentIds = [];

        foreach ($components as $component) {
            $componentType = $component->getType();
            $meterKey = $componentType->getMeterKey();

            $component->setErrorMessage(null);
            $this->entityManager->flush();

            $generatorClass = $this->dashboardService->getGeneratorClass($meterKey);

            if($generatorClass === DashboardService::NO_GENERATOR) {
                continue;
            }
            else if (!$generatorClass) {
                $component->setErrorMessage(DashboardService::DASHBOARD_ERROR_MESSAGE);
                $this->entityManager->flush();
                $this->loggerService->sendLog(new Exception("Component has no generator"));
            }

            if (is_subclass_of($generatorClass, MultipleDashboardComponentGenerator::class)) {
                $multipleComponentIds[$generatorClass] ??= [];
                $multipleComponentIds[$generatorClass][] = $component->getId();
            } else {
                $this->messageBus->dispatch(new FeedDashboardComponentMessage($component->getId(), $generatorClass));
            }
        }

        foreach($multipleComponentIds as $generatorClass => $componentIds) {
            $this->messageBus->dispatch(new FeedMultipleDashboardComponentMessage($componentIds, $generatorClass));
        }

        return Command::SUCCESS;
    }
}
