<?php
// At every 5th minute
// */5 * * * *

namespace App\Command;

use App\Entity\DaysWorked;
use App\Entity\WorkFreeDay;
use App\Service\DashboardService;
use App\Service\WiilockService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use App\Entity\Dashboard;

class DashboardFeedCommand extends Command {

    protected static $defaultName = 'app:feed:dashboards';

    private $entityManager;
    private $dashboardService;
    private $wiilockService;

    public function __construct(EntityManagerInterface $entityManager,
                                DashboardService $dashboardService,
                                WiilockService $wiilockService) {
        parent::__construct(self::$defaultName);
        $this->entityManager = $entityManager;
        $this->dashboardService = $dashboardService;
        $this->wiilockService = $wiilockService;
    }

    protected function configure() {
        $this->setDescription('This command feeds the dashboard data.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws ORMException
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $entityManager = $this->getEntityManager();
        if(!$this->wiilockService->dashboardNeedsFeeding($entityManager)) {
            $output->writeln("Dashboards are being fed, aborting");
            return 0;
        }
        $this->wiilockService->toggleFeedingDashboard($entityManager, true);
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
                case Dashboard\ComponentType::DAILY_HANDLING:
                case Dashboard\ComponentType::DAILY_OPERATIONS:
                    $this->dashboardService->persistDailyHandlingOrOperations($entityManager, $component);
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

        $this->wiilockService->toggleFeedingDashboard($entityManager, false);
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
            : EntityManager::Create($this->entityManager->getConnection(), $this->entityManager->getConfiguration());
    }

}
