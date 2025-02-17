<?php
// At every 5th minute
// */5 * * * *

namespace App\Command\Cron;

use App\Entity\Dashboard;
use App\Entity\Wiilock;
use App\Messenger\Dashboard\CalculateDashboardFeedingMessage;
use App\Messenger\Dashboard\CalculateLatePackComponentsFeedingMessage;
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

    public function __construct(private EntityManagerInterface  $entityManager,
                                private MessageBusInterface     $messageBus,
                                private WiilockService          $wiilockService) {
        parent::__construct();
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
        $this->wiilockService->toggleFeedingCommand($this->entityManager, false, Wiilock::DASHBOARD_FED_KEY);
        $this->entityManager->flush();

        $dashboardComponentRepository = $this->entityManager->getRepository(Dashboard\Component::class);

        $components = $dashboardComponentRepository->findAll();

        $latePackComponentIds = [];
        $components = Stream::from($components)
            ->filter(static fn(Dashboard\Component $component) => $component->getType()->getMeterKey() !== Dashboard\ComponentType::LATE_PACKS)
            ->toArray();

        foreach ($components as $component) {
            if($component->getType()->getMeterKey() === Dashboard\ComponentType::LATE_PACKS) {
                $latePackComponentIds[] = $component->getId();
            } else {
                $this->messageBus->dispatch(new CalculateDashboardFeedingMessage($component->getId()));
                $this->entityManager->flush();
            }
        }

        if (count($latePackComponentIds) > 0) {
            $this->messageBus->dispatch(new CalculateLatePackComponentsFeedingMessage($latePackComponentIds));
        }

        return 0;
    }
}
