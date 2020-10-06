<?php

namespace App\Service;

use App\Entity\AverageRequestTime;
use App\Entity\Type;
use DateInterval;
use Doctrine\ORM\EntityManagerInterface;
use DoctrineExtensions\Query\Mysql\Date;

class AverageTimeService
{

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
}
