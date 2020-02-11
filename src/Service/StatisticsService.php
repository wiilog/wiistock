<?php

namespace App\Service;

use App\Repository\DaysWorkedRepository;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\NonUniqueResultException;
use Exception;


class StatisticsService {

    private $daysWorkedRepository;

    public function __construct(DaysWorkedRepository $daysWorkedRepository) {
        $this->daysWorkedRepository = $daysWorkedRepository;
    }

    /**
     * Make assoc array. Assoc a date like "d/m" to a counter returned by given function
     * If table DaysWorked is no filled then the returned array is empty
     * Else we return an array with 7 counters
     * @param callable $getCounter (DateTime $dateMin, DateTime $dateMax) => integer
     * @return array ['d/m' => integer]
	 * @throws Exception
     */
    public function getDailyObjectsStatistics(callable $getCounter): array {
		$daysToReturn = [];
		$nbDaysToReturn = 7;
		$dayIndex = 0;

		$workedDaysLabels = $this->daysWorkedRepository->getLabelWorkedDays();

		while (count($daysToReturn) <= $nbDaysToReturn) {
			$dateToCheck = new DateTime("now - $dayIndex days", new DateTimeZone('Europe/Paris'));
			$dateDayLabel = strtolower($dateToCheck->format('l'));

			if (in_array($dateDayLabel, $workedDaysLabels)) {
				$daysToReturn[] = $dateToCheck;
			}

			$dayIndex++;
		}

		return array_reduce(
			array_reverse($daysToReturn),
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
	 * @throws Exception
	 */
    public function getWeeklyObjectsStatistics(callable $getCounter): array {
        $weekCountersToReturn = [];
        $nbWeeksToReturn = 5;

		$daysWorkedInWeek = $this->daysWorkedRepository->countDaysWorked();

        if ($daysWorkedInWeek > 0) {
            for ($weekIndex = ($nbWeeksToReturn - 1); $weekIndex >= 0; $weekIndex--) {
                $dateMin = new DateTime("monday $weekIndex weeks ago");
                $dateMin->setTime(0, 0, 0);
                $dateMax = new DateTime("sunday $weekIndex weeks ago");
                $dateMax->setTime(23, 59, 59);

                $dayKey = ('S' . $dateMin->format('W'));
                $weekCountersToReturn[$dayKey] = $getCounter($dateMin, $dateMax);
            }
        }

        return $weekCountersToReturn;
    }
}
