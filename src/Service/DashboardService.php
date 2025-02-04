<?php

namespace App\Service;

use App\Entity\Alert;
use App\Entity\Arrivage;
use App\Entity\ArrivalHistory;
use App\Entity\Article;
use App\Entity\Collecte;
use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Dispatch;
use App\Entity\Dispute;
use App\Entity\Emplacement;
use App\Entity\Handling;
use App\Entity\Language;
use App\Entity\LatePack;
use App\Entity\Livraison;
use App\Entity\LocationCluster;
use App\Entity\LocationClusterMeter;
use App\Entity\MouvementStock;
use App\Entity\Nature;
use App\Entity\OrdreCollecte;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\ProductionRequest;
use App\Entity\ReceiptAssociation;
use App\Entity\ReferenceArticle;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingDelay;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\TransferOrder;
use App\Entity\TransferRequest;
use App\Entity\Transporteur;
use App\Entity\TruckArrivalLine;
use App\Entity\Type;
use App\Entity\Urgence;
use App\Entity\Wiilock;
use App\Exceptions\DashboardException;
use App\Helper\FormatHelper;
use App\Helper\LanguageHelper;
use App\Helper\QueryBuilderHelper;
use App\Service\TrackingDelayService;
use App\Service\WorkPeriod\WorkPeriodItem;
use App\Service\WorkPeriod\WorkPeriodService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use InvalidArgumentException;
use WiiCommon\Helper\Stream;

class DashboardService {

    public const DEFAULT_DAILY_REQUESTS_SCALE = 5;
    public const DEFAULT_HANDLING_TRACKING_SCALE = 7;
    public const DEFAULT_WEEKLY_REQUESTS_SCALE = 7;

    public const DAILY_PERIOD_NEXT_DAYS = 'nextDays';
    public const DAILY_PERIOD_PREVIOUS_DAYS = 'previousDays';

    public function __construct(
        private WorkPeriodService       $workPeriodService,
        private DateTimeService         $dateTimeService,
        private TranslationService      $translationService,
        private LanguageService         $languageService,
        private EntityManagerInterface  $entityManager,
        private TruckArrivalLineService $truckArrivalLineService,
        private FormatService           $formatService,
        private EnCoursService          $enCoursService,
        private PackService             $packService,
    ) {}

    public function refreshDate(EntityManagerInterface $entityManager): string {
        $wiilockRepository = $entityManager->getRepository(Wiilock::class);
        $lock = $wiilockRepository->findOneBy(["lockKey" => Wiilock::DASHBOARD_FED_KEY]);

        return $lock
            ? FormatHelper::datetime($lock->getUpdateDate())
            : "(date inconnue)";
    }

    public function getWeekAssoc($firstDay, $lastDay, $beforeAfter) {
        $receiptAssociationRepository = $this->entityManager->getRepository(ReceiptAssociation::class);

        if ($beforeAfter == 'after') {
            $firstDay = date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' +7 days'));
            $lastDay = date("d/m/Y", strtotime(str_replace("/", "-", $lastDay) . ' +7 days'));
        } elseif ($beforeAfter == 'before') {
            $firstDay = date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' -7 days'));
            $lastDay = date("d/m/Y", strtotime(str_replace("/", "-", $lastDay) . ' -7 days'));
        }
        $firstDayTime = strtotime(str_replace("/", "-", $firstDay));
        $lastDayTime = strtotime(str_replace("/", "-", $lastDay));

        $rows = [];
        $secondInADay = 60 * 60 * 24;

        $keyFormat = 'd';
        $counterKeyToString = function($key) {
            return ' ' . $key . ' ';
        };

        for ($dayIncrement = 0; $dayIncrement < 7; $dayIncrement++) {
            $dayCounterKey = $counterKeyToString(date($keyFormat, $firstDayTime + ($secondInADay * $dayIncrement)));
            $rows[$dayCounterKey] = 0;
        }

        $receiptAssociations = $receiptAssociationRepository->countByDays($firstDay, $lastDay);
        foreach ($receiptAssociations as $qttPerDay) {
            $dayCounterKey = $counterKeyToString($qttPerDay['date']->format($keyFormat));
            $rows[$dayCounterKey] += $qttPerDay['count'];
        }
        return [
            'data' => $rows,
            'firstDay' => date("d/m/y", $firstDayTime),
            'firstDayData' => date("d/m/Y", $firstDayTime),
            'lastDay' => date("d/m/y", $lastDayTime),
            'lastDayData' => date("d/m/Y", $lastDayTime)
        ];
    }

    public function getWeekArrival($firstDay, $lastDay, $beforeAfter) {
        $arrivalHistoryRepository = $this->entityManager->getRepository(ArrivalHistory::class);
        $arrivageRepository = $this->entityManager->getRepository(Arrivage::class);

        if ($beforeAfter == 'after') {
            $firstDay = date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' +7 days'));
            $lastDay = date("d/m/Y", strtotime(str_replace("/", "-", $lastDay) . ' +7 days'));
        } else if ($beforeAfter == 'before') {
            $firstDay = date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' -7 days'));
            $lastDay = date("d/m/Y", strtotime(str_replace("/", "-", $lastDay) . ' -7 days'));
        }

        $firstDayTime = strtotime(str_replace("/", "-", $firstDay));
        $lastDayTime = strtotime(str_replace("/", "-", $lastDay));

        $rows = [];
        $secondInADay = 60 * 60 * 24;
        $keyFormat = 'd';
        $counterKeyToString = function($key) {
            return ' ' . $key . ' ';
        };

        for ($dayIncrement = 0; $dayIncrement < 7; $dayIncrement++) {
            $dayCounterKey = $counterKeyToString(date($keyFormat, $firstDayTime + ($secondInADay * $dayIncrement)));
            $rows[$dayCounterKey] = [
                'count' => 0,
                'conform' => null
            ];
        }

        $arrivages = $arrivageRepository->countByDays($firstDay, $lastDay);
        foreach ($arrivages as $qttPerDay) {
            $dayCounterKey = $counterKeyToString($qttPerDay['date']->format($keyFormat));
            if (!isset($rows[$dayCounterKey])) {
                $rows[$dayCounterKey] = ['count' => 0];
            }

            $rows[$dayCounterKey]['count'] += $qttPerDay['count'];

            $dateHistory = $qttPerDay['date']->setTime(0, 0);

            $arrivalHistory = $arrivalHistoryRepository->getByDate($dateHistory);

            $rows[$dayCounterKey]['conform'] = isset($arrivalHistory)
                ? $arrivalHistory->getConformRate()
                : null;
        }
        return [
            'data' => $rows,
            'firstDay' => date("d/m/y", $firstDayTime),
            'firstDayData' => date("d/m/Y", $firstDayTime),
            'lastDay' => date("d/m/y", $lastDayTime),
            'lastDayData' => date("d/m/Y", $lastDayTime)
        ];
    }

    public function getDashboardCounter(EntityManagerInterface $entityManager,
                                        array $locationIds,
                                        bool $includeDelay = false,
                                        bool $includeLocationLabels = false): ?array {
        $packRepository = $entityManager->getRepository(Pack::class);

        if (!empty($locationIds)) {
            $locationRepository = $entityManager->getRepository(Emplacement::class);
            $locations = $locationRepository->findBy(['id' => $locationIds]);
        } else {
            $locations = [];
        }

        if (!empty($locations)) {
            $response = [];
            $response['delay'] = null;
            if ($includeDelay) {
                $lastEnCours = $packRepository->getCurrentPackOnLocations(
                    $locationIds,
                    [
                        'isCount' => false,
                        'field' => 'lastOngoingDrop.datetime, emplacement.dateMaxTime',
                        'limit' => 1,
                        'onlyLate' => true,
                        'order' => 'asc'
                    ]
                );
                if (!empty($lastEnCours[0]) && $lastEnCours[0]['dateMaxTime']) {
                    $lastEnCoursDateTime = $lastEnCours[0]['datetime'];
                    $date = $this->dateTimeService->getWorkedPeriodBetweenDates($entityManager, $lastEnCoursDateTime, new DateTime("now"));
                    $timeInformation = $this->enCoursService->getTimeInformation($date, $lastEnCours[0]['dateMaxTime']);
                    $response['delay'] = $timeInformation['countDownLateTimespan'];
                }
            }
            $response['subtitle'] = $includeLocationLabels
                ? array_reduce(
                    $locations,
                    function(string $carry, Emplacement $location) {
                        return $carry . (!empty($carry) ? ', ' : '') . $location->getLabel();
                    },
                    ''
                )
                : null;
            $response['count'] = $packRepository->getCurrentPackOnLocations($locationIds);
        } else {
            $response = null;
        }

        return $response;
    }

    /**
     * @param array $steps
     * @param callable $getObject
     * @return array
     */
    public function getObjectForTimeSpan(array $steps, callable $getObject): array {
        $timeSpanToObject = [];

        $timeSpans = [
            -1 => -1,
            0 => 1
        ];
        $lastKey = 1;
        $timeSpans += Stream::from($steps)
            ->reduce(function(array $carry, $step) use (&$lastKey) {
                $numberStep = (int) $step;
                $carry[$lastKey] = $numberStep;
                $lastKey = $numberStep;
                return $carry;
            }, []);

        foreach ($timeSpans as $timeBegin => $timeEnd) {
            $key = $timeBegin === -1
                ? $this->translationService->translate("Dashboard", "Retard", false)
                : ($timeEnd === 1
                    ? $this->translationService->translate("Dashboard", "Moins d'{1}", [
                        1 => "1h"
                    ], false)
                    : ($timeBegin . "h-" . $timeEnd . 'h'));
            $timeSpanToObject[$key] = $getObject($timeBegin, $timeEnd);
        }
        return $timeSpanToObject;
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Dashboard\Component $component
     * @throws Exception
     */
    public function persistOngoingPack(EntityManagerInterface $entityManager,
                                       Dashboard\Component $component): void {
        $config = $component->getConfig();

        $calculatedData = $this->getDashboardCounter(
            $entityManager,
            $config['locations'],
            (bool)$config['withTreatmentDelay'],
            (bool)$config['withLocationLabels']
        );

        $meter = $this->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);

        $meter
            ->setCount($calculatedData ? $calculatedData['count'] : 0)
            ->setDelay($calculatedData ? $calculatedData['delay'] : 0)
            ->setSubtitle($calculatedData['subtitle'] ?? null);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Dashboard\Component $component
     * @throws Exception
     */
    public function persistDailyHandlingIndicator(EntityManagerInterface $entityManager,
                                       Dashboard\Component $component): void {
        $config = $component->getConfig();
        $handlingRepository = $entityManager->getRepository(Handling::class);
        $now = new DateTime("now");
        $nowMorning = clone $now;
        $nowMorning->setTime(0, 0, 0, 0);
        $nowEvening = clone $now;
        $nowEvening->setTime(23, 59, 59, 59);
        $handlingStatusesFilter = $config['handlingStatuses'] ?? [];
        $handlingTypesFilter = $config['handlingTypes'] ?? [];

        $numberOfOperations = $handlingRepository->countByDates(
            $nowMorning,
            $nowEvening,
            [
                'isOperations' => true,
                'handlingStatusesFilter' => $handlingStatusesFilter,
                'handlingTypesFilter' => $handlingTypesFilter
            ]
        );

        $numberOfHandlings = $handlingRepository->countByDates(
            $nowMorning,
            $nowEvening,
            [
                'handlingStatusesFilter' => $handlingStatusesFilter,
                'handlingTypesFilter' => $handlingTypesFilter
            ]
        );

        $numberOfEmergenciesHandlings = $handlingRepository->countByDates(
            $nowMorning,
            $nowEvening,
            [
                'emergency' => true,
                'handlingStatusesFilter' => $handlingStatusesFilter,
                'handlingTypesFilter' => $handlingTypesFilter
            ]
        );

        $config = $component->getConfig();
        $config['selectedDate'] = true;
        $component->setConfig($config);

        $meter = $this->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);
        $secondCount = '<span>'
            . ($numberOfOperations ?? '0')
            . '</span><span class="text-wii-black"> '.$this->translationService->translate('Dashboard', 'lignes').'</span>';
        $thirdCount = '<span class="text-wii-black">'.$this->translationService->translate('Dashboard', 'Dont {1} urgences', [
                1 => '<span class="text-wii-danger">'.$numberOfEmergenciesHandlings.'</span>'
            ]).'</span>';

        $meter
            ->setCount($numberOfHandlings)
            ->setSubCounts([$secondCount, $thirdCount]);
    }

    public function persistCarriers(EntityManagerInterface $entityManager, Dashboard\Component $component) {
        $config = $component->getConfig();
        $carrierRepository = $entityManager->getRepository(Transporteur::class);
        $lineRepository = $entityManager->getRepository(TruckArrivalLine::class);
        $carriers = $carrierRepository->getDailyArrivalCarriersLabel($config['carriers'] ?? []);

        $meter = $this->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);
        $meter->setSubtitle(FormatHelper::carriers($carriers) ?: '-');
        $meter->setCount(0);
        if (isset($config['displayUnassociatedLines']) && $config['displayUnassociatedLines']) {
            $unassociatedLines = $lineRepository->getUnassociatedLines();
            $meter->setCount(count($unassociatedLines));

            if (isset($config['displayLateLines']) && $config['displayLateLines']) {
                $lateLines = Stream::from($unassociatedLines)
                    ->filter((fn(TruckArrivalLine $line) => $this->truckArrivalLineService->lineIsLate($line, $entityManager)))
                    ->count();
                $meter
                    ->setSubCounts([
                        '<span>Numéros de tracking transporteur non associés</span>',
                        '<span class="text-wii-black">Dont</span> <span class="font-">' . $lateLines . '</span> <span class="text-wii-black">en retard</span>'
                    ]);
            }
        }
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Dashboard\Component $component
     * @param bool $daily
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function persistArrivalsEmergencies(EntityManagerInterface $entityManager,
                                               Dashboard\Component    $component,
                                               bool                   $daily,
                                               bool                   $active): void {
        $meter = $this->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);

        $emergencyRepository = $entityManager->getRepository(Urgence::class);
        $unsolvedEmergencies = $emergencyRepository->countUnsolved($daily, $active);
        $meter
            ->setCount($unsolvedEmergencies ?? 0);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Dashboard\Component $component
     */
    public function persistActiveReferenceAlerts(EntityManagerInterface $entityManager,
                                                 Dashboard\Component $component): void {
        $meter = $this->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);
        $alertRepository = $entityManager->getRepository(Alert::class);
        $count = $alertRepository->countAllActiveByParams($component->getConfig());

        $meter
            ->setCount($count ?? 0);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Dashboard\Component $component
     */
    public function persistMonetaryReliabilityGraph(EntityManagerInterface $entityManager,
                                                    Dashboard\Component $component): void {
        $meter = $this->persistDashboardMeter($entityManager, $component, DashboardMeter\Chart::class);
        $config = $component->getConfig();

        $mouvementStockRepository = $entityManager->getRepository(MouvementStock::class);

        $firstDayOfCurrentMonth = date("Y-m-d", strtotime("first day of this month"));
        $lastDayOfCurrentMonth = date("Y-m-d", strtotime("last day of this month", strtotime($firstDayOfCurrentMonth)));
        $precedentMonthFirst = $firstDayOfCurrentMonth;
        $precedentMonthLast = $lastDayOfCurrentMonth;
        $idx = 0;
        $value = [];
        $value['data'] = [];
        while ($idx !== 6) {
            $month = date("m", strtotime($precedentMonthFirst));
            $month = date("F", mktime(0, 0, 0, $month, 10));
            $totalEntryRefArticleOfPrecedentMonth = $mouvementStockRepository->countTotalEntryPriceRefArticle($precedentMonthFirst, $precedentMonthLast);
            $totalExitRefArticleOfPrecedentMonth = $mouvementStockRepository->countTotalExitPriceRefArticle($precedentMonthFirst, $precedentMonthLast);
            $totalRefArticleOfPrecedentMonth = $totalEntryRefArticleOfPrecedentMonth - $totalExitRefArticleOfPrecedentMonth;
            $totalEntryArticleOfPrecedentMonth = $mouvementStockRepository->countTotalEntryPriceArticle($precedentMonthFirst, $precedentMonthLast);
            $totalExitArticleOfPrecedentMonth = $mouvementStockRepository->countTotalExitPriceArticle($precedentMonthFirst, $precedentMonthLast);
            $totalArticleOfPrecedentMonth = $totalEntryArticleOfPrecedentMonth - $totalExitArticleOfPrecedentMonth;

            $nbrFiabiliteMonetaireOfPrecedentMonth = $totalRefArticleOfPrecedentMonth + $totalArticleOfPrecedentMonth;
            $month = str_replace(
                array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'),
                array('Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'),
                $month
            );
            $value['data'][$month] = $nbrFiabiliteMonetaireOfPrecedentMonth;
            $precedentMonthFirst = date("Y-m-d", strtotime("-1 month", strtotime($precedentMonthFirst)));
            $precedentMonthLast = date("Y-m-d", strtotime("last day of -1 month", strtotime($precedentMonthLast)));
            $idx += 1;
        }
        $values = array_reverse($value['data']);
        $chartColors = $config['chartColors'] ?? [];

        $meter
            ->setData($values)
            ->setChartColors($chartColors);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Dashboard\Component $component
     * @param string $class
     * @return DashboardMeter\Indicator|DashboardMeter\Chart
     */
    private function persistDashboardMeter(EntityManagerInterface $entityManager,
                                           Dashboard\Component $component,
                                           string $class) {

        /** @var DashboardMeter\Indicator|DashboardMeter\Chart|null $meter */
        $meter = $component->getMeter();

        if (!isset($meter)) {
            $meter = new $class();
            $meter->setComponent($component);
            $entityManager->persist($meter);
        }

        return $meter;
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Dashboard\Component $component
     * @throws Exception
     */
    public function persistPackToTreatFrom(EntityManagerInterface $entityManager,
                                           Dashboard\Component $component): void {

        $locationClusterMeterRepository = $entityManager->getRepository(LocationClusterMeter::class);

        $config = $component->getConfig();
        $config['legends'] = [];
        $countLegend = 1;
        foreach($config['chartColors'] ?? [] as $key => $legend){
            $config['legends'][$key] = [];
            Stream::from($config)
                ->each(function($conf, $arrayKey) use ($countLegend, $key, &$config) {
                    if (str_starts_with($arrayKey, 'legend') && str_contains($arrayKey, '_') && str_contains($arrayKey, $countLegend)) {
                        $explode = explode('_', $arrayKey);
                        $config['legends'][$key][$explode[1]] = $conf;
                        unset($config[$arrayKey]);
                    }
                });
            $countLegend++;
        }

        $legend1 = 'Legende1';
        $legend2 = 'Legende2';
        $clusterKeys = ['firstOriginLocation', 'secondOriginLocation', 'firstDestinationLocation', 'secondDestinationLocation'];
        foreach ($clusterKeys as $key) {
            $this->updateComponentLocationCluster($entityManager, $component, $key);
        }

        $entityManager->flush();
        $needsFirstOriginFilter = $component->getLocationCluster('firstOriginLocation')
            && $component->getLocationCluster('firstOriginLocation')->getLocations()->count() > 0;
        $data = [
            'chartColors' => [
                $legend1 => $config['chartColors']['Legende1'] ?? null,
                $legend2 => $config['chartColors']['Legende2'] ?? null
            ],
            'chartData' => $this->getDailyObjectsStatistics($entityManager, DashboardService::DEFAULT_WEEKLY_REQUESTS_SCALE, function (DateTime $date) use (
                $legend1,
                $legend2,
                $locationClusterMeterRepository,
                $component,
                $needsFirstOriginFilter
            ) {
                return [
                    $legend1 => $locationClusterMeterRepository->countByDate(
                        $date,
                        $component->getLocationCluster('firstDestinationLocation'),
                        $needsFirstOriginFilter ? $component->getLocationCluster('firstOriginLocation') : null
                    ),
                    $legend2 => $locationClusterMeterRepository->countByDate(
                        $date,
                        $component->getLocationCluster('secondDestinationLocation'),
                        $component->getLocationCluster('secondOriginLocation')
                    )
                ];
            })
        ];
        $meter = $this->persistDashboardMeter($entityManager, $component, DashboardMeter\Chart::class);
        $meter->setData($data['chartData']);
        $meter->setChartColors($data['chartColors']);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Dashboard\Component $component
     * @throws Exception
     */
    public function persistDroppedPacks(EntityManagerInterface $entityManager,
                                        Dashboard\Component $component): void {
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);

        $config = $component->getConfig();
        $clusterKey = 'locations';
        $this->updateComponentLocationCluster($entityManager, $component, $clusterKey);
        $locationCluster = $component->getLocationCluster($clusterKey);
        $entityManager->flush();
        $packsCountByDays = $this->getDailyObjectsStatistics($entityManager, DashboardService::DEFAULT_WEEKLY_REQUESTS_SCALE,
            function (DateTime $date) use ($trackingMovementRepository, $locationCluster) {
                return $trackingMovementRepository->countDropsOnLocationsOn($date, $locationCluster->getLocations()->toArray());
        });

        $chartColors = $config['chartColors'] ?? [Dashboard\ComponentType::DEFAULT_CHART_COLOR];

        $meter = $this->persistDashboardMeter($entityManager, $component, DashboardMeter\Chart::class);
        $meter
            ->setData($packsCountByDays)
            ->setChartColors($chartColors);
    }

    public function persistEntriesToHandle(EntityManagerInterface $entityManager,
                                           Dashboard\Component $component): void {

        $config = $component->getConfig();
        $natureRepository = $entityManager->getRepository(Nature::class);
        $locationClusterRepository = $entityManager->getRepository(LocationCluster::class);

        $naturesFilter = !empty($config['natures'])
            ? $natureRepository->findBy(['id' => $config['natures']])
            : [];

        $clusterKey = 'locations';
        $this->updateComponentLocationCluster($entityManager, $component, $clusterKey);
        $entityManager->flush();

        $locationCluster = $component->getLocationCluster($clusterKey);

        $locationCounters = [];

        $globalCounter = 0;

        $maxResultPackOnCluster = 1000;

        $olderPackLocation = [
            'locationLabel' => null,
            'locationId' => null,
            'packDateTime' => null
        ];

        if (!empty($naturesFilter)) {
            $defaultSlug = LanguageHelper::clearLanguage($this->languageService->getDefaultSlug($entityManager));
            $defaultLanguage = $entityManager->getRepository(Language::class)->findOneBy(['slug' => $defaultSlug]);
            $nbPacksOnCluster = $locationClusterRepository->countPacksOnCluster($locationCluster, $naturesFilter, $defaultLanguage);
            if ($nbPacksOnCluster > $maxResultPackOnCluster) {
                throw new DashboardException("Nombre de données trop important");
            }
            $packsOnCluster = $locationClusterRepository->getPacksOnCluster($locationCluster, $naturesFilter, $defaultLanguage);

            $countByNatureBase = [];
            foreach ($naturesFilter as $wantedNature) {
                $countByNatureBase[$this->formatService->nature($wantedNature)] = 0;
            }
            $segments = $config['segments'];

            $lastSegmentKey = count($segments) - 1;
            $adminDelay = "$segments[$lastSegmentKey]:00";

            $truckArrivalTime = $config['truckArrivalTime'] ?? null;

            $graphData = $this->getObjectForTimeSpan($segments, function (int $beginSpan, int $endSpan)
                                                                use (
                                                                    $entityManager,
                                                                    $countByNatureBase,
                                                                    &$packsOnCluster,
                                                                    $adminDelay,
                                                                    &$locationCounters,
                                                                    &$olderPackLocation,
                                                                    &$globalCounter,
                                                                    $truckArrivalTime) {
                $countByNature = array_merge($countByNatureBase);
                $packUntreated = [];
                foreach ($packsOnCluster as $pack) {
                    $interval = $this->dateTimeService->getWorkedPeriodBetweenDates($entityManager, $pack['firstTrackingDateTime'], new DateTime("now"));
                    $timeInformation = $this->enCoursService->getTimeInformation($interval, $adminDelay);
                    $countDownHours = isset($timeInformation['countDownLateTimespan'])
                        ? ($timeInformation['countDownLateTimespan'] / 1000 / 60 / 60)
                        : null;

                    $countDownHours -= $truckArrivalTime && $pack['truckArrivalDelay']
                        ? intval($pack['truckArrivalDelay']) / 1000 / 60 / 60
                        : 0;

                    if (isset($countDownHours)
                        && (
                            ($countDownHours < 0 && $beginSpan === -1) // count late pack
                            || ($countDownHours >= 0 && $countDownHours >= $beginSpan && $countDownHours < $endSpan)
                        )) {

                        $this->updateOlderPackLocation($olderPackLocation, $pack);

                        $natureLabel = $pack['natureLabel'];
                        $countByNature[$natureLabel] = $countByNature[$natureLabel] ?? 0;
                        $countByNature[$natureLabel]++;

                        $currentLocationId = $pack['currentLocationId'];
                        $locationCounters[$currentLocationId] = $locationCounters[$currentLocationId] ?? 0;
                        $locationCounters[$currentLocationId]++;

                        $globalCounter++;
                    } else {
                        $packUntreated[] = $pack;
                    }
                }

                $packsOnCluster = $packUntreated;

                return $countByNature;
            });
        }

        if (empty($graphData)) {
            $graphData = $this->getObjectForTimeSpan([], static fn() => 0);
        }

        $totalToDisplay = $olderPackLocation['locationId'] ? $globalCounter : null;
        $locationToDisplay = $olderPackLocation['locationLabel'] ?: null;
        $chartColors = Stream::from($naturesFilter)
            ->filter(fn (Nature $nature) => $nature->getColor())
            ->keymap(fn(Nature $nature) => [
                $this->formatService->nature($nature),
                $nature->getColor()
            ])
            ->toArray();

        $meter = $this->persistDashboardMeter($entityManager, $component, DashboardMeter\Chart::class);

        $meter
            ->setChartColors($chartColors)
            ->setData($graphData)
            ->setTotal($totalToDisplay ?: '-')
            ->setLocation($locationToDisplay ?: '-');
    }

    private function updateOlderPackLocation(array &$olderPackLocation, array $pack): void
    {
        if (empty($olderPackLocation['locationLabel'])
            || empty($olderPackLocation['locationId'])
            || empty($olderPackLocation['packDateTime'])
            || $olderPackLocation['packDateTime'] > $pack['lastTrackingDateTime'])
        {
            $olderPackLocation['locationLabel'] = $pack['currentLocationLabel'];
            $olderPackLocation['locationId'] = $pack['currentLocationId'];
            $olderPackLocation['packDateTime'] = $pack['lastTrackingDateTime'];
        }
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Dashboard\Component $component
     * @throws Exception
     */
    public function persistArrivalsAndPacksMeter(EntityManagerInterface $entityManager,
                                                 Dashboard\Component $component): void {
        $config = $component->getConfig();
        $type = $component->getType();
        $weeklyRequest = ($type->getMeterKey() === Dashboard\ComponentType::WEEKLY_ARRIVALS_AND_PACKS);
        $dailyRequest = ($type->getMeterKey() === Dashboard\ComponentType::DAILY_ARRIVALS_AND_PACKS);

        if (!$dailyRequest && !$weeklyRequest) {
            throw new InvalidArgumentException('Invalid component type');
        }

        $displayPackNatures = $config['displayPackNatures'] ?? false;
        $arrivalStatusesFilter = $config['arrivalStatuses'] ?? [];
        $arrivalTypesFilter = $config['arrivalTypes'] ?? [];
        if ($dailyRequest) {
            $scale = $config['daysNumber'] ?? self::DEFAULT_DAILY_REQUESTS_SCALE;
        } else {
            $scale = self::DEFAULT_WEEKLY_REQUESTS_SCALE;
        }

        $arrivageRepository = $entityManager->getRepository(Arrivage::class);

        if ($dailyRequest) {
            $getObjectsStatisticsCallable = 'getDailyObjectsStatistics';
        } else {
            $getObjectsStatisticsCallable = 'getWeeklyObjectsStatistics';
        }

        // arrivals column
        $chartData = $this->{$getObjectsStatisticsCallable}(
            $entityManager,
            $scale,
            function(DateTime $dateMin, DateTime $dateMax) use ($arrivageRepository, $arrivalStatusesFilter, $arrivalTypesFilter) {
                return $arrivageRepository->countByDates($dateMin, $dateMax, $arrivalStatusesFilter, $arrivalTypesFilter);
            }
        );
        // packs column
        if ($scale) {
            $natureData = $this->getArrivalPacksData($entityManager, $getObjectsStatisticsCallable, $scale, $arrivalStatusesFilter, $arrivalTypesFilter, $displayPackNatures);

            if ($natureData) {
                $chartData['stack'] = $natureData;
            }
            if(!$displayPackNatures && isset($config['chartColor1'])) {
                $chartData['stack'][0]['backgroundColor'] = $config['chartColor1'];
            }
        }
        $meter = $this->persistDashboardMeter($entityManager, $component, DashboardMeter\Chart::class);
        $meter
            ->setData($chartData);
    }

    public function persistDailyHandlingOrOperations(EntityManagerInterface $entityManager,
                                                     Dashboard\Component $component) {
        $config = $component->getConfig();
        $isOperations = $component->getType() && $component->getType()->getMeterKey() === Dashboard\ComponentType::DAILY_OPERATIONS;
        $handlingStatusesFilter = $config['handlingStatuses'] ?? [];
        $handlingTypesFilter = $config['handlingTypes'] ?? [];
        $scale = $config['daysNumber'] ?? self::DEFAULT_DAILY_REQUESTS_SCALE;
        $period = $config['period'] ?? self::DAILY_PERIOD_PREVIOUS_DAYS;
        $separateType = isset($config['separateType']) && $config['separateType'];

        $handlingRepository = $entityManager->getRepository(Handling::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $chartData = $this->getDailyObjectsStatistics(
            $entityManager,
            $scale,
            function(DateTime $dateMin, DateTime $dateMax) use ($handlingRepository, $handlingStatusesFilter, $handlingTypesFilter, $separateType, $isOperations) {
                return $handlingRepository->countByDates(
                    $dateMin,
                    $dateMax,
                    [
                        'groupByTypes' => $separateType,
                        'isOperations' => $isOperations,
                        'handlingStatusesFilter' => $handlingStatusesFilter,
                        'handlingTypesFilter' => $handlingTypesFilter
                    ]
                );
            },
            $period
        );
        if ($separateType) {
            $types = $typeRepository->findBy(['id' => $handlingTypesFilter]);
            $chartData = Stream::from($chartData)
                ->reduce(function ($carry, $data, $date) use ($types) {
                    foreach ($types as $type) {
                        $carry[$date][$type->getLabel()] = 0;
                    }
                    foreach ($data as $datum) {
                        if (isset($datum['typeLabel']) && $datum['typeLabel']) {
                            $carry[$date][$datum['typeLabel']] = $datum['count'];
                        }
                    }
                    return $carry;
                }, []);
        }
        $chartColors = $config['chartColors'] ?? [];

        $meter = $this->persistDashboardMeter($entityManager, $component, DashboardMeter\Chart::class);
        $meter
            ->setData($chartData);
        if ($chartColors) {
            $meter->setChartColors($chartColors);
        }

        return $chartData;
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Dashboard\Component $component
     */
    public function persistMonetaryReliabilityIndicator(EntityManagerInterface $entityManager,
                                                        Dashboard\Component $component): void {

        $stockMovementRepository = $entityManager->getRepository(MouvementStock::class);

        $firstDayOfCurrentMonth = date("Y-m-d", strtotime("first day of this month"));
        $totalEntryRefArticleOfThisMonth = $stockMovementRepository->countTotalEntryPriceRefArticle($firstDayOfCurrentMonth);
        $totalExitRefArticleOfThisMonth = $stockMovementRepository->countTotalExitPriceRefArticle($firstDayOfCurrentMonth);
        $totalRefArticleOfThisMonth = $totalEntryRefArticleOfThisMonth - $totalExitRefArticleOfThisMonth;
        $totalEntryArticleOfThisMonth = $stockMovementRepository->countTotalEntryPriceArticle($firstDayOfCurrentMonth);
        $totalExitArticleOfThisMonth = $stockMovementRepository->countTotalExitPriceArticle($firstDayOfCurrentMonth);
        $totalArticleOfThisMonth = $totalEntryArticleOfThisMonth - $totalExitArticleOfThisMonth;
        $count = $totalRefArticleOfThisMonth + $totalArticleOfThisMonth;

        $meter = $this->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);
        $meter
            ->setCount($count ?? 0);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Dashboard\Component $component
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function persistReferenceReliability(EntityManagerInterface $entityManager,
                                                Dashboard\Component $component): void {

        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $stockMovementRepository = $entityManager->getRepository(MouvementStock::class);

        $types = [
            MouvementStock::TYPE_INVENTAIRE_ENTREE,
            MouvementStock::TYPE_INVENTAIRE_SORTIE
        ];
        $nbStockInventoryMovements = $stockMovementRepository->countByTypes($types);
        $nbActiveRefAndArt = $referenceArticleRepository->countActiveTypeRefRef() + $articleRepository->countActiveArticles();
        $count = $nbActiveRefAndArt == 0 ? 0 : (1 - ($nbStockInventoryMovements / $nbActiveRefAndArt)) * 100;

        $meter = $this->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);
        $meter
            ->setCount($count ?? 0);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Dashboard\Component $component
     * @throws Exception
     */
    public function persistDailyDispatches(EntityManagerInterface $entityManager,
                                           Dashboard\Component $component): void
    {
        $config = $component->getConfig();

        $dispatchStatusesFilter = $config['dispatchStatuses'] ?? [];
        $dispatchTypesFilter = $config['dispatchTypes'] ?? [];
        $scale = $config['scale'] ?? self::DEFAULT_DAILY_REQUESTS_SCALE;
        $period = $config['period'] ?? self::DAILY_PERIOD_PREVIOUS_DAYS;
        $date = $config['date'] ?? 'endDate';
        $separateType = isset($config['separateType']) && $config['separateType'];

        switch ($date) {
            case 'treatmentDate':
                $type = "de traitement";
                break;
            case 'startDate':
                $type = "d'échéances Du";
                break;
            case 'validationDate':
                $type = "de validation";
                break;
            case 'endDate':
            default:
                $type = "d'échéances Au";
                break;
        }

        $hint = "Nombre d'acheminements ayant leurs dates $type sur les jours présentés";

        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $chartData = $this->getDailyObjectsStatistics(
            $entityManager,
            $scale,
            function(DateTime $dateMin, DateTime $dateMax) use ($dispatchRepository, $dispatchStatusesFilter, $dispatchTypesFilter, $date, $separateType) {
                return $dispatchRepository->countByDates($dateMin, $dateMax, $separateType, $dispatchStatusesFilter, $dispatchTypesFilter, $date);
            },
            $period
        );

        if ($separateType) {
            $types = $typeRepository->findBy(['id' => $dispatchTypesFilter]);
            $chartData = Stream::from($chartData)
                ->reduce(function ($carry, $data, $date) use ($types) {
                    foreach ($types as $type) {
                        $carry[$date][$type->getLabel()] = 0;
                    }
                    foreach ($data as $datum) {
                        if (isset($datum['typeLabel']) && $datum['typeLabel']) {
                            $carry[$date][$datum['typeLabel']] = $datum['count'];
                        }
                    }
                    return $carry;
                }, []);
        }
        $chartColors = $config['chartColors'] ?? [];

        $chartData['hint'] = $hint;
        $meter = $this->persistDashboardMeter($entityManager, $component, DashboardMeter\Chart::class);
        $meter
            ->setData($chartData);
        if ($chartColors) {
            $meter->setChartColors($chartColors);
        }
    }

    public function persistEntitiesToTreat(EntityManagerInterface $entityManager,
                                           Dashboard\Component $component): void {
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

        $meter = $this->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);
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
                    } else {
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

            if(preg_match(Dashboard\ComponentType::ENTITY_TO_TREAT_REGEX_TREATMENT_DELAY, $treatmentDelay)) {
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

    public function updateComponentLocationCluster(EntityManagerInterface $entityManager,
                                                    Dashboard\Component $component,
                                                    string $fieldName): void {

        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $config = $component->getConfig();
        $locationCluster = $component->getLocationCluster($fieldName);

        if (!$locationCluster) {
            $locationCluster = new LocationCluster();
            $locationCluster
                ->setComponent($component)
                ->setClusterKey($fieldName);
            $entityManager->persist($locationCluster);
        }

        foreach ($locationCluster->getLocations() as $location) {
            $locationCluster->removeLocation($location);
        }

        if(!empty($config[$fieldName])) {
            $locations = $locationRepository->findBy([
                'id' => $config[$fieldName]
            ]);

            foreach ($locations as $location) {
                $locationCluster->addLocation($location);
            }
        }
    }

    /**
     * Make assoc array. Assoc a date like "d/m" to a counter returned by given function
     * If table DaysWorked is no filled then the returned array is empty
     * Else we return an array with 7 counters
     * @param EntityManagerInterface $entityManager
     * @param int $nbDaysToReturn
     * @param callable $getCounter (DateTime $dateMin, DateTime $dateMax) => integer
     * @param string $period
     * @return array ['d/m' => $getCounter return]
     * @throws Exception
     */
    private function getDailyObjectsStatistics(EntityManagerInterface $entityManager,
                                               int $nbDaysToReturn,
                                               callable $getCounter,
                                               string $period = self::DAILY_PERIOD_PREVIOUS_DAYS): array {

        if (!in_array($period, [self::DAILY_PERIOD_PREVIOUS_DAYS, self::DAILY_PERIOD_NEXT_DAYS])) {
            throw new InvalidArgumentException();
        }

        $daysToReturn = [];
        $dayIndex = 0;

        $workedDays = $this->workPeriodService->get($entityManager, WorkPeriodItem::WORKED_DAYS);

        if (!empty($workedDays)) {
            while (count($daysToReturn) < $nbDaysToReturn) {
                $operator = $period === self::DAILY_PERIOD_PREVIOUS_DAYS ? '-' : '+';
                $dateToCheck = new DateTime("now " . $operator . " $dayIndex days");

                if ($this->workPeriodService->isOnWorkPeriod($entityManager, $dateToCheck, ["onlyDayCheck" => true])) {
                    if ($period === self::DAILY_PERIOD_PREVIOUS_DAYS) {
                        array_unshift($daysToReturn, $dateToCheck);
                    }
                    else {
                        $daysToReturn[] = $dateToCheck;
                    }
                }

                $dayIndex++;
            }
        }

        return array_reduce(
            $daysToReturn,
            function(array $carry, DateTime $dateToCheck) use ($getCounter) {
                $dateMin = clone $dateToCheck;
                $dateMin->setTime(0, 0, 0);
                $dateMax = clone $dateToCheck;
                $dateMax->setTime(23, 59, 59);
                $dateToCheck->setTime(0, 0);

                $dayKey = $dateToCheck->format('d/m');
                $carry[$dayKey] = $getCounter($dateMin, $dateMax);
                return $carry;
            },
            []);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Dashboard\Component $component
     * @throws Exception
     */
    public function persistHandlingTracking(EntityManagerInterface $entityManager, mixed $component): void
    {
        $config = $component->getConfig();

        $handlingTypes = $config['handlingTypes'] ?? [];
        $scale = $config['scale'] ?? self::DEFAULT_HANDLING_TRACKING_SCALE;
        $period = $config['period'] ?? self::DAILY_PERIOD_PREVIOUS_DAYS;
        $dates = [];

        if(!empty($config['creationDate'])){
            $dates[] = 'creationDate';
        }
        if(!empty($config['desiredDate'])){
            $dates[] = 'desiredDate';
        }
        if(!empty($config['validationDate'])){
            $dates[] = 'validationDate';
        }

        $hint = $config['tooltip'] ?? '';

        $handlingRepository = $entityManager->getRepository(Handling::class);

        $chartData = [];
        $labels = [
            'validationDate' => 'Date de traitement',
            'desiredDate' => 'Date attendue',
            'creationDate' => 'Date de création',
        ];
        foreach ($dates as $date){
            $dateData = $this->getDailyObjectsStatistics(
                $entityManager,
                $scale,
                function(DateTime $dateMin, DateTime $dateMax) use ($date, $handlingRepository, $handlingTypes) {
                    return $handlingRepository->countByDates($dateMin, $dateMax, [
                        'handlingTypesFilter' => $handlingTypes,
                        'date' => $date
                    ]);
                },
                $period
            );
            $label = $labels[$date];
            foreach ($dateData as $dateKey => $datum) {
                if (!isset($chartData[$dateKey][$label])) {
                    $chartData[$dateKey][$label] = 0;
                }
                $chartData[$dateKey][$label] += intval($datum);
            }
        }

        $chartColors = $config['chartColors'] ?? [];

        $chartData['hint'] = $hint;
        $meter = $this->persistDashboardMeter($entityManager, $component, DashboardMeter\Chart::class);
        $meter
            ->setData($chartData);
        if ($chartColors) {
            $meter->setChartColors($chartColors);
        }
    }

    /**
     * Make assoc array. Assoc a date like ('S' . weekNumber) to a counter returned by given function
     * If table DaysWorked is no filled then the returned array is empty
     * Else we return an array with 5 counters
     * @param EntityManagerInterface $entityManager
     * @param int $nbWeeksToReturn
     * @param callable $getCounter (DateTime $dateMin, DateTime $dateMax) => integer
     * @return array [('S' . weekNumber) => $getCounter return]
     */
    private function getWeeklyObjectsStatistics(EntityManagerInterface $entityManager,
                                                int $nbWeeksToReturn,
                                                callable $getCounter): array {
        $weekCountersToReturn = [];

        $workedDays = $this->workPeriodService->get($entityManager, WorkPeriodItem::WORKED_DAYS);

        if (!empty($workedDays)) {
            for ($weekIndex = ($nbWeeksToReturn - 2); $weekIndex >= -1; $weekIndex--) {
                $dateMin = new DateTime("monday $weekIndex weeks ago");
                $dateMin->setTime(0, 0);
                $dateMax = new DateTime("sunday $weekIndex weeks ago");
                $dateMax->setTime(23, 59, 59);
                $dayKey = ('S' . $dateMin->format('W'));
                $weekCountersToReturn[$dayKey] = $getCounter($dateMin, $dateMax);
            }
        }

        return $weekCountersToReturn;
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param string $getObjectsStatisticsCallable
     * @param int $scale
     * @param array $arrivalStatusesFilter
     * @param array $arrivalTypesFilter
     * @param array|null $workFreeDays
     * @param bool $displayPackNatures
     * @return array
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    private function getArrivalPacksData(EntityManagerInterface $entityManager,
                                         string $getObjectsStatisticsCallable,
                                         int $scale,
                                         array $arrivalStatusesFilter,
                                         array $arrivalTypesFilter,
                                         bool $displayPackNatures = false): array {

        $packRepository = $entityManager->getRepository(Pack::class);
        $natureRepository = $entityManager->getRepository(Nature::class);

        $packCountByDay = $this->{$getObjectsStatisticsCallable}(
            $entityManager,
            $scale,
            function(DateTime $dateMin, DateTime $dateMax) use ($packRepository, $arrivalStatusesFilter, $arrivalTypesFilter, $displayPackNatures) {
                return $packRepository->countPacksByDates($dateMin, $dateMax, $displayPackNatures, $arrivalStatusesFilter, $arrivalTypesFilter);
            }
        );
        $naturesStack = [];
        if ($displayPackNatures) {
            $natures = $natureRepository->findAll();
            foreach ($natures as $nature) {
                $natureId = $nature->getId();
                if (!isset($naturesStack[$natureId])) {
                    $naturesStack[$natureId] = [
                        'id' => $natureId,
                        'label' => $this->formatService->nature($nature),
                        'backgroundColor' => $nature->getColor(),
                        'stack' => 'stack',
                        'data' => []
                    ];
                }
                foreach ($packCountByDay as $countersGroupByNature) {
                    $found = false;
                    if (!empty($countersGroupByNature)) {
                        foreach ($countersGroupByNature as $natureCount) {
                            $currentNatureId = (int)$natureCount['natureId'];
                            if ($natureId === $currentNatureId) {
                                $naturesStack[$natureId]['data'][] = (int)$natureCount['count'];
                                $found = true;
                                break;
                            }
                        }
                    }

                    if (!$found) {
                        $naturesStack[$nature->getId()]['data'][] = 0;
                    }
                }
                $total = Stream::from($naturesStack[$nature->getId()]['data'])
                    ->reduce(function ($counter, $current) {
                        return $counter + $current;
                    }, 0);

                if ($total === 0) {
                    unset($naturesStack[$nature->getId()]);
                }
            }
        } else {
            $naturesStack[] = [
                'label' => 'Unité logistique',
                'backgroundColor' => '#E5E1E1',
                'stack' => 'stack',
                'data' => []
            ];
            foreach ($packCountByDay as $packCount) {
                $naturesStack[0]['data'][] = $packCount;
            }
        }
        return array_values($naturesStack);
    }

    public function persistEntitiesLatePack(EntityManagerInterface $entityManager) {
        $latePackRepository = $entityManager->getRepository(LatePack::class);
        $lastLates = $this->enCoursService->getLastEnCoursForLate($entityManager);
        $latePackRepository->clearTable();
        foreach ($lastLates as $lastLate) {
            $latePack = new LatePack();
            $latePack
                ->setDelay($lastLate['delayTimeStamp'])
                ->setDate($lastLate['date'])
                ->setEmp($lastLate['emp'])
                ->setLU($lastLate['LU']);
            $entityManager->persist($latePack);
        }
    }

    public function persistDailyDeliveryOrders(EntityManagerInterface   $entityManager,
                                               Dashboard\Component      $component): void {
        $config = $component->getConfig();
        $deliveryOrderStatusesFilter = $config['deliveryOrderStatuses'] ?? [];
        $deliveryOrderTypesFilter = $config['deliveryOrderTypes'] ?? [];
        $scale = $config['daysNumber'] ?? self::DEFAULT_DAILY_REQUESTS_SCALE;
        $period = $config['period'] ?? self::DAILY_PERIOD_PREVIOUS_DAYS;
        $date = $config['date'];

        $deliveryOrderRepository = $entityManager->getRepository(Livraison::class);

        $type = match ($config['date']) {
            'validationDate'  => "de validation",
            'treatmentDate'   => "de traitement",
            default           => "date attendue",
        };
        $hint = "Nombre d'" . mb_strtolower($this->translationService->translate("Ordre", "Livraison", "Ordre de livraison", false)) . " ayant leur $type sur les jours présentés";

        $chartData = $this->getDailyObjectsStatistics(
            $entityManager,
            $scale,
            function(DateTime $dateMin, DateTime $dateMax) use ($deliveryOrderRepository, $deliveryOrderStatusesFilter, $deliveryOrderTypesFilter, $date) {
                return $deliveryOrderRepository->countByDates(
                    $dateMin,
                    $dateMax,
                    [
                        'deliveryOrderStatusesFilter' => $deliveryOrderStatusesFilter,
                        'deliveryOrderTypesFilter' => $deliveryOrderTypesFilter,
                        'date' => $date
                    ]
                );
            },
            $period
        );

        if ($config['displayDeliveryOrderContentCheckbox'] && $scale) {
            $deliveryOrderContent = $this->getDeliveryOrderContent(
                $entityManager,
                $scale,
                $deliveryOrderStatusesFilter,
                $deliveryOrderTypesFilter,
                [
                    'displayDeliveryOrderContent' => $config['displayDeliveryOrderContent'],
                    'date' => $date,
                    'period' => $period
                ]
            );

            if ($deliveryOrderContent) {
                $chartData['stack'] = $deliveryOrderContent;
            }

            if(isset($config['chartColor1'])) {
                $chartData['stack'][0]['backgroundColor'] = $config['chartColor1'];
            }
        }

        $chartColors = $config['chartColors'] ?? [];
        $chartData['hint'] = $hint;

        $meter = $this->persistDashboardMeter($entityManager, $component, DashboardMeter\Chart::class);
        $meter
            ->setData($chartData);
        if ($chartColors) {
            $meter->setChartColors($chartColors);
        }
    }

    public function getDeliveryOrderContent(EntityManagerInterface $entityManager,
                                            int $scale,
                                            array $deliveryOrderStatusesFilter,
                                            array $deliveryOrderTypesFilter,
                                            array $options = []): array {
        $deliveryOrderRepository = $entityManager->getRepository(Livraison::class);

        $contentCountByDay = $this->getDailyObjectsStatistics(
            $entityManager,
            $scale,
            function(DateTime $dateMin, DateTime $dateMax) use ($deliveryOrderRepository, $deliveryOrderStatusesFilter, $deliveryOrderTypesFilter, $options) {
                return $deliveryOrderRepository->countContentByDates($dateMin, $dateMax, $deliveryOrderStatusesFilter, $deliveryOrderTypesFilter, $options);
            },
            $options['period']
        );

        $contentStack[] = [
            'label' => 'UL',
            'backgroundColor' => '#E5E1E1',
            'stack' => 'stack',
            'data' => []
        ];

        foreach ($contentCountByDay as $contentCount) {
            $contentStack[0]['data'][] = $contentCount;
        }

        return array_values($contentStack);
    }

    public function persistDailyProductions(EntityManagerInterface $entityManager,
                                            Dashboard\Component $component): void
    {
        $config = $component->getConfig();

        $productionStatusesFilter = $config['productionStatuses'] ?? [];
        $productionTypesFilter = $config['productionTypes'] ?? [];
        $scale = $config['scale'] ?? self::DEFAULT_DAILY_REQUESTS_SCALE;
        $period = $config['period'] ?? self::DAILY_PERIOD_PREVIOUS_DAYS;
        $date = $config['date'] ?? 'creationDate';
        $separateType = isset($config['separateType']) && $config['separateType'];

        switch ($date) {
            case 'validationDate':
                $type = "de validation";
                break;
            case 'treatmentDate':
                $type = "de traitement";
                break;
            case 'creationDate':
            default:
                $type =  "de création";
                break;
        }

        $hint = "Nombre de demandes de productions ayant leurs dates $type sur les jours présentés";

        $productionRepository = $entityManager->getRepository(ProductionRequest::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $chartData = $this->getDailyObjectsStatistics(
            $entityManager,
            $scale,
            function(DateTime $dateMin, DateTime $dateMax) use ($productionRepository, $productionStatusesFilter, $productionTypesFilter, $date, $separateType) {
                return $productionRepository->countByDates($dateMin, $dateMax, $separateType, $productionStatusesFilter, $productionTypesFilter, $date);
            },
            $period
        );

        if ($separateType) {
            $types = $typeRepository->findBy(['id' => $productionTypesFilter]);
            $chartData = Stream::from($chartData)
                ->reduce(static function ($carry, $data, $date) use ($types) {
                    foreach ($types as $type) {
                        $carry[$date][$type->getLabel()] = 0;
                    }
                    foreach ($data as $datum) {
                        if (isset($datum['typeLabel']) && $datum['typeLabel']) {
                            $carry[$date][$datum['typeLabel']] = $datum['count'];
                        }
                    }
                    return $carry;
                }, []);
        }
        $chartColors = $config['chartColors'] ?? [];

        $chartData['hint'] = $hint;
        $meter = $this->persistDashboardMeter($entityManager, $component, DashboardMeter\Chart::class);
        $meter
            ->setData($chartData);
        if ($chartColors) {
            $meter->setChartColors($chartColors);
        }
    }

    public function persistDisputesToTreat(EntityManagerInterface $entityManager, Dashboard\Component $component): void
    {
        $config = $component->getConfig();
        $disputeTypes = $config['disputeTypes'];
        $disputeStatuses = $config['disputeStatuses'];
        $disputeEmergency = $config['disputeEmergency'] ?? false;

        $disputeRepository = $entityManager->getRepository(Dispute::class);
        $count = $disputeRepository->countByFilters([
            'types' => $disputeTypes,
            'statuses' => $disputeStatuses,
            'disputeEmergency' => $disputeEmergency,
        ]);

        $meter = $this->persistDashboardMeter($entityManager, $component, DashboardMeter\Indicator::class);
        $meter->setCount($count ?? 0);
    }

    public function persistEntriesToHandleByTrackingDelay(EntityManagerInterface $entityManager,
                                                          Dashboard\Component $component): void {
        $config = $component->getConfig();
        $natureRepository = $entityManager->getRepository(Nature::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $trackingDelayRepository = $entityManager->getRepository(TrackingDelay::class);

        $naturesFilter = !empty($config['natures'])
            ? $natureRepository->findBy(['id' => $config['natures']])
            : [];

        $locationsFilter = !empty($config['locations'])
            ? $locationRepository->findBy(['id' => $config['locations']])
            : [];

        $maxResultPack = 1000;
        $globalCounter = null;

        if (!empty($naturesFilter) && !empty($locationsFilter)) {
            // TODO WIIS-12353 construire le tableau d'event en fonction du parametrage sur le composant
            $trackingDelayByFilters = $trackingDelayRepository->findByFilters($config['natures'], $config['locations'], [null,0,1,2], $maxResultPack);
            if (count($trackingDelayByFilters) > $maxResultPack) {
                throw new DashboardException("Nombre de données trop important");
            }

            $countByNatureBase = [];
            foreach ($naturesFilter as $wantedNature) {
                $countByNatureBase[$this->formatService->nature($wantedNature)] = 0;
            }

            $segments = $config['segments'];

            $customSegments = array_merge([-1, 1], $segments);
            $counterByEndingSpan = [];
            $nextElementToDisplay = [];

            Stream::from($trackingDelayByFilters)
                ->each(function(TrackingDelay $trackingDelay) use ($countByNatureBase, $customSegments, &$counterByEndingSpan, &$globalCounter, &$nextElementToDisplay) {
                    $pack = $trackingDelay->getPack();
                    $remainingTimeInSeconds = $this->packService->getTrackingDelayRemainingTime($pack);
                    if(empty($nextElementToDisplay)){
                        $nextElementToDisplay = [
                            'remainingTimeInSeconds' => abs($remainingTimeInSeconds),
                            'pack' => $pack,
                        ];
                    } else if(abs($remainingTimeInSeconds) < $nextElementToDisplay['remainingTimeInSeconds']) {
                        $nextElementToDisplay = [
                            'remainingTimeInSeconds' => abs($remainingTimeInSeconds),
                            'pack' => $pack,
                        ];
                    }

                    $countByNature = array_merge($countByNatureBase);
                    foreach ($customSegments as $key => $segmentEnd) {
                        if(!isset($counterByEndingSpan[$segmentEnd])) {
                            $counterByEndingSpan[$segmentEnd] = $countByNature;
                        }
                        $beginSpan = $segmentEnd === -1
                            ? -1
                            : ($segmentEnd === 1
                                ? 0
                                : ($customSegments[$key-1] * 60)
                            );
                        $endSpan = $segmentEnd === -1
                            ? -1
                            : ($segmentEnd * 60);

                        if ($remainingTimeInSeconds
                            && (
                                ($remainingTimeInSeconds < 0 && $beginSpan < 0) // count late pack
                                || ($remainingTimeInSeconds >= 0 && $remainingTimeInSeconds >= $beginSpan && $remainingTimeInSeconds < $endSpan)
                            )) {

                            $natureLabel = $this->formatService->nature($pack->getNature());
                            $counterByEndingSpan[$segmentEnd][$natureLabel] = $counterByEndingSpan[$segmentEnd][$natureLabel] ?? 0;
                            $counterByEndingSpan[$segmentEnd][$natureLabel]++;
                            $globalCounter++;
                        }
                    }
                });

            $graphData = $this->getObjectForTimeSpan($segments, function (int $beginSpan, int $endSpan) use (
                                                                                                            $entityManager,
                                                                                                            &$trackingDelayByFilters,
                                                                                                            $counterByEndingSpan,
                                                                                                            &$globalCounter) {
                return $counterByEndingSpan[$endSpan] ?? [];
            });
        }

        if (empty($graphData)) {
            $graphData = $this->getObjectForTimeSpan([], static fn() => 0);
        }

        $packToDisplay = $nextElementToDisplay['pack'] ?? null;
        $nextElementIdToDisplay = $packToDisplay->getId();
        $config['nextElement'] = $nextElementIdToDisplay;
        $component->setConfig($config);

        $totalToDisplay = $globalCounter ?: null;
        $locationToDisplay = $packToDisplay?->getLastOngoingDrop()?->getEmplacement() ?? null;
        $chartColors = Stream::from($naturesFilter)
            ->filter(fn (Nature $nature) => $nature->getColor())
            ->keymap(fn(Nature $nature) => [
                $this->formatService->nature($nature),
                $nature->getColor()
            ])
            ->toArray();

        $meter = $this->persistDashboardMeter($entityManager, $component, DashboardMeter\Chart::class);

        $meter
            ->setChartColors($chartColors)
            ->setData($graphData)
            ->setTotal($totalToDisplay ?: '-')
            ->setNextElement($packToDisplay->getCode() ?: '-')
            ->setLocation($locationToDisplay ?: '-');
    }
}
