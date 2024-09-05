<?php

namespace App\Service;

use DateInterval;
use DatePeriod;
use DateTime;
use Exception;
use RuntimeException;

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
    public function calculateMinuteFrom(string $time): int
    {
        if (!preg_match("/". DateTimeService::AVERAGE_TIME_REGEX ."/", $time)) {
            throw new \InvalidArgumentException("Le format de l'heure doit être HH:MM");
        }

        // separate hours and minutes
        list($hours, $minutes) = explode(':', $time);

        $hours = (int) $hours;
        $minutes = (int) $minutes;

        // calculate minutes
        return $hours * DateTimeService::SECONDS_IN_MINUTE + $minutes;
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
    public function isDayInArray(DateTime $day, array $daysToCheck): bool
    {
        $isDayInArray = false;
        $dayIndex = 0;
        $daysToCheckCount = count($daysToCheck);
        $comparisonFormat = 'Y-m-d';

        $formattedDay = $day->format($comparisonFormat);
        while (!$isDayInArray && $dayIndex < $daysToCheckCount) {
            $currentFormattedDay = $daysToCheck[$dayIndex]->format($comparisonFormat);
            if ($currentFormattedDay === $formattedDay) {
                $isDayInArray = true;
            } else {
                $dayIndex++;
            }
        }
        return $isDayInArray;
    }

    /**
     * @param array $workedDays [<english day label ('l' format)> => <nb time worked>]
     * @param DateTime $initialDate
     * @param DateTime[] $workFreeDays
     * @return DateInterval
     * @throws Exception
     */
    public function getIntervalFromDate(array $workedDays, DateTime $initialDate, array $workFreeDays): DateInterval
    {
        if (count($workedDays) > 0) {
            $now = new DateTime("now");
            if ($now->getTimezone()->getName() !== $initialDate->getTimezone()->getName()) {
                $currentHours = $now->format('H');
                $currentMinutes = $now->format('i');
                $now->setTimezone($initialDate->getTimezone());
                $now->setTime((int)$currentHours, (int)$currentMinutes);
            }
            $nowIncluding = (clone $now)->setTime(23, 59, 59);
            $interval = DateInterval::createFromDateString('1 day');
            $period = new DatePeriod($initialDate, $interval, $nowIncluding);

            $periodsWorked = [];
            // pour chaque jour entre la date initiale et aujourd'hui, minimum un tour de boucle
            /** @var DateTime $day */
            foreach ($period as $day) {
                $dayLabel = strtolower($day->format('l'));
                if (isset($workedDays[$dayLabel])
                    && !$this->isDayInArray($day, $workFreeDays)) {
                    $periodsWorked = array_merge(
                        $periodsWorked,
                        array_map(
                            function (string $timePeriod) use ($now, $day, $initialDate) {
                                // we calculate delay between two given times
                                $times = explode('-', $timePeriod);

                                $time1 = explode(':', $times[0]);
                                $begin = (clone $day)->setTime($time1[0], $time1[1], 0);

                                $time2 = explode(':', $times[1]);
                                $end = (clone $day)->setTime($time2[0], $time2[1], 0);
                                if (($end < $initialDate) || ($now < $begin)) {
                                    $calculatedInterval = new DateInterval('P0Y');
                                } else {
                                    // si la date initiale est dans la fourchette => devient la date de begin
                                    if ($begin < $initialDate && $initialDate <= $end) {
                                        $begin = $initialDate;
                                    }

                                    // si le DateTime 'now'  est dans la fourchette => devient la date de end
                                    if ($begin <= $now &&
                                        $now < $end) {
                                        $end = $now;
                                    }
                                    $calculatedInterval = $begin->diff($end);
                                }
                                return $calculatedInterval;
                            },
                            explode(';', $workedDays[$dayLabel])
                        )
                    );
                }
            }

            // on fait la somme de toutes les périodes calculées
            $dateInterval = array_reduce(
                $periodsWorked,
                function (?DateInterval $carry, DateInterval $interval) {
                    $f = ($carry->f + $interval->f);
                    $s = ($carry->s + $interval->s) + intval($f / 1000);
                    $i = ($carry->i + $interval->i) + intval($s / 60);
                    $h = ($carry->h + $interval->h) + intval($i / 60);

                    $newDateInterval = new DateInterval('P0Y');
                    $newDateInterval->h = $h;
                    $newDateInterval->i = $i % 60;
                    $newDateInterval->s = $s % 60;
                    $newDateInterval->f = $f % 1000;
                    return $newDateInterval;
                },
                new DateInterval('P0Y')
            );
        }
        else {
            // age null
            $dateInterval = new DateInterval('P0Y');
        }
        return $dateInterval;
    }
    public function calculateSecondsFrom(string $time, string $regex = self::AVERAGE_TIME_REGEX, string $separator = ":"): int
    {
        $minutes = $this->calculateMinuteFrom($time, $regex, $separator);
        return $minutes * self::SECONDS_IN_MINUTE;
    }

    public function intervalToHourAndMinStr(DateInterval $delay): string {
        return (
            ($delay->h
                ? "{$delay->h}h"
                : '')
            . ($delay->i
                ? (strlen($delay->i) === 1
                    ? "0{$delay->i}"
                    : "$delay->i")
                : '')
        );
    }

}
