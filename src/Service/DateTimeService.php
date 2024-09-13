<?php

namespace App\Service;

use App\Entity\DaysWorked;
use App\Entity\WorkFreeDay;
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

    private array $cache = [];

    public function dateIntervalToSeconds(DateInterval $dateInterval): int {
        return
            ($dateInterval->d * self::SECONDS_IN_DAY) +
            ($dateInterval->h * self::SECONDS_IN_HOUR) +
            ($dateInterval->i * self::SECONDS_IN_MINUTE) +
            ($dateInterval->s);
    }

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

    public function intervalToStr(DateInterval $delay): string {
        return (
            ($delay->d ? "{$delay->d}j" : '')
            . ($delay->h ? " {$delay->h}h" : '')
            . ($delay->i ? " {$delay->i}m" : '')
            . ($delay->s ? " {$delay->s}s" : '')
        );
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

    function checkForOverlaps(array $timeSlots): void {
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

    function processWorkingHours(array $workingHours, array &$days): void {
        foreach ($workingHours as $workingHour) {
            $hours = $workingHour["hours"] ?? null;

            if ($hours) {
                $this->validateHoursFormat($hours);

                $timeSlots = explode(';', $hours);
                $this->checkForOverlaps($timeSlots);
            }

            if (!empty($workingHour['id'])) {
                $day = $days[$workingHour["id"]]
                    ->setTimes($hours)
                    ->setWorked($workingHour["worked"]);

                if ($day->isWorked() && !$day->getTimes()) {
                    throw new RuntimeException("Le champ horaires de travail est requis pour les jours travaillés");
                } elseif (!$day->isWorked()) {
                    $day->setTimes(null);
                }
            }
        }
    }

    public function isWorkFreeDay(EntityManagerInterface $entityManager,
                                  DateTime $day): bool {
        $comparisonFormat = 'Y-m-d';
        $workFreeDays = $this->getCache($entityManager, "workFreeDays");

        $formattedDay = $day->format($comparisonFormat);

        foreach ($workFreeDays as $workFreeDay) {
            $currentFormattedDay = $workFreeDay->format($comparisonFormat);
            return $currentFormattedDay === $formattedDay;
        }

        return false;
    }

    public function intervalToHourAndMinStr(DateInterval $delay): string {
        $hours = sprintf('%02d', ($delay->d*24) + $delay->h);
        $minutes = sprintf('%02d', $delay->i);
        return "{$hours}h{$minutes}";
    }

    // TODO WIIS-11848
    public function getWorkedPeriodBetweenDates(EntityManagerInterface $entityManager,
                                                DateTime               $date1,
                                                DateTime               $date2): DateInterval {
        $workedDays = $this->getCache($entityManager, "workedDays");

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
                    && !$this->isWorkFreeDay($entityManager, $day)) {

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

    private function getCache(EntityManagerInterface $entityManager, string $key): mixed {
        if (!isset($this->cache[$key])) {
            if ($key === "workedDays") {
                $workedDaysRepository = $entityManager->getRepository(DaysWorked::class);
                $workedDays = $workedDaysRepository->findAll();
                $this->cache[$key] = Stream::from($workedDays)
                    ->keymap(fn(DaysWorked $dayWorked) => (
                    $dayWorked->isWorked()
                        ? [
                        $dayWorked->getDay(),
                        $this->timePeriodToArray($dayWorked->getTimes())
                    ]
                        : null
                    ));
            }
            else if ($key === "workFreeDays") {
                $workedDaysRepository = $entityManager->getRepository(WorkFreeDay::class);
                $this->cache[$key] = $workedDaysRepository->getWorkFreeDaysToDateTime();
            }
        }

        return $this->cache[$key] ?? null;
    }

    /**
     * @return array{string, string}[]
     */
    private function timePeriodToArray(string $periods): array {
        return Stream::explode(";", $periods)
            ->map(static fn(string $period) => explode("-", $period))
            ->toArray();
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

        return $dateTime1->diff($dateTime2);
    }
}
