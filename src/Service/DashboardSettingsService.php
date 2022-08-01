<?php

namespace App\Service;

use App\Entity\Action;
use App\Entity\Alert;
use App\Entity\AverageRequestTime;
use App\Entity\Collecte;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Dispatch;
use App\Entity\Emplacement;
use App\Entity\Handling;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\TransferRequest;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use WiiCommon\Helper\Stream;
use App\Entity\Dashboard as Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\Routing\RouterInterface;

class DashboardSettingsService {

    const MODE_EDIT = 0;
    const MODE_DISPLAY = 1;
    const MODE_EXTERNAL = 2;

    const UNKNOWN_COMPONENT = 'unknown_component';
    const INVALID_SEGMENTS_ENTRY = 'invalid_segments_entry';

    /** @Required */
    public DashboardService $dashboardService;

    /** @Required */
    public DateService $dateService;

    /** @Required */
    public DemandeLivraisonService $demandeLivraisonService;

    /** @Required */
    public DemandeCollecteService $demandeCollecteService;

    /** @Required */
    public HandlingService $handlingService;

    /** @Required */
    public DispatchService $dispatchService;

    /** @Required */
    public TransferRequestService $transferRequestService;

    /** @Required */
    public UserService $userService;

    /** @Required */
    public RouterInterface $router;

    public function serialize(EntityManagerInterface $entityManager, ?Utilisateur $user, int $mode): string {
        $pageRepository = $entityManager->getRepository(Dashboard\Page::class);

        if ($mode === self::MODE_DISPLAY) {
            $pages = Stream::from($pageRepository->findAllowedToAccess($user));
        } else {
            $pages = Stream::from($pageRepository->findAll());
        }

        $pageIndex = 0;
        $dashboards = $pages->map(function(Dashboard\Page $page) use (&$pageIndex, $entityManager, $mode, $user) {
            $rowIndex = 0;
            return [
                "id" => $page->getId(),
                "name" => $page->getName(),
                "componentCount" => $page->getComponentsCount(),
                "dashboardIndex" => $pageIndex++,
                "rows" => $page->getRows()
                    ->map(function(Dashboard\PageRow $row) use (&$rowIndex, $entityManager, $mode, $user) {
                        return [
                            "id" => $row->getId(),
                            "size" => $row->getSize(),
                            "rowIndex" => $rowIndex++,
                            "components" => $row->getComponents()
                                ->map(function(Dashboard\Component $component) use ($entityManager, $mode, $user) {
                                    $type = $component->getType();
                                    $config = $component->getConfig();
                                    $meter = $component->getMeter();
                                    $meterKey = $type->getMeterKey();
                                    return [
                                        "id" => $component->getId(),
                                        "type" => $type->getId(),
                                        "columnIndex" => $component->getColumnIndex(),
                                        "direction" => $component->getDirection(),
                                        "cellIndex" => $component->getCellIndex(),
                                        "template" => $type->getTemplate(),
                                        "config" => $config,
                                        "meterKey" => $meterKey,
                                        "initData" => $this->serializeValues($entityManager, $type, $config, $mode, $mode === self::MODE_EDIT, $meter, $user),
                                    ];
                                })
                                ->getValues(),
                        ];
                    })
                    ->getValues(),
            ];
        })->toArray();

        return json_encode($dashboards);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Dashboard\ComponentType $componentType
     * @param array $config
     * @param bool $example
     * @param DashboardMeter\Chart|DashboardMeter\Indicator $meter
     * @return array
     */
    public function serializeValues(EntityManagerInterface $entityManager,
                                    Dashboard\ComponentType $componentType,
                                    array $config,
                                    ?int $mode = null,
                                    bool $example = false,
                                    $meter = null,
                                    Utilisateur $currentUser = null): array {
        $values = [];
        $meterKey = $componentType->getMeterKey();
        Stream::from($config)
            ->each(function($conf, $key) use (&$values) {
                if (str_starts_with($key, 'fontSize-')
                    || str_starts_with($key, 'textColor-')
                    || str_starts_with($key, 'textBold-')
                    || str_starts_with($key, 'textItalic-')
                    || str_starts_with($key, 'textUnderline-')) {
                    $values[$key] = $conf;
                }
            });

        $values['title'] = !empty($config['title']) ? $config['title'] : $componentType->getName();

        if (!empty($config['tooltip'])) {
            $values['tooltip'] = $config['tooltip'];
        }

        if (!empty($config['backgroundColor']) && ($componentType->getMeterKey() !== Dashboard\ComponentType::EXTERNAL_IMAGE)) {
            $values['backgroundColor'] = $config['backgroundColor'];
        }

        $redirect = $config['redirect'] ?? false;

        if (!$example && $redirect) {
            $values['componentLink'] = $this->getComponentLink($componentType, $config);
        }

        switch ($meterKey) {
            case Dashboard\ComponentType::ACTIVE_REFERENCE_ALERTS:
                $values += $this->serializeReferenceArticles($entityManager, $componentType, $example, $meter, $currentUser);
                break;
            case Dashboard\ComponentType::ONGOING_PACKS:
                $values += $this->serializeOngoingPacks($entityManager, $componentType, $config, $example, $meter);
                break;
            case Dashboard\ComponentType::DAILY_HANDLING_INDICATOR:
                $values += $this->serializeDailyHandlingIndicator($componentType, $config, $example, $meter);
                break;
            case Dashboard\ComponentType::CARRIER_TRACKING:
                $values += $this->serializeCarrierIndicator($entityManager, $componentType, $config, $example);
                break;
            case Dashboard\ComponentType::ENTRIES_TO_HANDLE:
                $values += $this->serializeEntriesToHandle($entityManager, $componentType, $config, $example, $meter);
                break;
            case Dashboard\ComponentType::WEEKLY_ARRIVALS_AND_PACKS:
            case Dashboard\ComponentType::DAILY_ARRIVALS_AND_PACKS:
                $values += $this->serializeArrivalsAndPacks($componentType, $config, $example, $meter);
                break;
            case Dashboard\ComponentType::RECEIPT_ASSOCIATION:
                $values += $this->serializeDailyReceptions($componentType, $config, $example);
                break;
            case Dashboard\ComponentType::DAILY_ARRIVALS:
                $values += $this->serializeDailyArrivals($componentType, $config, $example);
                break;
            case Dashboard\ComponentType::PENDING_REQUESTS:
                $values += $this->serializePendingRequests($entityManager, $componentType, $config, $mode);
                break;
            case Dashboard\ComponentType::DROP_OFF_DISTRIBUTED_PACKS:
            case Dashboard\ComponentType::PACK_TO_TREAT_FROM:
            case Dashboard\ComponentType::MONETARY_RELIABILITY_GRAPH:
                $values += $this->serializeSimpleChart($componentType, $example, $config, $meter);
                break;
            case Dashboard\ComponentType::DAILY_ARRIVALS_EMERGENCIES:
            case Dashboard\ComponentType::ARRIVALS_EMERGENCIES_TO_RECEIVE:
            case Dashboard\ComponentType::MONETARY_RELIABILITY_INDICATOR:
            case Dashboard\ComponentType::REFERENCE_RELIABILITY:
                $values += $this->serializeSimpleCounter($componentType, $example, $meter);
                break;
            case Dashboard\ComponentType::DAILY_DISPATCHES:
                $values += $this->serializeDailyDispatches($entityManager, $componentType, $config, $example, $meter);
                break;
            case Dashboard\ComponentType::DAILY_HANDLING:
            case Dashboard\ComponentType::DAILY_OPERATIONS:
                $values += $this->serializeDailyHandlingOrOperations($entityManager, $componentType, $config, $example, $meter);
                break;
            case Dashboard\ComponentType::REQUESTS_TO_TREAT:
            case Dashboard\ComponentType::ORDERS_TO_TREAT:
                $values += $this->serializeEntitiesToTreat($componentType, $example, $meter, $config);
                break;
            default:
                //TODO:remove
                $values += $componentType->getExampleValues();
                break;
        }

        // must be after component serialization
        if (!isset($values['chartColors']) && !empty($config['chartColors'])) {
            $values['chartColors'] = $config['chartColors'];
        }

        if (!isset($values['chartColorsLabels']) && !empty($config['chartColorsLabels'])) {
            $values['chartColorsLabels'] = $config['chartColorsLabels'];
        }
        return $values;
    }

    private function serializePendingRequests(EntityManagerInterface $entityManager,
                                              Dashboard\ComponentType $componentType,
                                              array $config,
                                              ?int $mode): array {
        if ($mode === self::MODE_EDIT) {
            $values = $componentType->getExampleValues();
        } else {
            $loggedUser = $config["shown"] === Dashboard\ComponentType::REQUESTS_SELF ? $this->userService->getUser() : null;
            $averageRequestTimeRepository = $entityManager->getRepository(AverageRequestTime::class);

            $averageRequestTimesByType = Stream::from($averageRequestTimeRepository->findAll())
                ->reduce(function(array $carry, AverageRequestTime $averageRequestTime) {
                    $typeId = $averageRequestTime->getType() ? $averageRequestTime->getType()->getId() : null;
                    if ($typeId) {
                        $carry[$typeId] = $averageRequestTime;
                    }
                    return $carry;
                }, []);

            if ($config["kind"] == "delivery" && ($mode === self::MODE_EXTERNAL || ($this->userService->getUser() && $this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_DEM_LIVR)))) {
                $demandeRepository = $entityManager->getRepository(Demande::class);
                if($config["shown"] === Dashboard\ComponentType::REQUESTS_EVERYONE || $mode !== self::MODE_EXTERNAL) {
                    $pendingDeliveries = Stream::from($demandeRepository->findRequestToTreatByUser($loggedUser, 50))
                        ->map(function(Demande $demande) use ($averageRequestTimesByType) {
                            return $this->demandeLivraisonService->parseRequestForCard($demande, $this->dateService, $averageRequestTimesByType);
                        })
                        ->toArray();
                }
            }

            if ($config["kind"] == "collect" && ($mode === self::MODE_EXTERNAL || ($this->userService->getUser() && $this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_DEM_COLL)))) {
                $collecteRepository = $entityManager->getRepository(Collecte::class);
                if($config["shown"] === Dashboard\ComponentType::REQUESTS_EVERYONE || $mode !== self::MODE_EXTERNAL) {
                    $backgroundColor = $config['backgroundColor'] ?? '';
                    $pendingCollects = Stream::from($collecteRepository->findRequestToTreatByUser($loggedUser, 50))
                        ->map(function(Collecte $collecte) use ($averageRequestTimesByType, $backgroundColor) {
                            return $this->demandeCollecteService->parseRequestForCard($collecte, $this->dateService, $averageRequestTimesByType, $backgroundColor);
                        })
                        ->toArray();
                }
            }

            if ($config["kind"] == "handling" && ($mode === self::MODE_EXTERNAL || ($this->userService->getUser() && $this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_HAND)))) {
                $handlingRepository = $entityManager->getRepository(Handling::class);
                if($config["shown"] === Dashboard\ComponentType::REQUESTS_EVERYONE || $mode !== self::MODE_EXTERNAL) {
                    $pendingHandlings = Stream::from($handlingRepository->findRequestToTreatByUser($loggedUser, 50))
                        ->map(function(Handling $handling) use ($averageRequestTimesByType) {
                            return $this->handlingService->parseRequestForCard($handling, $this->dateService, $averageRequestTimesByType);
                        })
                        ->toArray();
                }
            }

            if ($config["kind"] == "transfer" && ($mode === self::MODE_EXTERNAL || ($this->userService->getUser() && $this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_TRANSFER_REQ)))) {
                $transferRequestRepository = $entityManager->getRepository(TransferRequest::class);
                if($config["shown"] === Dashboard\ComponentType::REQUESTS_EVERYONE || $mode !== self::MODE_EXTERNAL) {
                    $pendingTransfers = Stream::from($transferRequestRepository->findRequestToTreatByUser($loggedUser, 50))
                        ->map(function(TransferRequest $transfer) use ($averageRequestTimesByType) {
                            return $this->transferRequestService->parseRequestForCard($transfer, $this->dateService, $averageRequestTimesByType);
                        })
                        ->toArray();
                }
            }
            if ($config["kind"] == "dispatch" && ($mode === self::MODE_EXTERNAL || ($this->userService->getUser() && $this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_ACHE)))) {
                $dispatchRepository = $entityManager->getRepository(Dispatch::class);
                if($config["shown"] === Dashboard\ComponentType::REQUESTS_EVERYONE || $mode !== self::MODE_EXTERNAL) {
                    $pendingDispatches = Stream::from($dispatchRepository->findRequestToTreatByUser($loggedUser, 50))
                        ->map(function(Dispatch $dispatch) use ($averageRequestTimesByType) {
                            return $this->dispatchService->parseRequestForCard($dispatch, $this->dateService, $averageRequestTimesByType);
                        })
                        ->toArray();
                }
            }

            $values["requests"] = array_merge($pendingDeliveries ?? [], $pendingCollects ?? [], $pendingHandlings ?? [], $pendingTransfers ?? [], $pendingDispatches ?? []);
        }

        if(isset($config['cardBackgroundColor']) && $config['cardBackgroundColor'] !== '#ffffff') {
            foreach ($values["requests"] ?? [] as $key => $request) {
                $values["requests"][$key]['cardBackgroundColor'] = $config['cardBackgroundColor'];
            }
        }

        return $values;
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Dashboard\ComponentType $componentType
     * @param array $config
     * @param bool $example
     * @param DashboardMeter\Chart|null $meterChart
     * @return array
     */
    private function serializeEntriesToHandle(EntityManagerInterface $entityManager,
                                              Dashboard\ComponentType $componentType,
                                              array $config,
                                              bool $example = false,
                                              DashboardMeter\Chart $meterChart = null): array {

        if ($example) {
            $values = $componentType->getExampleValues();
            $values['linesCountTooltip'] = !empty($config['linesCountTooltip']) ? $config['linesCountTooltip'] : '';
            $values['nextLocationTooltip'] = !empty($config['nextLocationTooltip']) ? $config['linesCountTooltip'] : '';

            if (!empty($config['natures'])) {
                $natureRepository = $entityManager->getRepository(Nature::class);
                $natures = $natureRepository->findBy(['id' => $config['natures']]);
                $generated = Stream::from($natures)
                    ->reduce(function(array $carry, Nature $nature) {
                        $carry['chartColors'][$nature->getLabel()] = $nature->getColor();
                        $carry['defaultCounters'][$nature->getLabel()] = random_int(0, 30);
                        return $carry;
                    }, ['chartColors' => [], 'defaultCounters' => []]);
                $values['chartColors'] = $generated['chartColors'];
                $defaultCounters = $generated['defaultCounters'];
            } else {
                $defaultCounters = [
                    'Standard' => 15,
                    'Consommable' => 2,
                    'Congelé' => 12
                ];
            }

            $segments = $config['segments'] ?? [];
            if (!empty($segments)) {
                $segmentsLabels = [
                    'Retard',
                    'Moins d\'1h'
                ];
                $lastKey = "1";
                foreach ($segments as $segment) {
                    $segmentsLabels[] = "${lastKey}h - ${segment}h";
                    $lastKey = $segment;
                }
            } else {
                $segmentsLabels = array_keys($values['chartData'] ?? []);
            }
            $values['chartData'] = Stream::from($segmentsLabels)
                ->reduce(function(array $carry, string $segmentLabel) use ($defaultCounters) {
                    $carry[$segmentLabel] = $defaultCounters;
                    return $carry;
                }, []);
        } else if (isset($meterChart)) {
            $values = [
                'chartData' => $meterChart->getData(),
                'nextLocation' => $meterChart->getLocation(),
                'count' => $meterChart->getTotal(),
                'chartColors' => $meterChart->getChartColors(),
                'linesCountTooltip' => $config['linesCountTooltip'] ?? '',
                'nextLocationTooltip' => $config['nextLocationTooltip'] ?? '',
            ];
        } else {
            $values = [
                'chartData' => [],
                'nextLocation' => '-',
                'count' => '-',
                'chartColors' => []
            ];
        }

        $values['linesCountTooltip'] = $config['linesCountTooltip'] ?? '';
        $values['nextLocationTooltip'] = $config['nextLocationTooltip'] ?? '';

        return $values;
    }

    /**
     * @param Dashboard\ComponentType $componentType
     * @param array $config
     * @param bool $example
     * @return array
     */
    private function serializeDailyArrivals(Dashboard\ComponentType $componentType,
                                            array $config,
                                            bool $example = false): array {
        $values = $componentType->getExampleValues();
        if (!$example) {
            $chartValues = $this->dashboardService->getWeekArrival(
                $config['firstDay'] ?? date("d/m/Y", strtotime('monday this week')),
                $config['lastDay'] ?? date("d/m/Y", strtotime('sunday this week')),
                $config['beforeAfter'] ?? 'now'
            );
            $chartData = Stream::from($chartValues['data'])
                ->map(function(array $value) {
                    return $value['count'];
                })->toArray();
            $values['chartData'] = $chartData;
            unset($chartValues['data']);
            $values += $chartValues;
        }

        $values['chartColors'] = $config['chartColors'] ?? $values['chartColors'] ?? [];

        return $values;
    }

    private function serializeDailyHandlingIndicator(Dashboard\ComponentType $componentType,
                                                     array $config,
                                                     bool $example = false,
                                                     DashboardMeter\Indicator $meter = null): array {
        $shouldShowOperations = isset($config['displayOperations']) && $config['displayOperations'];
        $shouldShowEmergencies = isset($config['displayEmergency']) && $config['displayEmergency'];
        $shouldRedirectToHandling = isset($config['redirectToHandling']) && $config['redirectToHandling'];
        if ($example) {
            $values = $componentType->getExampleValues();
        } else {
            if ($meter) {
                $values = [
                    'subCounts' => $meter->getSubCounts(),
                    'delay' => $meter->getDelay(),
                    'count' => $meter->getCount(),
                ];
            } else {
                $values = [
                    'subtitle' => '-',
                    'subCounts' => [
                        '<span class="text-wii-green">-</span> <span class="text-wii-black">lignes</span>',
                        '<span class="text-wii-black">Dont</span> <span class="text-wii-red">-</span> <span class="text-wii-black">urgences</span>'
                    ],
                    'count' => '-',
                ];
            }
        }
        if (!$shouldShowOperations && isset($values['subCounts'][0])) {
            unset($values['subCounts'][0]);
        }
        if (!$shouldShowEmergencies && isset($values['subCounts'][1])) {
            unset($values['subCounts'][1]);
        }
        if (!$shouldRedirectToHandling && isset($values['subCounts'][2])) {
            unset($values['subCounts'][2]);
        }

        $values['subCounts'] = array_values($values['subCounts']);

        return $values;
    }

    private function serializeOngoingPacks(EntityManagerInterface $manager,
                                           Dashboard\ComponentType $componentType,
                                           array $config,
                                           bool $example = false,
                                           DashboardMeter\Indicator $meter = null): array {
        $shouldShowTreatmentDelay = isset($config['withTreatmentDelay']) && $config['withTreatmentDelay'];
        $shouldShowLocationLabels = isset($config['withLocationLabels']) && $config['withLocationLabels'];
        $emergency = isset($config['emergency']) && $config['emergency'];
        if ($example) {
            $values = $componentType->getExampleValues();
            $values['logoURL'] = $config['logoURL'] ?? null;
            $values['titleComponentLogo'] = $config['titleComponentLogo'] ?? null;
            if ($shouldShowLocationLabels && !empty($config['locations'])) {
                $locationRepository = $manager->getRepository(Emplacement::class);
                $locations = $locationRepository->findBy(['id' => $config['locations']]);
                $values['subtitle'] = FormatHelper::locations($locations);
            }
        } else {
            if ($meter) {
                $values = [
                    'subtitle' => $meter->getSubtitle(),
                    'delay' => $meter->getDelay(),
                    'count' => $meter->getCount(),
                ];
            } else {
                $values = [
                    'subtitle' => '-',
                    'delay' => '-',
                    'count' => '-',
                ];
            }
        }
        $values['emergency'] = $emergency;
        $values['logoURL'] = $config['logoURL'] ?? null;
        $values['titleComponentLogo'] = $config['titleComponentLogo'] ?? null;
        if (!$shouldShowLocationLabels) {
            unset($values['subtitle']);
        } else if (empty($values['subtitle'])) {
            $values['subtitle'] = '-';
        }

        if (!$shouldShowTreatmentDelay) {
            unset($values['delay']);
        } else if (empty($values['delay'])) {
            $values['delay'] = '-';
        }

        return $values;
    }

    private function serializeReferenceArticles(EntityManagerInterface $manager,
                                                Dashboard\ComponentType $componentType,
                                                bool $example,
                                                ?DashboardMeter\Indicator $meter,
                                                ?Utilisateur $utilisateur): array
    {
        $alertRepository = $manager->getRepository(Alert::class);

        if ($example) {
            $values = $componentType->getExampleValues();
        } else {
            $count = $meter
                ? $alertRepository->countAllActiveByParams(array_merge(
                    $meter->getComponent()->getConfig(),
                    ['user' => $utilisateur]
                ))
                : 0;
            $values = [
                'count' => $count ?? 0
            ];
        }

        return $values;
    }

    private function serializeCarrierIndicator(EntityManagerInterface $manager,
                                               Dashboard\ComponentType $componentType,
                                               array $config,
                                               bool $example = false): array {
        $values = [];
        $carrierRepository = $manager->getRepository(Transporteur::class);
        if (!empty($config["carriers"])) {

            if ($example) {
                $carriers = $carrierRepository->findBy(['id' => $config['carriers']]);
            } else {
                $carriers = $carrierRepository->getDailyArrivalCarriersLabel($config['carriers']);
            }

            $values["carriers"] = FormatHelper::carriers($carriers);
        } else if ($example) {
            $values = $componentType->getExampleValues();
        } else {
            $carriers = $carrierRepository->getDailyArrivalCarriersLabel();
            $values["carriers"] = FormatHelper::carriers($carriers);
        }

        return $values;
    }

    public function serializeArrivalsAndPacks(Dashboard\ComponentType $componentType,
                                              array $config,
                                              bool $example = false,
                                              DashboardMeter\Chart $meterChart = null): array {
        $values = $example ? $componentType->getExampleValues() : [];

        if (!empty($config['chartColors'])) {
            $exampleChartColors = $values['chartColors'] ?? null;
            $values['chartColors'] = $config['chartColors'];
            if (isset($exampleChartColors)) {
                foreach ($exampleChartColors as $index => $color) {
                    if (!isset($values['chartColors'][$index])) {
                        $values['chartColors'][$index] = $color;
                    }
                }
            }
        }

        $displayPackNatures = $config['displayPackNatures'] ?? false;

        $values['stack'] = true;
        $values['label'] = 'Arrivages';

        $dailyRequest = ($componentType->getMeterKey() === Dashboard\ComponentType::DAILY_ARRIVALS_AND_PACKS);
        if ($dailyRequest) {
            $scale = $config['daysNumber'] ?? DashboardService::DEFAULT_DAILY_REQUESTS_SCALE;
        } else {
            $scale = DashboardService::DEFAULT_WEEKLY_REQUESTS_SCALE;
        }

        // arrivals column
        if (!$example && isset($meterChart)) {
            $values['chartData'] = $meterChart->getData();
        } else {
            $chartData = $values['chartData'] ?? [];
            $keysToKeep = array_slice(array_keys($chartData), 0, $scale);
            $keysToKeep[] = 'stack';
            $chartData = Stream::from($keysToKeep)
                ->reduce(function(array $carry, string $key) use ($chartData) {
                    if (isset($chartData[$key])) {
                        $carry[$key] = $chartData[$key];
                    }
                    return $carry;
                }, []);

            $values['colorsFilled'] = $displayPackNatures;
            // packs column
            if (isset($chartData['stack'])) {
                if ($scale) {
                    if (!$displayPackNatures) {
                        $chartData['stack'] = array_slice($chartData['stack'], 0, 1);
                        $chartData['stack'][0] = [
                            'label' => 'Colis',
                            'backgroundColor' => '#E5E1E1',
                            'stack' => 'stack',
                            'data' => $chartData['stack'][0]['data']
                        ];
                    }
                    foreach ($chartData['stack'] as $natureData) {
                        $natureData['data'] = array_slice($natureData['data'], 0, $scale);
                    }
                } else if (isset($chartData['stack'])) {
                    unset($chartData['stack']);
                }
            }

            if ($values['colorsFilled']
                && is_array($values['chartColors'])
                && !empty($values['chartColors'])) {
                $countChartColors = count($values['chartColors']);
                if ($countChartColors > 1) {
                    unset($values['chartColors'][$countChartColors - 1]);
                }
            }

            $values['chartData'] = $chartData;
        }
        return $values;
    }

    private function serializeSimpleChart(Dashboard\ComponentType $componentType,
                                          bool $example = false,
                                          $config = [],
                                          ?DashboardMeter\Chart $chart = null): array {

        if (!$example) {
            if ($chart) {
                return [
                    "chartData" => $chart->getData(),
                    "chartColors" => $chart->getChartColors()
                ];
            } else {
                return ["chartData" => []];
            }
        } else {
            $values = $componentType->getExampleValues();
            if ($componentType->getMeterKey() === Dashboard\ComponentType::PACK_TO_TREAT_FROM && isset($config['chartColors'])) {
                $values['originCaption'] = $config['originCaption'] ?? 'Legende1';
                $values['destinationCaption'] = $config['destinationCaption'] ?? 'Legende2';
                $values['chartColors'] = [
                    'Legende1' => $config['chartColors']['Legende1'] ?? '',
                    'Legende2' => $config['chartColors']['Legende2'] ?? ''
                ];
            } else {
                $values['chartColors'] = $config['chartColors'] ?? $values['chartColors'] ?? [];
            }
            return $values;
        }
    }

    private function serializeDailyReceptions(Dashboard\ComponentType $componentType,
                                              array $config,
                                              bool $example = false): array {

        $values = $componentType->getExampleValues();
        if (!$example) {
            $chartValues = $this->dashboardService->getWeekAssoc(
                $config['firstDay'] ?? date("d/m/Y", strtotime('monday this week')),
                $config['lastDay'] ?? date("d/m/Y", strtotime('sunday this week')),
                $config['beforeAfter'] ?? 'now'
            );

            $values['chartData'] = $chartValues['data'];

            unset($chartValues['data']);
            $values += $chartValues;
        }

        $values['chartColors'] = $config['chartColors'] ?? $values['chartColors'] ?? [];

        return $values;
    }

    public function serializeSimpleCounter(Dashboard\ComponentType $componentType,
                                           bool $example = false,
                                           DashboardMeter\Indicator $meter = null) {
        if ($example) {
            $values = $componentType->getExampleValues();
        } else {
            $values = [
                'count' => $meter
                    ? $meter->getCount()
                    : '-'
            ];
        }

        return $values;
    }

    /**
     * @param Dashboard\ComponentType $componentType
     * @param array $config
     * @param bool $example
     * @param DashboardMeter\Chart|null $chart
     * @return array
     */
    private function serializeDailyHandlingOrOperations(EntityManagerInterface $entityManager,
                                                        Dashboard\ComponentType $componentType,
                                                        array $config,
                                                        bool $example = false,
                                                        DashboardMeter\Chart $chart = null): array {
        $separateType = isset($config['separateType']) && $config['separateType'];
        if (!$example) {
            if ($chart) {
                $values = ["chartData" => $chart->getData(), 'chartColors' => $chart->getChartColors()];
            } else {
                $values = ["chartData" => []];
            }
        } else {
            $values = $componentType->getExampleValues();
            $values['separateType'] = $config['separateType'] ?? false;
            $values['handlingTypes'] = $config['handlingTypes'] ?? '';
            if (!empty($config['handlingTypes']) && $separateType) {
                $handlingTypes = $entityManager->getRepository(Type::class)->findBy(['id' => $config['handlingTypes']]);
                $counter = 0;
                $chartColors = Stream::from($handlingTypes)
                    ->reduce(function (array $carry, Type $type) use ($config, &$counter, $values) {
                        $carry[$type->getLabel()] = $config['chartColors'][$type->getLabel()] ?? Dashboard\ComponentType::DEFAULT_CHART_COLOR;
                        $counter++;
                        return $carry;
                    }, []);
                $values['chartColors'] = $chartColors;

                $chartColorsLabels = Stream::from($handlingTypes)
                    ->map(function (Type $type) {
                        return $type->getLabel();
                    })->toArray();
                $values['chartColorsLabels'] = $chartColorsLabels;

                $chartValues = Stream::from($handlingTypes)
                    ->reduce(function (array $carry, Type $type) {
                        $carry[$type->getLabel()] = rand(10, 18);
                        return $carry;
                    }, []);

                $chartDataMultiple = Stream::from($values['chartDataMultiple'])
                    ->map(function () use ($chartValues) {
                        return $chartValues;
                    })->toArray();
                $values['chartDataMultiple'] = $chartDataMultiple;
            } else {
                $values['chartColors'] = (isset($config['chartColors']) && isset($config['chartColors'][0]))
                    ? [$config['chartColors'][0]]
                    : [Dashboard\ComponentType::DEFAULT_CHART_COLOR];
            }

            $scale = $config['daysNumber'] ?? DashboardService::DEFAULT_WEEKLY_REQUESTS_SCALE;
            $chartData = $separateType ? ($values['chartDataMultiple'] ?? []) : ($values['chartData'] ?? []);
            $keysToKeep = array_slice(array_keys($chartData), 0, $scale);
            $chartData = Stream::from($keysToKeep)
                ->reduce(function(array $carry, string $key) use ($chartData) {
                    if (isset($chartData[$key])) {
                        $carry[$key] = $chartData[$key];
                    }
                    return $carry;
                }, []);
            $values['chartData'] = $chartData;
        }
        $values['multiple'] = $separateType;
        return $values;
    }

    /**
     * @param Dashboard\ComponentType $componentType
     * @param array $config
     * @param bool $example
     * @param DashboardMeter\Chart|null $chart
     * @return array
     */
    private function serializeDailyDispatches(EntityManagerInterface $entityManager,
                                              Dashboard\ComponentType $componentType,
                                              array $config,
                                              bool $example = false,
                                              DashboardMeter\Chart $chart = null): array {
        $separateType = isset($config['separateType']) && $config['separateType'];
        $stackValues = isset($config['stackValues']) && $config['stackValues'];
        if (!$example) {
            if ($chart) {
                $values = ["chartData" => $chart->getData(), 'chartColors' => $chart->getChartColors()];
            } else {
                $values = ["chartData" => []];
            }
        } else {
            $values = $componentType->getExampleValues();
            $values['separateType'] = $config['separateType'] ?? false;
            $values['dispatchTypes'] = $config['dispatchTypes'] ?? '';
            if (!empty($config['dispatchTypes']) && $separateType) {
                $dispatchTypes = $entityManager->getRepository(Type::class)->findBy(['id' => $config['dispatchTypes']]);
                $counter = 0;
                $chartColors = Stream::from($dispatchTypes)
                    ->reduce(function (array $carry, Type $type) use ($config, &$counter, $values) {
                        srand($type->getId());
                        $carry[$type->getLabel()] = $config['chartColors'][$type->getLabel()] ?? sprintf('#%06X', mt_rand(0, 0xFFFFFF));
                        $counter++;
                        return $carry;
                    }, []);

                srand();
                $values['chartColors'] = $chartColors;

                $chartColorsLabels = Stream::from($dispatchTypes)
                    ->map(function (Type $type) {
                        return $type->getLabel();
                    })->toArray();
                $values['chartColorsLabels'] = $chartColorsLabels;

                $chartValues = Stream::from($dispatchTypes)
                    ->reduce(function (array $carry, Type $type) {
                        $carry[$type->getLabel()] = rand(10, 18);
                        return $carry;
                    }, []);

                $chartDataMultiple = Stream::from($values['chartDataMultiple'])
                    ->map(function () use ($chartValues) {
                        return $chartValues;
                    })->toArray();
                $values['chartDataMultiple'] = $chartDataMultiple;
            } else {
                $values['chartColors'] = (isset($config['chartColors']) && isset($config['chartColors'][0]))
                    ? [$config['chartColors'][0]]
                    : [Dashboard\ComponentType::DEFAULT_CHART_COLOR];
            }

            $scale = $config['scale'] ?? DashboardService::DEFAULT_WEEKLY_REQUESTS_SCALE;

            $chartData = $separateType ? ($values['chartDataMultiple'] ?? []) : ($values['chartData'] ?? []);
            $keysToKeep = array_slice(array_keys($chartData), 0, $scale);
            $chartData = Stream::from($keysToKeep)
                ->reduce(function(array $carry, string $key) use ($chartData) {
                    if (isset($chartData[$key])) {
                        $carry[$key] = $chartData[$key];
                    }
                    return $carry;
                }, []);

            $values['chartColors'] = $values['chartColors'] ?? $config['chartColors'] ?? [];

            $values['chartData'] = $chartData;

            $values['date'] = $config['date'] ?? "";
        }
        $values['multiple'] = $separateType;
        $values['stackValues'] = $stackValues;
        return $values;
    }

    /**
     * @param Dashboard\ComponentType $componentType
     * @param bool $example
     * @param DashboardMeter\Indicator|null $meter
     * @param $config
     * @return array
     */
    public function serializeEntitiesToTreat(Dashboard\ComponentType $componentType,
                                             bool $example = false,
                                             DashboardMeter\Indicator $meter = null,
                                             $config = []): array {
        if ($example) {
            $values = $componentType->getExampleValues();

            $convertedDelay = null;
            if(isset($config['treatmentDelay'])
                && preg_match(Dashboard\ComponentType::ENTITY_TO_TREAT_REGEX_TREATMENT_DELAY, $config['treatmentDelay'])) {
                $treatmentDelay = explode(':', $config['treatmentDelay']);
                $convertedDelay = ($treatmentDelay[0] * 60 * 60 * 1000) + ($treatmentDelay[1] * 60 * 1000);
            }

            $values['delay'] = $convertedDelay;
        } else {
            $values = [
                'count' => $meter ? $meter->getCount() : '-',
                'delay' => $meter ? $meter->getDelay() : '-'
            ];
        }

        if (empty($config['treatmentDelay']) && isset($values['delay'])) {
            unset($values['delay']);
        }

        return $values;
    }

    public function save(EntityManagerInterface $entityManager, array $jsonDashboard) {
        $componentTypeRepository = $entityManager->getRepository(Dashboard\ComponentType::class);
        $pageRepository = $entityManager->getRepository(Dashboard\Page::class);
        $pageRowRepository = $entityManager->getRepository(Dashboard\PageRow::class);
        $componentRepository = $entityManager->getRepository(Dashboard\Component::class);

        $pagesToDelete = $this->byId($pageRepository->findAll());
        $pageRowsToDelete = $this->byId($pageRowRepository->findAll());
        $componentsToDelete = $this->byId($componentRepository->findAll());
        foreach ($jsonDashboard as $jsonPage) {
            [$updatePage, $page] = $this->getEntity($entityManager, Dashboard\Page::class, $jsonPage);
            if ($page) {
                if ($updatePage) {
                    $page->setName($jsonPage["name"]);
                    $page->setComponentsCount(isset($jsonPage["componentCount"]) ? intval($jsonPage["componentCount"]) : null);

                    foreach ($jsonPage["rows"] as $jsonRow) {
                        [$updateRow, $row] = $this->getEntity($entityManager, Dashboard\PageRow::class, $jsonRow);
                        if ($row) {
                            if ($updateRow) {
                                $row->setPage($page);
                                $row->setSize($jsonRow["size"]);

                                foreach ($jsonRow["components"] as $jsonComponent) {
                                    [$updateComponent, $component] = $this->getEntity($entityManager, Dashboard\Component::class, $jsonComponent);
                                    if ($updateComponent && $component) {
                                        $type = $componentTypeRepository->find($jsonComponent["type"]);
                                        if (!$type) {
                                            throw new InvalidArgumentException(self::UNKNOWN_COMPONENT . '-' . $jsonComponent["type"]);
                                        }
                                        $component->setType($type);
                                        $component->setRow($row);
                                        $component->setColumnIndex($jsonComponent["columnIndex"]);
                                        $component->setDirection($jsonComponent["direction"] !== "" ? $jsonComponent["direction"] : null);
                                        $component->setCellIndex($jsonComponent["cellIndex"] ?? null);
                                        $this->validateComponentConfig($type, $jsonComponent["config"]);
                                        $component->setConfig($jsonComponent["config"]);
                                    }

                                    if (isset($jsonComponent["id"], $componentsToDelete[$jsonComponent["id"]])) {
                                        unset($componentsToDelete[$jsonComponent["id"]]);
                                    }
                                }
                            } else {
                                $this->ignoreRow($row, $componentsToDelete);
                            }
                        }

                        if (isset($jsonRow["id"], $pageRowsToDelete[$jsonRow["id"]])) {
                            unset($pageRowsToDelete[$jsonRow["id"]]);
                        }
                    }
                } else {
                    $this->ignorePage($page, $pageRowsToDelete, $componentsToDelete);
                }
            }

            if (isset($jsonPage["id"], $pagesToDelete[$jsonPage["id"]])) {
                unset($pagesToDelete[$jsonPage["id"]]);
            }
        }
        Stream::from($pagesToDelete, $pageRowsToDelete, $componentsToDelete)
            ->each(function($entity) use ($entityManager) {
                $entityManager->remove($entity);
            });
    }

    /**
     * @param Dashboard\ComponentType $componentType
     * @param array $config
     */
    private function validateComponentConfig(Dashboard\ComponentType $componentType,
                                             array $config) {
        if ($componentType->getMeterKey() === Dashboard\ComponentType::ENTRIES_TO_HANDLE) {
            $errorMessage = self::INVALID_SEGMENTS_ENTRY . '-' . $config['title'];
            if (empty($config['segments']) || count($config['segments']) < 1) {
                throw new InvalidArgumentException($errorMessage);
            } else {
                $previousSegment = 0;
                foreach ($config['segments'] as $segment) {
                    if ($previousSegment > $segment) {
                        throw new InvalidArgumentException($errorMessage);
                    } else {
                        $previousSegment = $segment;
                    }
                }
            }
        }
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param string $class
     * @param array|null $json
     * @return array
     */
    private function getEntity(EntityManagerInterface $entityManager,
                               string $class,
                               ?array $json): array {
        $set = $json["updated"] ?? false;
        if (!$json) {
            return [false, null];
        }

        if (isset($json["id"])) {
            $entity = $entityManager->find($class, $json["id"]);
        }

        if (!isset($entity)) {
            $set = true;
            $entity = new $class();
            if ($entity instanceof Dashboard\Page) {
                $menu = $entityManager->getRepository(Menu::class)
                    ->findOneBy(["label" => Menu::DASHBOARDS]);

                $action = new Action();
                $action->setMenu($menu);

                $entity->setAction($action);
            }

            $entityManager->persist($entity);
        }

        return [$set, $entity ?? null];
    }

    private function byId($elements): array {
        return Stream::from($elements)
            ->keymap(function($element) {
                return [$element->getId(), $element];
            })
            ->toArray();
    }

    private function ignorePage(Dashboard\Page $page,
                                array &$pageRowsToDelete,
                                array &$componentsToDelete) {
        foreach ($page->getRows() as $row) {
            $rowId = $row->getId();
            if (isset($pageRowsToDelete[$rowId])) {
                unset($pageRowsToDelete[$rowId]);
            }
            $this->ignoreRow($row, $componentsToDelete);
        }
    }

    private function ignoreRow(Dashboard\PageRow $component,
                               array &$componentsToDelete) {

        foreach ($component->getComponents() as $component) {
            $componentId = $component->getId();
            if (isset($componentsToDelete[$componentId])) {
                unset($componentsToDelete[$componentId]);
            }
        }
    }

    public function getComponentLink(Dashboard\ComponentType $componentType,
                                     array $config) {
        $meterKey = $componentType->getMeterKey();
        switch ($meterKey) {
            case Dashboard\ComponentType::ACTIVE_REFERENCE_ALERTS:
                $managers = $config['managers'] ?? [];
                $types = $config['referenceTypes'] ?? [];
                $link = $this->router->generate('alerte_index', [
                    'managers' => implode(',', $managers),
                    'referenceTypes' => implode(',', $types)
                ]);
                break;
            case Dashboard\ComponentType::ENTRIES_TO_HANDLE:
            case Dashboard\ComponentType::ONGOING_PACKS:
                $locations = $config['locations'] ?? [];
                $redirect = $config['redirect'] ?? false;
                $link = !empty($locations) && $redirect
                    ? $this->router->generate('en_cours', ['locations' => implode(',', $locations), 'fromDashboard' => true ])
                    : null;
                break;
            default:
                $link = null;
                break;
        }

        return $link;
    }
}
