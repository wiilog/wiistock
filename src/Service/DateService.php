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

    public function calculateMinuteFrom(string $time): int
    {
        // Vérifier si le format est correct
        if (!preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $time)) {
            throw new \InvalidArgumentException("Le format de l'heure doit être HH:MM");
        }

        // Séparer les heures et les minutes
        list($hours, $minutes) = explode(':', $time);

        // Convertir en entiers
        $hours = (int) $hours;
        $minutes = (int) $minutes;

        // Calculer le total des minutes
        return $hours * DateService::SECONDS_IN_MINUTE + $minutes;
    }
}
