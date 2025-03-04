<?php

namespace App\Service\Dashboard\DashboardComponentGenerator;

use App\Entity\Collecte;
use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Dispatch;
use App\Entity\Handling;
use App\Entity\Livraison;
use App\Entity\OrdreCollecte;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\ProductionRequest;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\TransferOrder;
use App\Entity\TransferRequest;
use App\Helper\QueryBuilderHelper;
use App\Service\Dashboard\DashboardService;
use App\Service\DateTimeService;
use App\Service\EnCoursService;
use App\Service\TranslationService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class RequestsOrdersToTreatComponentGenerator implements DashboardComponentGenerator {

    public function __construct(
        private DashboardService   $dashboardService,
        private TranslationService $translationService,
        private DateTimeService    $dateTimeService,
        private EnCoursService     $enCoursService,
    ) {
    }

    public function persist(EntityManagerInterface $entityManager,
                            Dashboard\Component    $component): void {
        $config = $component->getConfig();
        $entityTypes = $config['entityTypes'];
        $entityStatuses = $config['entityStatuses'];
        $treatmentDelay = $config['treatmentDelay'];

        $entityToClass = [
            Dashboard\ComponentType::REQUESTS_TO_TREAT_HANDLING => Handling::class,
            Dashboard\ComponentType::REQUESTS_TO_TREAT_DELIVERY => Demande::class,
            Dashboard\ComponentType::REQUESTS_TO_TREAT_DISPATCH => Dispatch::class,
            Dashboard\ComponentType::REQUESTS_TO_TREAT_COLLECT => Collecte::class,
            Dashboard\ComponentType::REQUESTS_TO_TREAT_TRANSFER => TransferRequest::class,
            Dashboard\ComponentType::REQUESTS_TO_TREAT_PRODUCTION => ProductionRequest::class,
            Dashboard\ComponentType::REQUESTS_TO_TREAT_SHIPPING => ShippingRequest::class,
            Dashboard\ComponentType::ORDERS_TO_TREAT_COLLECT => OrdreCollecte::class,
            Dashboard\ComponentType::ORDERS_TO_TREAT_DELIVERY => Livraison::class,
            Dashboard\ComponentType::ORDERS_TO_TREAT_PREPARATION => Preparation::class,
            Dashboard\ComponentType::ORDERS_TO_TREAT_TRANSFER => TransferOrder::class,
        ];

        $meter = $this->dashboardService->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);
        if (isset($entityToClass[$config['entity']])) {
            $repository = $entityManager->getRepository($entityToClass[$config['entity']]);
            switch ($config['entity']) {
                case Dashboard\ComponentType::REQUESTS_TO_TREAT_HANDLING:
                case Dashboard\ComponentType::REQUESTS_TO_TREAT_DELIVERY:
                case Dashboard\ComponentType::REQUESTS_TO_TREAT_COLLECT:
                case Dashboard\ComponentType::REQUESTS_TO_TREAT_TRANSFER:
                case Dashboard\ComponentType::REQUESTS_TO_TREAT_PRODUCTION:
                    $count = QueryBuilderHelper::countByStatusesAndTypes($entityManager, $entityToClass[$config['entity']], $entityTypes, $entityStatuses);
                    break;
                case Dashboard\ComponentType::REQUESTS_TO_TREAT_DISPATCH:
                    $count = $repository->countByFilters([
                        'types' => $entityTypes,
                        'statuses' => $entityStatuses,
                        'pickLocations' => $config['pickLocations'] ?? [],
                        'dropLocations' => $config['dropLocations'] ?? [],
                        'dispatchEmergencies' => $config['dispatchEmergencies'] ?? [],
                        'nonUrgentTranslationLabel' => $this->translationService->translate('Demande', 'Général', 'Non urgent', false),
                    ]);
                    break;
                case Dashboard\ComponentType::REQUESTS_TO_TREAT_SHIPPING:
                    $result = QueryBuilderHelper::countByStatuses($entityManager, $entityToClass[$config['entity']], $entityStatuses);
                    $count = $result[0]['count'] ?? $result;
                    break;
                case Dashboard\ComponentType::ORDERS_TO_TREAT_DELIVERY:
                case Dashboard\ComponentType::ORDERS_TO_TREAT_PREPARATION:
                    $result = $repository->countByTypesAndStatuses(
                        $entityTypes,
                        $entityStatuses,
                        $config['displayDeliveryOrderContentCheckbox'] ?? null,
                        $config['displayDeliveryOrderContent'] ?? null,
                        $config['displayDeliveryOrderWithExpectedDate'] ?? null,
                    );

                    $count = $result[0]['count'] ?? $result;

                    if (isset($result[0]['sub']) && $count > 0) {
                        $meter
                            ->setSubCounts([
                                $config['displayDeliveryOrderContent'] === 'displayLogisticUnitsCount'
                                    ? '<span>Nombre d\'unités logistiques</span>'
                                    : '<span>Nombre d\'articles</span>',
                                '<span class="dashboard-stats dashboard-stats-counter">' . $result[0]['sub'] . '</span>'
                            ]);
                    }
                    else {
                        $meter
                            ->setSubCounts([]);
                    }
                    break;
                case Dashboard\ComponentType::ORDERS_TO_TREAT_COLLECT:
                case Dashboard\ComponentType::ORDERS_TO_TREAT_TRANSFER:
                    $result = $repository->countByTypesAndStatuses(
                        $entityTypes,
                        $entityStatuses,
                        $config['displayDeliveryOrderContentCheckbox'] ?? null,
                        $config['displayDeliveryOrderContent'] ?? null,
                        $config['displayDeliveryOrderWithExpectedDate'] ?? null,
                    );
                    $count = $result[0]['count'] ?? $result;

                    break;
                default:
                    break;
            }

            if (preg_match(Dashboard\ComponentType::ENTITY_TO_TREAT_REGEX_TREATMENT_DELAY, $treatmentDelay)) {
                $lastDate = $repository->getOlderDateToTreat($entityTypes, $entityStatuses, [
                    'dispatchEmergencies' => $config['dispatchEmergencies'] ?? [],
                    'nonUrgentTranslationLabel' => $this->translationService->translate('Demande', 'Général', 'Non urgent', false),
                ]);
                if (isset($lastDate)) {
                    $date = $this->dateTimeService->getWorkedPeriodBetweenDates($entityManager, $lastDate, new DateTime("now"));
                    $timeInformation = $this->enCoursService->getTimeInformation($date, $treatmentDelay);
                }
                $meter->setDelay($timeInformation['countDownLateTimespan'] ?? null);
            }
        }

        $meter->setCount($count ?? 0);
    }
}
