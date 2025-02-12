<?php
// At every 5th minute
// */5 * * * *

namespace App\Command\Cron;

use App\Entity\Dashboard;
use App\Entity\Wiilock;
use App\Messenger\Dashboard\CalculateDashboardFeedingMessage;
use App\Service\DashboardService;
use App\Service\WiilockService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;
use WiiCommon\Helper\Stream;

#[AsCommand(
    name: DashboardFeedCommand::COMMAND_NAME,
    description: 'Feeds the dashboard data.'
)]
class DashboardFeedCommand extends Command {
    public const COMMAND_NAME = 'app:feed:dashboards';

    private EntityManagerInterface $entityManager;
    private DashboardService $dashboardService;
    private MessageBusInterface $messageBus;
    private WiilockService $wiilockService;

    public function __construct(EntityManagerInterface  $entityManager,
                                DashboardService        $dashboardService,
                                MessageBusInterface     $messageBus,
                                WiilockService          $wiilockService) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->messageBus = $messageBus;
        $this->dashboardService = $dashboardService;
        $this->wiilockService = $wiilockService;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws ORMException
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int {
        if(!$this->wiilockService->dashboardNeedsFeeding($this->entityManager)) {
            $output->writeln("Dashboards are being fed, aborting");
            return 0;
        }
        $this->wiilockService->toggleFeedingCommand($this->entityManager, true, Wiilock::DASHBOARD_FED_KEY);
        $this->entityManager->flush();

        $dashboardComponentRepository = $this->entityManager->getRepository(Dashboard\Component::class);

        $components = $dashboardComponentRepository->findAll();

        $latePackComponentIds = Stream::from($components)
            ->filter(static fn(Dashboard\Component $component) => $component->getType()->getMeterKey() === Dashboard\ComponentType::LATE_PACKS)
            ->map(static fn(Dashboard\Component $component) => $component->getId())
            ->toArray();

        $calculateLatePack = count($latePackComponentIds) > 0;

        $components = Stream::from($components)
            ->filter(static fn(Dashboard\Component $component) => $component->getType()->getMeterKey() !== Dashboard\ComponentType::LATE_PACKS)
            ->toArray();

        foreach ($components as $component) {
            $this->messageBus->dispatch(new CalculateDashboardFeedingMessage($component->getId(), null));
            $this->entityManager->flush();
        }

        if ($calculateLatePack) {
            $this->messageBus->dispatch(new CalculateDashboardFeedingMessage(null, $latePackComponentIds));
        }

        $this->messageBus->dispatch(new CalculateDashboardFeedingMessage(null, null));

        return 0;
    }
}
