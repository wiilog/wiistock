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

    public function treatPack(Pack   $pack,
                               int    $remainingTimeInSeconds,
                               array  $customSegments,
                               array  &$counterByEndingSpan,
                               int    &$globalCounter,
                               ?array &$nextElementToDisplay,
                               ?Pack  $packToGetNature = null): void {
        // we save pack with the smallest tracking delay
        if (!isset($nextElementToDisplay)
            || ($remainingTimeInSeconds < $nextElementToDisplay['remainingTimeInSeconds'])) {
            $nextElementToDisplay = [
                'remainingTimeInSeconds' => $remainingTimeInSeconds,
                'pack' => $pack,
            ];
        }

        foreach ($customSegments as $segmentEnd) {
            $endSpan = match($segmentEnd) {
                -1 => -1,
                default => $segmentEnd * 60,
            };

            if ($remainingTimeInSeconds < $endSpan) {
                $packToGetNature ??= $pack;
                $natureLabel = $this->formatService->nature($packToGetNature->getNature());

                $counterByEndingSpan[$segmentEnd][$natureLabel] ??= 0;
                $counterByEndingSpan[$segmentEnd][$natureLabel]++;
                $globalCounter++;

                break;
            }
        }
    }
}
