<?php

namespace App\Messenger\Dashboard;

use App\Entity\Dashboard\Component;
use App\Entity\Dashboard\ComponentType;
use App\Exceptions\DashboardException;
use App\Messenger\LoggedHandler;
use App\Messenger\MessageInterface;
use App\Service\DashboardService;
use App\Service\ExceptionLoggerService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
class CalculateDashboardFeedingHandler extends LoggedHandler
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DashboardService       $dashboardService,
        private ExceptionLoggerService $loggerService,
    ) {
        parent::__construct($this->loggerService);
    }

    public function __invoke(CalculateDashboardFeedingMessage $message): void {
        $this->handle($message);
    }

    /**
     * @param CalculateDashboardFeedingMessage $message Not typed in php to implement LoggedHandler
     */
    protected function process(MessageInterface $message): void {
        $componentId = $message->getComponentId();
        $componentRepository = $this->entityManager->getRepository(Component::class);

        if($componentId) {
            $component = $componentRepository->findOneBy(["id" => $componentId]);

            $componentType = $component->getType();
            $meterKey = $componentType->getMeterKey();
            $component->setErrorMessage(null);
            try {
                switch ($meterKey) {
                    case ComponentType::ONGOING_PACKS:
                        $this->dashboardService->persistOngoingPack($this->entityManager, $component);
                        break;
                    case ComponentType::DAILY_HANDLING_INDICATOR:
                        $this->dashboardService->persistDailyHandlingIndicator($this->entityManager, $component);
                        break;
                    case ComponentType::DROP_OFF_DISTRIBUTED_PACKS:
                        $this->dashboardService->persistDroppedPacks($this->entityManager, $component);
                        break;
                    case ComponentType::CARRIER_TRACKING:
                        $this->dashboardService->persistCarriers($this->entityManager, $component);
                        break;
                    case ComponentType::DAILY_ARRIVALS_AND_PACKS:
                    case ComponentType::WEEKLY_ARRIVALS_AND_PACKS:
                        $this->dashboardService->persistArrivalsAndPacksMeter($this->entityManager, $component);
                        break;
                    case ComponentType::ENTRIES_TO_HANDLE:
                        $this->dashboardService->persistEntriesToHandle($this->entityManager, $component);
                        break;
                    case ComponentType::PACK_TO_TREAT_FROM:
                        $this->dashboardService->persistPackToTreatFrom($this->entityManager, $component);
                        break;
                    case ComponentType::ARRIVALS_EMERGENCIES_TO_RECEIVE:
                    case ComponentType::DAILY_ARRIVALS_EMERGENCIES:
                        $this->dashboardService->persistArrivalsEmergencies(
                            $this->entityManager,
                            $component,
                            $meterKey === ComponentType::DAILY_ARRIVALS_EMERGENCIES,
                            $meterKey === ComponentType::ARRIVALS_EMERGENCIES_TO_RECEIVE
                        );
                        break;
                    case ComponentType::ACTIVE_REFERENCE_ALERTS:
                        $this->dashboardService->persistActiveReferenceAlerts($this->entityManager, $component);
                        break;
                    case ComponentType::MONETARY_RELIABILITY_GRAPH:
                        $this->dashboardService->persistMonetaryReliabilityGraph($this->entityManager, $component);
                        break;
                    case ComponentType::MONETARY_RELIABILITY_INDICATOR:
                        $this->dashboardService->persistMonetaryReliabilityIndicator($this->entityManager, $component);
                        break;
                    case ComponentType::REFERENCE_RELIABILITY:
                        $this->dashboardService->persistReferenceReliability($this->entityManager, $component);
                        break;
                    case ComponentType::DAILY_DISPATCHES:
                        $this->dashboardService->persistDailyDispatches($this->entityManager, $component);
                        break;
                    case ComponentType::DAILY_PRODUCTION:
                        $this->dashboardService->persistDailyProductions($this->entityManager, $component);
                        break;
                    case ComponentType::DAILY_HANDLING:
                    case ComponentType::DAILY_OPERATIONS:
                        $this->dashboardService->persistDailyHandlingOrOperations($this->entityManager, $component);
                        break;
                    case ComponentType::DAILY_DELIVERY_ORDERS:
                        $this->dashboardService->persistDailyDeliveryOrders($this->entityManager, $component);
                        break;
                    case ComponentType::REQUESTS_TO_TREAT:
                    case ComponentType::ORDERS_TO_TREAT:
                        $this->dashboardService->persistEntitiesToTreat($this->entityManager, $component);
                        break;
                    case ComponentType::DISPUTES_TO_TREAT:
                        $this->dashboardService->persistDisputesToTreat($this->entityManager, $component);
                        break;
                    case ComponentType::HANDLING_TRACKING:
                        $this->dashboardService->persistHandlingTracking($this->entityManager, $component);
                        break;
                    default:
                        break;
                }
                $this->entityManager->flush();
            } catch (Throwable $exception) {
                if ($exception instanceof DashboardException) {
                    $component->setErrorMessage($exception->getMessage());
                } else {
                    $component->setErrorMessage("Erreur : Impossible de charger le composant");
                }
                $this->entityManager = $this->getEntityManager();
            }
            $this->entityManager->flush();
        }
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
