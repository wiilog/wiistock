<?php

namespace App\Service;

use App\Repository\DaysWorkedRepository;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\NonUniqueResultException;
use Exception;


class StatisticsService
{

    private $daysWorkedRepository;

    public function __construct(DaysWorkedRepository $daysWorkedRepository)
    {
        $this->daysWorkedRepository = $daysWorkedRepository;
    }

    /**
     * Make assoc array. Assoc a date like "d/m" to a counter returned by given function
     * If table DaysWorked is no filled then the returned array is empty
     * Else we return an array with 7 counters
     * @param callable $getCounter (DateTime $dateMin, DateTime $dateMax) => integer
     * @return array ['d/m' => integer]
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function getDailyObjectsStatistics(callable $getCounter): array
    {
        $dayToReturn = [];
        $nbDaysToReturn = 8;
        $dayIndex = 0;

        $daysWorkedInWeek = $this->daysWorkedRepository->countDaysWorked();
        if ($daysWorkedInWeek > 0) {
            while (count($dayToReturn) < $nbDaysToReturn) {
                $dateToCheck = new DateTime("now - $dayIndex days", new DateTimeZone('Europe/Paris'));
                $dayDateLabelStr = $dateToCheck->format('l');
                $dayWorked = $this->daysWorkedRepository->findByDayAndWorked($dayDateLabelStr);
                if ($dayWorked->getWorked() && !empty($dayWorked->getTimes())) {
                    $dayToReturn[] = $dateToCheck;
                }

                $dayIndex++;
            }
        }

        $dayToReturn = array_reverse($dayToReturn);

        return array_reduce(
            $dayToReturn,
            function (array $carry, DateTime $dateToCheck) use ($getCounter) {
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
     * @param callable $getCounter (DateTime $dateMin, DateTime $dateMax) => integer
     * @return array [('S' . weekNumber) => integer]
     * @throws NonUniqueResultException
     */
    public function getWeeklyObjectsStatistics(callable $getCounter): array
    {
        $weekCountersToReturn = [];
        $nbWeeksToReturn = 5;

        $daysWorkedInWeek = $this->daysWorkedRepository->countDaysWorked();
        if ($daysWorkedInWeek > 0) {
            for ($weekIndex = ($nbWeeksToReturn - 1); $weekIndex >= 0; $weekIndex--) {
                $dateMin = new DateTime("monday - $weekIndex weeks");
                $dateMin->setTime(0, 0, 0);
                $dateMax = new DateTime("sunday - $weekIndex weeks");
                $dateMax->setTime(23, 59, 59);

                $dayKey = ('S' . $dateMin->format('W'));
                $weekCountersToReturn[$dayKey] = $getCounter($dateMin, $dateMax);
            }
        }

        return $weekCountersToReturn;
    }

    /**
     * @param callable $getObject
     * @return array
     */
    public function getObjectForTimeSpan(callable $getObject): array
    {
        $timeSpanToObject = [];
        $timeSpans = [
            -1 => -1,
            0 => 1,
            1 => 6,
            6 => 12,
            12 => 24,
            24 => 36,
            36 => 48,
        ];
        foreach ($timeSpans as $timeBegin => $timeEnd) {
            $timeSpanToObject[$timeBegin === -1 ? "Retard" : ($timeBegin . "h-" . $timeEnd . 'h')] = $getObject($timeBegin, $timeEnd);
        }
        return $timeSpanToObject;
    }
}
