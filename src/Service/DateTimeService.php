<?php

namespace App\Service;

use App\Entity\WorkPeriod\WorkedDay;
use App\Service\WorkPeriod\WorkPeriodItem;
use App\Service\WorkPeriod\WorkPeriodService;
use DateInterval;
use DatePeriod;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use WiiCommon\Helper\Stream;

class DateTimeService {

    const ENG_TO_FR_MONTHS = [
        'Jan' => 'Janv.',
        'Feb' => 'Févr.',
        'Mar' => 'Mars',
        'Apr' => 'Avr.',
        'May' => 'Mai',
        'Jun' => 'Juin',
        'Jul' => 'Juil.',
        'Aug' => 'Août',
        'Sep' => 'Sept.',
        'Oct' => 'Oct.',
        'Nov' => 'Nov.',
        'Dec' => 'Déc.',
    ];

    const SECONDS_IN_DAY = 86400;
    const SECONDS_IN_HOUR = 3600;
    const SECONDS_IN_MINUTE = 60;

    const AVERAGE_TIME_REGEX = "^(?:[01]\d|2[0-3]):[0-5]\d$";

    public function __construct(private WorkPeriodService $workPeriodService) {}


    public function secondsToDateInterval(int $seconds): DateInterval {

        $days = (int)floor($seconds / self::SECONDS_IN_DAY);
        $remainingSeconds = ($seconds % self::SECONDS_IN_DAY);

        $hours = (int)floor($remainingSeconds / self::SECONDS_IN_HOUR);
        $remainingSeconds = ($seconds % self::SECONDS_IN_HOUR);

        $minutes = (int)floor($remainingSeconds / self::SECONDS_IN_MINUTE);
        $remainingSeconds = ($seconds % self::SECONDS_IN_MINUTE);

        $dateInterval = new DateInterval('P0Y');
        $dateInterval->d = $days;
        $dateInterval->h = $hours;
        $dateInterval->i = $minutes;
        $dateInterval->s = $remainingSeconds;

        return $dateInterval;
    }

    /**
     * @param string $time the time in HH:MM format
     * @return int the number of minutes
     */
    public function calculateMinuteFrom(string $time, string $regex = DateTimeService::AVERAGE_TIME_REGEX, string $separator = ":"): int
    {
        if (!preg_match("/". $regex ."/", $time)) {
            throw new \InvalidArgumentException("Le format de l'heure doit être HH{$separator}MM");
        }

        // separate hours and minutes
        list($hours, $minutes) = explode($separator, $time);

        $hours = (int) $hours;
        $minutes = (int) $minutes;

        // calculate minutes
        return $hours * DateTimeService::SECONDS_IN_MINUTE + $minutes;
    }

    public function calculateSecondsFrom(string $time, string $regex = DateTimeService::AVERAGE_TIME_REGEX, string $separator = ":"): int
    {
        $minutes = $this->calculateMinuteFrom($time, $regex, $separator);
        return $minutes * DateTimeService::SECONDS_IN_MINUTE;
    }

    function validateHoursFormat(string $hours): void {
        if (!preg_match("/^\d{2}:\d{2}-\d{2}:\d{2}(;\d{2}:\d{2}-\d{2}:\d{2})?$/", $hours)) {
            throw new RuntimeException("Le champ horaires doit être au format HH:MM-HH:MM;HH:MM-HH:MM ou HH:MM-HH:MM");
        }

        if (!preg_match("/^(0\d|1\d|2[0-3]):(0\d|[1-5]\d)-(0\d|1\d|2[0-3]):(0\d|[1-5]\d)(;(0\d|1\d|2[0-3]):(0\d|[1-5]\d)-(0\d|1\d|2[0-3]):(0\d|[1-5]\d))?$/", $hours)) {
            throw new RuntimeException("Les heures doivent être comprises entre 00:00 et 23:59");
        }
    }

    function validateTimeRange(string $start, string $end): void {
        $startTime = strtotime($start);
        $endTime = strtotime($end);

        if ($startTime >= $endTime) {
            throw new RuntimeException("L'heure de début doit être inférieure à l'heure de fin pour chaque créneau.");
        }
    }

    function checkForOverlaps(iterable $timeSlots): void {
        $timeRanges = [];

        foreach ($timeSlots as $timeSlot) {
            [$start, $end] = explode('-', $timeSlot);

            $this->validateTimeRange($start, $end);

            $startTime = strtotime($start);
            $endTime = strtotime($end);

            foreach ($timeRanges as $range) {
                if (($startTime < $range['end'] && $endTime > $range['start']) ||
                    ($range['start'] < $endTime && $range['end'] > $startTime)) {
                    throw new RuntimeException("Les créneaux horaires ne doivent pas se chevaucher.");
                }
            }

            $timeRanges[] = ['start' => $startTime, 'end' => $endTime];
        }
    }

    /**
     * @param WorkedDay[] $days
     */
    function processWorkingHours(array $workingHours, array &$days): void {
        foreach ($workingHours as $workingHour) {
            $hours = $workingHour["hours"] ?? null;
            $timeSlots = null;

            if ($hours) {
                $this->validateHoursFormat($hours);

                $timeSlots = Stream::explode(";", $hours)
                    ->sort();

                $this->checkForOverlaps($timeSlots);
            }

            if (!empty($workingHour['id'])) {
                $day = $days[$workingHour["id"]]
                    ->setTimes($timeSlots?->join(";"))
                    ->setWorked($workingHour["worked"]);

                if ($day->isWorked() && !$day->getTimes()) {
                    throw new RuntimeException("Le champ horaires de travail est requis pour les jours travaillés");
                } elseif (!$day->isWorked()) {
                    $day->setTimes(null);
                }
            }
        }

        $this->workPeriodService->clearCaches();
    }

    /**
     * Calculate worked period between the two input date.
     * The set of worked days and work free days are used to define this period.
     * Returned period is defined as a DateInterval
     */
    public function getWorkedPeriodBetweenDates(EntityManagerInterface $entityManager,
                                                DateTime               $date1,
                                                DateTime               $date2): DateInterval {
        $workedDays = $this->workPeriodService->get($entityManager, WorkPeriodItem::WORKED_DAYS);

        if ($date1 <= $date2) {
            $start = $date1;
            $end = $date2;
        }
        else {
            $start = $date2;
            $end = $date1;
        }

        if (!empty($workedDays)) {
            $period = new DatePeriod(
                $start,
                DateInterval::createFromDateString('1 day'),
                (clone $end)->setTime(23, 59, 59)
            );

            $dateCalculation = new DateTime();
            $dateCalculation2 = clone $dateCalculation;

            // foreach days between $start and $end
            foreach ($period as $day) {
                $dayLabel = strtolower($day->format('l'));

                if (isset($workedDays[$dayLabel])
                    && !$this->workPeriodService->isWorkFreeDay($entityManager, $day)) {

                    foreach ($workedDays[$dayLabel] as $period) {
                        [$startTimePeriod, $endTimePeriod] = $period;
                        [$startHour, $startMinute] = explode(':', $startTimePeriod);
                        [$endHour, $endMinute] = explode(':', $endTimePeriod);

                        $startPeriod = (clone $day)->setTime($startHour, $startMinute);
                        $endPeriod = (clone $day)->setTime($endHour, $endMinute);

                        if ($start >= $endPeriod
                            || $end <= $startPeriod) {
                            continue;
                        }

                        if ($start > $startPeriod) {
                            $startPeriod = $start;
                        }

                        if ($end < $endPeriod) {
                            $endPeriod = $end;
                        }
                        $intervalToAdd = $startPeriod->diff($endPeriod);

                        $dateCalculation->add($intervalToAdd);
                    }
                }
            }

            return $dateCalculation2->diff($dateCalculation);
        }

        return new DateInterval("P0Y");
    }

    public function convertDateIntervalToMilliseconds(DateInterval $interval): int {
        $dateTime1 = new DateTime();
        $dateTime2 = clone $dateTime1;
        $dateTime1->add($interval);

        return ($dateTime1->getTimestamp() - $dateTime2->getTimestamp()) * 1000;
    }

    public function convertSecondsToDateInterval(int $seconds): DateInterval {
        $dateTime1 = new DateTime();
        $dateTime2 = clone $dateTime1;
        $dateTime1->modify("+{$seconds} seconds");

        return $dateTime1 < $dateTime2
            ? $dateTime1->diff($dateTime2)
            : $dateTime2->diff($dateTime1);
    }

    /**
     * Add a worked period to an input date.
     * The set of worked days and work free days are used to define this period.
     * Returned date is a copy and the given date is a clone.
     */
    public function addWorkedPeriodToDateTime(EntityManagerInterface $entityManager,
                                              DateTime               $startDate,
                                              DateInterval           $workedInterval): ?DateTime {
        $workedSegments = $this->workPeriodService->get($entityManager, WorkPeriodItem::WORKED_DAYS);

        $finalDate = clone $startDate;
        $finalInterval = clone $workedInterval;

        // prevent execution if worked days settings empty
        if (empty($workedSegments)) {
            return null;
        }

        $dateIntervalMillisecond = $this->convertDateIntervalToMilliseconds($finalInterval);
        if ($dateIntervalMillisecond === 0) {
            return $finalDate;
        }

        $day = strtolower($startDate->format('l'));


        if (!$this->workPeriodService->isWorkFreeDay($entityManager, $startDate)) {

            // loop over worked segment on current $date
            foreach ($workedSegments[$day] as $workedSegment) {
                [$workedSegmentStart, $workedSegmentEnd] = $workedSegment;

                [$workedSegmentEndHour, $workedSegmentEndMinute] = explode(':', $workedSegmentEnd);
                $currentSegmentEnd = (clone $startDate)->setTime($workedSegmentEndHour, $workedSegmentEndMinute);

                // ignore if segment is before start
                if ($startDate >= $currentSegmentEnd) {
                    continue;
                }

                [$workedSegmentStartHour, $workedSegmentStartMinute] = explode(':', $workedSegmentStart);
                $currentSegmentStart = (clone $startDate)->setTime($workedSegmentStartHour, $workedSegmentStartMinute);

                // if start date is before currentSegment the returned hour is at least the start date or more recent
                // else it's the start date
                if ($startDate < $currentSegmentStart) {
                    $finalDate = $currentSegmentStart;
                }

                /// interval segment in second
                $lower = $this->calculateSecondsFrom($finalDate->format("H:i"));
                $upper = $this->calculateSecondsFrom($workedSegment[1]);
                $currentSegmentSecondDuration = $upper - $lower;

                $intervalDiff = floor($this->convertDateIntervalToMilliseconds($finalInterval) / 1000) - $currentSegmentSecondDuration;

                // if <= 0 then the returned datetime should be on current segment
                if ($intervalDiff <= 0) {
                    return $finalDate->add($finalInterval);
                }

                // else we set the final interval
                $finalInterval = $this->secondsToDateInterval($intervalDiff);
            }
        }

        // we get the next day worked according to worked days settings & work free days settings
        $workedDays = Stream::keys($workedSegments);
        $currentDayKey = $workedDays
            ->findKey(static fn(string $workedDay) => $workedDay === $day) ?? -1;

        do {
            $currentDayKey = ($currentDayKey + 1) % $workedDays->count();
            $nextDay = $workedDays->offsetGet($currentDayKey);

            $finalDate
                ->modify("next $nextDay")
                ->setTime(0, 0);
        }
        while ($this->workPeriodService->isWorkFreeDay($entityManager, $finalDate));

        return $this->addWorkedPeriodToDateTime($entityManager, $finalDate, $finalInterval);
    }

    /**
     * Subtract the given delay in seconds with diff in seconds between the two given dates.
     *
     * @return array{
     *     delay: int,
     *     intervalTime: int,
     * }
     */
    public function subtractDelay(int      $initialDelay,
                                  DateTime $begin,
                                  DateTime $end): array {
        $intervalTime = $end->getTimestamp() - $begin->getTimestamp();
        return [
            "delay" => $initialDelay - $intervalTime,
            "intervalTime" => $intervalTime,
        ];
    }
}
