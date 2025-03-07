<?php

namespace App\Service\Dashboard;

use App\Entity\Arrivage;
use App\Entity\ArrivalHistory;
use App\Entity\Dashboard;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\Emplacement;
use App\Entity\LocationCluster;
use App\Entity\ReceiptAssociation;
use App\Entity\Setting;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingEvent;
use App\Entity\Wiilock;
use App\Service\Dashboard\DashboardComponentGenerator\ActiveReferenceAlertsComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\ArrivalsAndPacksComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\ArrivalsEmergenciesComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\CarrierTrackingComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\DailyDeliveryOrdersComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\DailyDispatchesComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\DailyHandlingIndicatorComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\DailyHandlingOrOperationsComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\DailyProductionsComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\DisputeToTreatComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\DropOffDistributedPacksComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\EntriesToHandleComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\HandlingTrackingComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\MonetaryReliabilityComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\MonetaryReliabilityGraphComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\MonetaryReliabilityIndicatorComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\OngoingPackComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\PackToTreatFromComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\RequestsOrdersToTreatComponentGenerator;
use App\Service\Dashboard\MultipleDashboardComponentGenerator\DashboardComponentsWithDelayGenerator;
use App\Service\Dashboard\MultipleDashboardComponentGenerator\LatePackComponentGenerator;
use App\Service\FormatService;
use App\Service\TranslationService;
use App\Service\WorkPeriod\WorkPeriodItem;
use App\Service\WorkPeriod\WorkPeriodService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use WiiCommon\Helper\Stream;

class DashboardService {

    public const DEFAULT_DAILY_REQUESTS_SCALE = 5;
    public const DEFAULT_HANDLING_TRACKING_SCALE = 7;
    public const DEFAULT_WEEKLY_REQUESTS_SCALE = 7;

    public const DAILY_PERIOD_NEXT_DAYS = 'nextDays';
    public const DAILY_PERIOD_PREVIOUS_DAYS = 'previousDays';

    public const TRACKING_EVENT_TO_TREATMENT_DELAY_TYPE = [
        Setting::TREATMENT_DELAY_IN_PROGRESS =>  [null, TrackingEvent::START],
        Setting::TREATMENT_DELAY_ON_HOLD => [TrackingEvent::PAUSE],
        Setting::TREATMENT_DELAY_BOTH => [null, TrackingEvent::START, TrackingEvent::PAUSE],
    ];

    public const DASHBOARD_ERROR_MESSAGE = "Erreur : Impossible de charger le composant";

    public function __construct(
        private WorkPeriodService       $workPeriodService,
        private TranslationService      $translationService,
        private EntityManagerInterface  $entityManager,
        private FormatService           $formatService,
    ) {}

    public function refreshDate(EntityManagerInterface $entityManager): string {
        $wiilockRepository = $entityManager->getRepository(Wiilock::class);
        $lock = $wiilockRepository->findOneBy(["lockKey" => Wiilock::DASHBOARD_FED_KEY]);

        return $lock
            ? $this->formatService->datetime($lock->getUpdateDate())
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

            $rows[$dayCounterKey]['conform'] = $arrivalHistory?->getConformRate();
        }
        return [
            'data' => $rows,
            'firstDay' => date("d/m/y", $firstDayTime),
            'firstDayData' => date("d/m/Y", $firstDayTime),
            'lastDay' => date("d/m/y", $lastDayTime),
            'lastDayData' => date("d/m/Y", $lastDayTime)
        ];
    }

    /**
     * @param array $steps
     * @param callable $getObject
     * @return array
     */
    public function getObjectForTimeSpan(array $steps, callable $getObject, string $meterKey): array {
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

        $segmentUnit = match ($meterKey) {
            Dashboard\ComponentType::ENTRIES_TO_HANDLE_BY_TRACKING_DELAY => "min",
            default => 'h',
        };

        foreach ($timeSpans as $timeBegin => $timeEnd) {
            $key = $timeBegin === -1
                ? $this->translationService->translate("Dashboard", "Retard", false)
                : ($timeEnd === 1
                    ? $this->translationService->translate("Dashboard", "Moins d'{1}", [
                        1 => "1$segmentUnit"
                    ], false)
                    : ($timeBegin . "$segmentUnit-" . $timeEnd . $segmentUnit));
            $timeSpanToObject[$key] = $getObject($timeBegin, $timeEnd);
        }
        return $timeSpanToObject;
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Dashboard\Component $component
     * @param string $class
     * @return DashboardMeter\Indicator|DashboardMeter\Chart
     */
    public function persistDashboardMeter(EntityManagerInterface $entityManager,
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
     */
    public function getDailyObjectsStatistics(EntityManagerInterface $entityManager,
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
     * Make assoc array. Assoc a date like ('S' . weekNumber) to a counter returned by given function
     * If table DaysWorked is no filled then the returned array is empty
     * Else we return an array with 5 counters
     * @param EntityManagerInterface $entityManager
     * @param int $nbWeeksToReturn
     * @param callable $getCounter (DateTime $dateMin, DateTime $dateMax) => integer
     * @return array [('S' . weekNumber) => $getCounter return]
     */
    public function getWeeklyObjectsStatistics(EntityManagerInterface $entityManager,
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

    public function getGeneratorClass(string $meterKey): ?string
    {
        return match ($meterKey) {
            Dashboard\ComponentType::ONGOING_PACKS_WITH_TRACKING_DELAY, Dashboard\ComponentType::ENTRIES_TO_HANDLE_BY_TRACKING_DELAY => DashboardComponentsWithDelayGenerator::class,
            Dashboard\ComponentType::ONGOING_PACKS => OngoingPackComponentGenerator::class,
            Dashboard\ComponentType::DAILY_HANDLING_INDICATOR => DailyHandlingIndicatorComponentGenerator::class,
            Dashboard\ComponentType::DROP_OFF_DISTRIBUTED_PACKS => DropOffDistributedPacksComponentGenerator::class,
            Dashboard\ComponentType::CARRIER_TRACKING => CarrierTrackingComponentGenerator::class,
            Dashboard\ComponentType::DAILY_ARRIVALS_AND_PACKS, Dashboard\ComponentType::WEEKLY_ARRIVALS_AND_PACKS => ArrivalsAndPacksComponentGenerator::class,
            Dashboard\ComponentType::ENTRIES_TO_HANDLE => EntriesToHandleComponentGenerator::class,
            Dashboard\ComponentType::PACK_TO_TREAT_FROM => PackToTreatFromComponentGenerator::class,
            Dashboard\ComponentType::ARRIVALS_EMERGENCIES_TO_RECEIVE, Dashboard\ComponentType::DAILY_ARRIVALS_EMERGENCIES => ArrivalsEmergenciesComponentGenerator::class,
            Dashboard\ComponentType::ACTIVE_REFERENCE_ALERTS => ActiveReferenceAlertsComponentGenerator::class,
            Dashboard\ComponentType::MONETARY_RELIABILITY_GRAPH => MonetaryReliabilityGraphComponentGenerator::class,
            Dashboard\ComponentType::MONETARY_RELIABILITY_INDICATOR => MonetaryReliabilityIndicatorComponentGenerator::class,
            Dashboard\ComponentType::REFERENCE_RELIABILITY => MonetaryReliabilityComponentGenerator::class,
            Dashboard\ComponentType::DAILY_DISPATCHES => DailyDispatchesComponentGenerator::class,
            Dashboard\ComponentType::DAILY_PRODUCTION => DailyProductionsComponentGenerator::class,
            Dashboard\ComponentType::DAILY_HANDLING, Dashboard\ComponentType::DAILY_OPERATIONS => DailyHandlingOrOperationsComponentGenerator::class,
            Dashboard\ComponentType::DAILY_DELIVERY_ORDERS => DailyDeliveryOrdersComponentGenerator::class,
            Dashboard\ComponentType::REQUESTS_TO_TREAT, Dashboard\ComponentType::ORDERS_TO_TREAT => RequestsOrdersToTreatComponentGenerator::class,
            Dashboard\ComponentType::DISPUTES_TO_TREAT => DisputeToTreatComponentGenerator::class,
            Dashboard\ComponentType::HANDLING_TRACKING => HandlingTrackingComponentGenerator::class,
            Dashboard\ComponentType::LATE_PACKS => LatePackComponentGenerator::class,
            default => null,
        };
    }
}
