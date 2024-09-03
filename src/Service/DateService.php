<?php

namespace App\Service;

use DateInterval;

class DateService
{

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

    /**
     * @param string $time the time in HH:MM format
     * @return int the number of minutes
     */
    public function calculateMinuteFrom(string $time, string $regex = DateService::AVERAGE_TIME_REGEX, string $separator = ":"): int
    {
        if (!preg_match("/". $regex ."/", $time)) {
            throw new \InvalidArgumentException("Le format de l'heure doit être HH{$separator}MM");
        }

        // separate hours and minutes
        list($hours, $minutes) = explode($separator, $time);

        $hours = (int) $hours;
        $minutes = (int) $minutes;

        // calculate minutes
        return $hours * DateService::SECONDS_IN_MINUTE + $minutes;
    }

    public function calculateSecondsFrom(string $time, string $regex = DateService::AVERAGE_TIME_REGEX, string $separator = ":"): int
    {
        $minutes = $this->calculateMinuteFrom($time, $regex, $separator);
        return $minutes * DateService::SECONDS_IN_MINUTE;
    }
}
