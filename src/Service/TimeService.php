<?php

namespace App\Service;

use DateInterval;
use DatePeriod;
use DateTime;
use Exception;

class TimeService {
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
}
