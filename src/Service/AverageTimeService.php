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

    public function addRequestTime(Type $requestType,
                                             EntityManagerInterface $entityManager,
                                             DateInterval $dateInterval) {
        $averageRequestTimeRepository = $entityManager->getRepository(AverageRequestTime::class);

        $averageRequestTime = $averageRequestTimeRepository->findOneBy([
            'type' => $requestType
        ]);

        if (!$averageRequestTime) {
            $averageRequestTime = new AverageRequestTime();
            $averageRequestTime
                ->setType($requestType)
                ->setTotal(1)
                ->setAverage($dateInterval);
            $entityManager
                ->persist($averageRequestTime);
        } else {
            $total = $averageRequestTime->getTotal();
            $average = $averageRequestTime->getAverage();

            $averageToInt = $this->dateIntervalToSeconds($average);
            $dateDiffToAddToInt = $this->dateIntervalToSeconds($dateInterval);

            $newAverageToInt =
                (
                    ($averageToInt * $total) +
                    $dateDiffToAddToInt
                ) / ($total + 1);

            $newAverageToDateInterval = $this->secondsToDateInterval($newAverageToInt);
            $averageRequestTime
                ->setAverage($newAverageToDateInterval)
                ->setTotal($total + 1);
        }
    }


    private function dateIntervalToSeconds(DateInterval $dateInterval): int {
        return
            ($dateInterval->d * self::SECONDS_IN_DAY) +
            ($dateInterval->h * self::SECONDS_IN_HOUR) +
            ($dateInterval->i * self::SECONDS_IN_MINUTE) +
            ($dateInterval->s);
    }

    private function secondsToDateInterval(int $seconds): DateInterval {

        $days = floor($seconds / self::SECONDS_IN_DAY);
        $remainingSeconds = ($seconds % self::SECONDS_IN_DAY);

        $hours = floor($remainingSeconds / self::SECONDS_IN_HOUR);
        $remainingSeconds = ($seconds % self::SECONDS_IN_HOUR);

        $minutes = floor($remainingSeconds / self::SECONDS_IN_MINUTE);
        $remainingSeconds = ($seconds % self::SECONDS_IN_MINUTE);

        $dateInterval = new DateInterval('P0Y');
        $dateInterval->d = $days;
        $dateInterval->h = $hours;
        $dateInterval->i = $minutes;
        $dateInterval->s = $remainingSeconds;

        return $dateInterval;
    }
}
