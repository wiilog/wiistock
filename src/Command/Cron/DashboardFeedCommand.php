<?php
// At every 5th minute
// */5 * * * *

namespace App\Command\Cron;

use App\Entity\Dashboard;
use App\Entity\DaysWorked;
use App\Entity\Wiilock;
use App\Entity\WorkFreeDay;
use App\Service\DashboardService;
use App\Service\WiilockService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: DashboardFeedCommand::COMMAND_NAME,
    description: 'Feeds the dashboard data.'
)]
class DashboardFeedCommand extends Command {
    public const COMMAND_NAME = 'app:feed:dashboards';

    private EntityManagerInterface $entityManager;
    private DashboardService $dashboardService;
    private WiilockService $wiilockService;

    public function __construct(EntityManagerInterface $entityManager,
                                DashboardService $dashboardService,
                                WiilockService $wiilockService) {
        parent::__construct();
        $this->entityManager = $entityManager;
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
        $entityManager = $this->getEntityManager();
        if(!$this->wiilockService->dashboardNeedsFeeding($entityManager)) {
            $output->writeln("Dashboards are being fed, aborting");
            return 0;
        }
        $this->wiilockService->toggleFeedingCommand($entityManager, true, Wiilock::DASHBOARD_FED_KEY);
        $entityManager->flush();

        $dashboardComponentRepository = $entityManager->getRepository(Dashboard\Component::class);
        $workedDaysRepository = $this->entityManager->getRepository(DaysWorked::class);
        $workFreeDaysRepository = $this->entityManager->getRepository(WorkFreeDay::class);

        $components = $dashboardComponentRepository->findAll();
        $daysWorked = $workedDaysRepository->getWorkedTimeForEachDaysWorked();
        $freeWorkDays = $workFreeDaysRepository->getWorkFreeDaysToDateTime();

        $calculateLatePack = false;

        foreach ($components as $component) {
            $componentType = $component->getType();
            $meterKey = $componentType->getMeterKey();
            switch ($meterKey) {
                case Dashboard\ComponentType::ONGOING_PACKS:
                    $this->dashboardService->persistOngoingPack($entityManager, $component);
                    break;
                case Dashboard\ComponentType::DAILY_HANDLING_INDICATOR:
                    $this->dashboardService->persistDailyHandlingIndicator($entityManager, $component);
                    break;
                case Dashboard\ComponentType::DROP_OFF_DISTRIBUTED_PACKS:
                    $this->dashboardService->persistDroppedPacks($entityManager, $component);
                    break;
                case Dashboard\ComponentType::CARRIER_TRACKING:
                    $this->dashboardService->persistCarriers($entityManager, $component);
                    break;
                case Dashboard\ComponentType::DAILY_ARRIVALS_AND_PACKS:
                case Dashboard\ComponentType::WEEKLY_ARRIVALS_AND_PACKS:
                    $this->dashboardService->persistArrivalsAndPacksMeter($entityManager, $component);
                    break;
                case Dashboard\ComponentType::ENTRIES_TO_HANDLE:
                    $this->dashboardService->persistEntriesToHandle($entityManager, $component);
                    break;
                case Dashboard\ComponentType::PACK_TO_TREAT_FROM:
                    $this->dashboardService->persistPackToTreatFrom($entityManager, $component);
                    break;
                case Dashboard\ComponentType::ARRIVALS_EMERGENCIES_TO_RECEIVE:
                case Dashboard\ComponentType::DAILY_ARRIVALS_EMERGENCIES:
                    $this->dashboardService->persistArrivalsEmergencies(
                        $entityManager,
                        $component,
                        $meterKey === Dashboard\ComponentType::DAILY_ARRIVALS_EMERGENCIES
                    );
                    break;
                case Dashboard\ComponentType::ACTIVE_REFERENCE_ALERTS:
                    $this->dashboardService->persistActiveReferenceAlerts($entityManager, $component);
                    break;
                case Dashboard\ComponentType::MONETARY_RELIABILITY_GRAPH:
                    $this->dashboardService->persistMonetaryReliabilityGraph($entityManager, $component);
                    break;
                case Dashboard\ComponentType::MONETARY_RELIABILITY_INDICATOR:
                    $this->dashboardService->persistMonetaryReliabilityIndicator($entityManager, $component);
                    break;
                case Dashboard\ComponentType::REFERENCE_RELIABILITY:
                    $this->dashboardService->persistReferenceReliability($entityManager, $component);
                    break;
                case Dashboard\ComponentType::DAILY_DISPATCHES:
                    $this->dashboardService->persistDailyDispatches($entityManager, $component);
                    break;
                case Dashboard\ComponentType::DAILY_PRODUCTION:
                    $this->dashboardService->persistDailyProductions($entityManager, $component);
                    break;
                case Dashboard\ComponentType::DAILY_HANDLING:
                case Dashboard\ComponentType::DAILY_OPERATIONS:
                    $this->dashboardService->persistDailyHandlingOrOperations($entityManager, $component);
                    break;
                case Dashboard\ComponentType::DAILY_DELIVERY_ORDERS:
                    $this->dashboardService->persistDailyDeliveryOrders($entityManager, $component);
                    break;
                case Dashboard\ComponentType::REQUESTS_TO_TREAT:
                case Dashboard\ComponentType::ORDERS_TO_TREAT:
                    $this->dashboardService->persistEntitiesToTreat($entityManager, $component, $daysWorked, $freeWorkDays);
                    break;
                case Dashboard\ComponentType::LATE_PACKS:
                    $calculateLatePack = true;
                    break;
                case Dashboard\ComponentType::HANDLING_TRACKING:
                    $this->dashboardService->persistHandlingTracking($entityManager, $component);
                    break;
                default:
                    break;
            }
        }

        if ($calculateLatePack) {
            $this->dashboardService->persistEntitiesLatePack($entityManager);
        }

        $entityManager->flush();

        $this->wiilockService->toggleFeedingCommand($entityManager, false, Wiilock::DASHBOARD_FED_KEY);
        $entityManager->flush();

        return 0;
    }

    /**
     * @return EntityManagerInterface
     * @throws ORMException
     */
    private function getEntityManager(): EntityManagerInterface {
        return $this->entityManager->isOpen()
            ? $this->entityManager
            : new EntityManager($this->entityManager->getConnection(), $this->entityManager->getConfiguration());
    }

}
