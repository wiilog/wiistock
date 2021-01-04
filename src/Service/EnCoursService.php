<?php


namespace App\Service;


use App\Entity\Pack;
use App\Entity\DaysWorked;
use App\Entity\WorkFreeDay;
use DateInterval;
use DatePeriod;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Exception;


class EnCoursService
{
    /**
     * @var EntityManagerInterface $entityManager
     */
    private $entityManager;

    private const AFTERNOON_FIRST_HOUR_INDEX = 4;
    private const AFTERNOON_LAST_HOUR_INDEX = 6;
    private const AFTERNOON_FIRST_MINUTE_INDEX = 5;
    private const AFTERNOON_LAST_MINUTE_INDEX = 7;
    private const MORNING_FIRST_HOUR_INDEX = 0;
    private const MORNING_LAST_HOUR_INDEX = 2;
    private const MORNING_FIRST_MINUTE_INDEX = 1;
    private const MORNING_LAST_MINUTE_INDEX = 3;

    /**
     * EnCoursService constructor.
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param DaysWorked $daysWorked the day to get the times on
     * @return array containing each minutes and hour for each points on the day
     * ex : 8:00-12:00;14:00-18:00 => [8, 0, 12, 0, 14, 0, 18, 0]
     */
    public function getTimeArrayForDayWorked(DaysWorked $daysWorked): array
    {
        $daysPeriod = explode(';', $daysWorked->getTimes());

        if (empty($daysPeriod[1])) return [];

        $afternoon = $daysPeriod[1];
        $morning = $daysPeriod[0];

        $afternoonLastHourAndMinute = explode('-', $afternoon)[1];
        $afternoonFirstHourAndMinute = explode('-', $afternoon)[0];
        $morningLastHourAndMinute = explode('-', $morning)[1];
        $morningFirstHourAndMinute = explode('-', $morning)[0];

        $afternoonLastHour = intval(explode(':', $afternoonLastHourAndMinute)[0]);
        $afternoonLastMinute = intval(explode(':', $afternoonLastHourAndMinute)[1]);
        $afternoonFirstHour = intval(explode(':', $afternoonFirstHourAndMinute)[0]);
        $afternoonFirstMinute = intval(explode(':', $afternoonFirstHourAndMinute)[1]);

        $morningLastHour = intval(explode(':', $morningLastHourAndMinute)[0]);
        $morningLastMinute = intval(explode(':', $morningLastHourAndMinute)[1]);
        $morningFirstHour = intval(explode(':', $morningFirstHourAndMinute)[0]);
        $morningFirstMinute = intval(explode(':', $morningFirstHourAndMinute)[1]);

        return [
            $morningFirstHour, $morningFirstMinute, $morningLastHour, $morningLastMinute,
            $afternoonFirstHour, $afternoonFirstMinute, $afternoonLastHour, $afternoonLastMinute
        ];
    }

    /**
     * @param DaysWorked $daysWorked the day to get total time on
     * @return int the total day time including the break;
     */
    public function getTotalTimeInDay(DaysWorked $daysWorked): int
    {
        $timeArray = $this->getTimeArrayForDayWorked($daysWorked);
        return (empty($timeArray) ? 0 : (
                ($timeArray[self::AFTERNOON_LAST_HOUR_INDEX] * 60) + $timeArray[self::AFTERNOON_LAST_MINUTE_INDEX])
            -
            (($timeArray[self::MORNING_FIRST_HOUR_INDEX] * 60) + $timeArray[self::MORNING_FIRST_MINUTE_INDEX]));
    }

    /**
     * @param DaysWorked $daysWorked
     * @return int the total day worked time, which is the total time minus the break time
     */
    public function getTimeWorkedDuringThisDayForDayWorked(DaysWorked $daysWorked): int
    {
        return $this->getTotalTimeInDay($daysWorked) - $this->getTimeBreakThisDayForDayWorked($daysWorked);
    }

    /**
     * @param DaysWorked $daysWorked the day worked
     * @return int the total break time for the param
     */
    public function getTimeBreakThisDayForDayWorked(DaysWorked $daysWorked): int
    {
        $timeArray = $this->getTimeArrayForDayWorked($daysWorked);
        return (empty($timeArray) ? 0 : (
                ($timeArray[self::AFTERNOON_FIRST_HOUR_INDEX] * 60) + $timeArray[self::AFTERNOON_FIRST_MINUTE_INDEX])
            -
            (($timeArray[self::MORNING_LAST_HOUR_INDEX] * 60) + $timeArray[self::MORNING_LAST_MINUTE_INDEX]));
    }

    /**
     * @param DateInterval $movementAge
     * @param string|null $dateMaxTime
     * @return array containing :
     * countDownLateTimespan => not defined or the time remaining to be late
     * ageTimespan => the time stayed on the emp
     */
    public function getTimeInformation(DateInterval $movementAge, ?string $dateMaxTime)
    {
        $ageTimespan = (
            ($movementAge->h * 60 * 60 * 1000) + // hours in milliseconds
            ($movementAge->i * 60 * 1000) + // minutes in milliseconds
            ($movementAge->s * 1000) + // seconds in milliseconds
            ($movementAge->f)
        );
        $information = [
            'ageTimespan' => $ageTimespan
        ];
        if ($dateMaxTime) {
            $explodeAgeTimespan = explode(':', $dateMaxTime);
            $maxTimeHours = intval($explodeAgeTimespan[0]);
            $maxTimeMinutes = intval($explodeAgeTimespan[1]);
            $maxTimespan = (
                ($maxTimeHours * 60 * 60 * 1000) + // hours in milliseconds
                ($maxTimeMinutes * 60 * 1000)      // minutes in milliseconds
            );
            $information['countDownLateTimespan'] = ($maxTimespan - $ageTimespan);
        }
        return $information;
    }

    /**
     * @throws Exception
     */
    public function getLastEnCoursForLate()
    {
        return $this->getEnCours([], [], true);
    }

    /**
     * @param $locations
     * @param array $natures
     * @param bool $onlyLate
     * @param int|null $limitOnlyLate
     * @return array
     * @throws Exception
     */
    public function getEnCours($locations, array $natures = [], bool $onlyLate = false, ?int $limitOnlyLate = 100): array
    {
        $packRepository = $this->entityManager->getRepository(Pack::class);
        $dropsCounter = 0;
        $workedDaysRepository = $this->entityManager->getRepository(DaysWorked::class);
        $workFreeDaysRepository = $this->entityManager->getRepository(WorkFreeDay::class);
        $daysWorked = $workedDaysRepository->getWorkedTimeForEachDaysWorked();
        $freeWorkDays = $workFreeDaysRepository->getWorkFreeDaysToDateTime();
        $emplacementInfo = [];

        if ($onlyLate) {
            $maxQueryResultLength = 200;
            while (count($emplacementInfo) < $limitOnlyLate) {
                $oldestDrops = [];
                $oldestDrops[] = $packRepository->getCurrentPackOnLocations(
                    $locations,
                    $natures,
                    false,
                    'colis.code, lastDrop.datetime, emplacement.dateMaxTime, emplacement.label',
                    $maxQueryResultLength,
                    $dropsCounter,
                    'asc',
                    true
                );
                $oldestDrops = $oldestDrops[0];
                if (empty($oldestDrops)) {
                    break;
                }
                foreach ($oldestDrops as $oldestDrop) {
                    $dateMvt = $oldestDrop['datetime'];
                    $movementAge = $this->getTrackingMovementAge($daysWorked, $dateMvt, $freeWorkDays);
                    $dateMaxTime = $oldestDrop['dateMaxTime'];
                    $timeInformation = $this->getTimeInformation($movementAge, $dateMaxTime);
                    $isLate = $timeInformation['countDownLateTimespan'] < 0;
                    if ($isLate) {
                        $emplacementInfo[] = [
                            'colis' => $oldestDrop['code'],
                            'delay' => $timeInformation['ageTimespan'],
                            'date' => $dateMvt->format('d/m/Y H:i:s'),
                            'late' => $isLate,
                            'emp' => $oldestDrop['label']
                        ];
                    }

                    if (count($emplacementInfo) >= $limitOnlyLate) {
                        break; // break foreach
                    }
                }
                $dropsCounter += $maxQueryResultLength;
            }
        } else {
            $oldestDrops[] = $packRepository->getCurrentPackOnLocations(
                $locations,
                $natures,
                false,
                'colis.code, lastDrop.datetime, emplacement.dateMaxTime, emplacement.label'
            );
            $oldestDrops = $oldestDrops[0];
            foreach ($oldestDrops as $oldestDrop) {
                $dateMvt = $oldestDrop['datetime'];
                $movementAge = $this->getTrackingMovementAge($daysWorked, $dateMvt, $freeWorkDays);
                $dateMaxTime = $oldestDrop['dateMaxTime'];
                $timeInformation = $this->getTimeInformation($movementAge, $dateMaxTime);
                $isLate = $timeInformation['countDownLateTimespan'] < 0;
                $emplacementInfo[] = [
                    'colis' => $oldestDrop['code'],
                    'delay' => $timeInformation['ageTimespan'],
                    'date' => $dateMvt->format('d/m/Y H:i:s'),
                    'late' => $isLate,
                    'emp' => $oldestDrop['label']
                ];
            }
        }

        return $emplacementInfo;
    }

    /**
     * @param array $workedDays [<english day label ('l' format)> => <nb time worked>]
     * @param DateTime $movementDate
     * @param DateTime[] $workFreeDays
     * @return DateInterval
     * @throws Exception
     */
    public function getTrackingMovementAge(array $workedDays, DateTime $movementDate, array $workFreeDays): DateInterval
    {
        if (count($workedDays) > 0) {
            $now = new DateTime("now", new DateTimeZone('Europe/Paris'));
            if ($now->getTimezone()->getName() !== $movementDate->getTimezone()->getName()) {
                $currentHours = $now->format('H');
                $currentMinutes = $now->format('i');
                $now->setTimezone($movementDate->getTimezone());
                $now->setTime((int)$currentHours, (int)$currentMinutes);
            }
            $nowIncluding = (clone $now)->setTime(23, 59, 59);
            $interval = DateInterval::createFromDateString('1 day');
            $period = new DatePeriod($movementDate, $interval, $nowIncluding);

            $periodsWorked = [];
            // pour chaque jour entre la date du mouvement et aujourd'hui, minimum un tour de boucle pour les mouvements du jours
            /** @var DateTime $day */
            foreach ($period as $day) {
                $dayLabel = strtolower($day->format('l'));
                if (isset($workedDays[$dayLabel])
                    && !$this->isDayInArray($day, $workFreeDays)) {
                    $periodsWorked = array_merge(
                        $periodsWorked,
                        array_map(
                            function (string $timePeriod) use ($now, $day, $movementDate) {
                                // we calculate delay between two given times
                                $times = explode('-', $timePeriod);

                                $time1 = explode(':', $times[0]);
                                $begin = (clone $day)->setTime($time1[0], $time1[1], 0);

                                $time2 = explode(':', $times[1]);
                                $end = (clone $day)->setTime($time2[0], $time2[1], 0);
                                if (($end < $movementDate) || ($now < $begin)) {
                                    $calculatedInterval = new DateInterval('P0Y');
                                } else {
                                    // si la date du mouvement est dans la fourchette => devient la date de begin
                                    if ($begin < $movementDate && $movementDate <= $end) {
                                        $begin = $movementDate;
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

            // on fait la somme de toutes les périodes calculés
            $age = array_reduce(
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
        } else {
            // age null
            $age = new DateInterval('P0Y');
        }
        return $age;
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
}
