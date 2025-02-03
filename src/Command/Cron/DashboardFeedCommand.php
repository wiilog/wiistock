<?php
// At every 5th minute
// */5 * * * *

namespace App\Command\Cron;

use App\Entity\Dashboard;
use App\Entity\Wiilock;
use App\Exceptions\DashboardException;
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
        if(!$this->wiilockService->dashboardNeedsFeeding($this->entityManager)) {
            $output->writeln("Dashboards are being fed, aborting");
            return 0;
        }
        $this->wiilockService->toggleFeedingCommand($this->entityManager, true, Wiilock::DASHBOARD_FED_KEY);
        $this->entityManager->flush();

        $dashboardComponentRepository = $this->entityManager->getRepository(Dashboard\Component::class);

        $components = $dashboardComponentRepository->findAll();

        $latePackComponents = [];

        $calculateLatePack = false;

        foreach ($components as $component) {
            $componentType = $component->getType();
            $meterKey = $componentType->getMeterKey();
            $component->setErrorMessage(null);
            try {
                switch ($meterKey) {
                    case Dashboard\ComponentType::ONGOING_PACKS:
                        $this->dashboardService->persistOngoingPack($this->entityManager, $component);
                        break;
                    case Dashboard\ComponentType::DAILY_HANDLING_INDICATOR:
                        $this->dashboardService->persistDailyHandlingIndicator($this->entityManager, $component);
                        break;
                    case Dashboard\ComponentType::DROP_OFF_DISTRIBUTED_PACKS:
                        $this->dashboardService->persistDroppedPacks($this->entityManager, $component);
                        break;
                    case Dashboard\ComponentType::CARRIER_TRACKING:
                        $this->dashboardService->persistCarriers($this->entityManager, $component);
                        break;
                    case Dashboard\ComponentType::DAILY_ARRIVALS_AND_PACKS:
                    case Dashboard\ComponentType::WEEKLY_ARRIVALS_AND_PACKS:
                        $this->dashboardService->persistArrivalsAndPacksMeter($this->entityManager, $component);
                        break;
                    case Dashboard\ComponentType::ENTRIES_TO_HANDLE_BY_TRACKING_DELAY:
                        //TODO WIIS-12423
                    case Dashboard\ComponentType::ENTRIES_TO_HANDLE:
                        $this->dashboardService->persistEntriesToHandle($this->entityManager, $component);
                        break;
                    case Dashboard\ComponentType::PACK_TO_TREAT_FROM:
                        $this->dashboardService->persistPackToTreatFrom($this->entityManager, $component);
                        break;
                    case Dashboard\ComponentType::ARRIVALS_EMERGENCIES_TO_RECEIVE:
                    case Dashboard\ComponentType::DAILY_ARRIVALS_EMERGENCIES:
                        $this->dashboardService->persistArrivalsEmergencies(
                            $this->entityManager,
                            $component,
                            $meterKey === Dashboard\ComponentType::DAILY_ARRIVALS_EMERGENCIES,
                            $meterKey === Dashboard\ComponentType::ARRIVALS_EMERGENCIES_TO_RECEIVE
                        );
                        break;
                    case Dashboard\ComponentType::ACTIVE_REFERENCE_ALERTS:
                        $this->dashboardService->persistActiveReferenceAlerts($this->entityManager, $component);
                        break;
                    case Dashboard\ComponentType::MONETARY_RELIABILITY_GRAPH:
                        $this->dashboardService->persistMonetaryReliabilityGraph($this->entityManager, $component);
                        break;
                    case Dashboard\ComponentType::MONETARY_RELIABILITY_INDICATOR:
                        $this->dashboardService->persistMonetaryReliabilityIndicator($this->entityManager, $component);
                        break;
                    case Dashboard\ComponentType::REFERENCE_RELIABILITY:
                        $this->dashboardService->persistReferenceReliability($this->entityManager, $component);
                        break;
                    case Dashboard\ComponentType::DAILY_DISPATCHES:
                        $this->dashboardService->persistDailyDispatches($this->entityManager, $component);
                        break;
                    case Dashboard\ComponentType::DAILY_PRODUCTION:
                        $this->dashboardService->persistDailyProductions($this->entityManager, $component);
                        break;
                    case Dashboard\ComponentType::DAILY_HANDLING:
                    case Dashboard\ComponentType::DAILY_OPERATIONS:
                        $this->dashboardService->persistDailyHandlingOrOperations($this->entityManager, $component);
                        break;
                    case Dashboard\ComponentType::DAILY_DELIVERY_ORDERS:
                        $this->dashboardService->persistDailyDeliveryOrders($this->entityManager, $component);
                        break;
                    case Dashboard\ComponentType::REQUESTS_TO_TREAT:
                    case Dashboard\ComponentType::ORDERS_TO_TREAT:
                        $this->dashboardService->persistEntitiesToTreat($this->entityManager, $component);
                        break;
                    case Dashboard\ComponentType::DISPUTES_TO_TREAT:
                        $this->dashboardService->persistDisputesToTreat($this->entityManager, $component);
                        break;
                    case Dashboard\ComponentType::LATE_PACKS:
                        $calculateLatePack = true;
                        $latePackComponents[] = $component;
                        break;
                    case Dashboard\ComponentType::HANDLING_TRACKING:
                        $this->dashboardService->persistHandlingTracking($this->entityManager, $component);
                        break;
                    default:
                        break;
                }
            } catch (Throwable $exception) {
                if ($exception instanceof DashboardException) {
                    $component->setErrorMessage($exception->getMessage());
                } else {
                    $component->setErrorMessage("Erreur : Impossible de charger le composant");
                }
                $this->entityManager = $this->getEntityManager();
            }
        }

        try {
            if ($calculateLatePack) {
                $this->dashboardService->persistEntitiesLatePack($this->entityManager);
            }
        } catch (Throwable $exception) {
            foreach ($latePackComponents as $latePackComponent) {
                $latePackComponent->setErrorMessage("Erreur : Impossible de charger le composant");
            }
            $this->entityManager = $this->getEntityManager();
        }

        $this->entityManager->flush();

        $this->wiilockService->toggleFeedingCommand($this->entityManager, false, Wiilock::DASHBOARD_FED_KEY);
        $this->entityManager->flush();

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
