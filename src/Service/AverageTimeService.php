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

    public function updateAverageRequestTime(Type $requestType,
                                             EntityManagerInterface $entityManager,
                                             DateInterval $dateInterval) {
        $averageRequestTimeRepository = $entityManager->getRepository(AverageRequestTime::class);

        $currentAverageRequestTime = $averageRequestTimeRepository->findOneBy([
            'type' => $requestType
        ]);

        if (!$currentAverageRequestTime) {
            $currentAverageRequestTime = new AverageRequestTime();
            $currentAverageRequestTime
                ->setType($requestType)
                ->setTotal(1)
                ->setAverage($dateInterval);
            $entityManager
                ->persist($currentAverageRequestTime);
        } else {
            $currentAverageRequestTimeTotal = $currentAverageRequestTime->getTotal();
            $currentAverageRequestTimeAverage = $currentAverageRequestTime->getAverage();

            $currentAverageToInt = $this->dateIntervalToSeconds($currentAverageRequestTimeAverage);
            $dateDiffToAddToInt = $this->dateIntervalToSeconds($dateInterval);

            $newAverageToInt =
                (
                    ($currentAverageToInt * $currentAverageRequestTimeTotal) +
                    $dateDiffToAddToInt
                ) / ($currentAverageRequestTimeTotal + 1);

            $newAverageToDateInterval = $this->secondsToDateInterval($newAverageToInt);
            $currentAverageRequestTime
                ->setAverage($newAverageToDateInterval)
                ->setTotal($currentAverageRequestTimeTotal + 1);
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

        $days = ($seconds / self::SECONDS_IN_DAY);
        $remainingSeconds = ($seconds % self::SECONDS_IN_DAY);

        $hours = ($remainingSeconds / self::SECONDS_IN_HOUR);
        $remainingSeconds = ($seconds % self::SECONDS_IN_HOUR);

        $minutes = ($remainingSeconds / self::SECONDS_IN_MINUTE);
        $remainingSeconds = ($seconds % self::SECONDS_IN_MINUTE);

        $dateInterval = new DateInterval('P0Y');
        $dateInterval->d = $days;
        $dateInterval->h = $hours;
        $dateInterval->i = $minutes;
        $dateInterval->s = $remainingSeconds;

        return $dateInterval;
    }
}
