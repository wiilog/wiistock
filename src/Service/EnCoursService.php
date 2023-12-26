<?php


namespace App\Service;


use App\Entity\Pack;
use App\Entity\DaysWorked;
use App\Entity\Utilisateur;
use App\Entity\WorkFreeDay;
use App\Helper\FormatHelper;
use DateInterval;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment;
use WiiCommon\Helper\Stream;


class EnCoursService
{
    /**
     * @var EntityManagerInterface $entityManager
     */
    private EntityManagerInterface $entityManager;
    private Environment $templating;

    #[Required]
    public TimeService $timeService;

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
    public function __construct(EntityManagerInterface $entityManager, Environment $templating)
    {
        $this->entityManager = $entityManager;
        $this->templating = $templating;
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
    public function getTimeInformation(DateInterval $movementAge, ?string $dateMaxTime, int $additionalTime = 0): array
    {
        $ageTimespan = (
            ($movementAge->h * 60 * 60 * 1000) + // hours in milliseconds
            ($movementAge->i * 60 * 1000) + // minutes in milliseconds
            ($movementAge->s * 1000) + // seconds in milliseconds
            ($movementAge->f)
        );
        $information = [
            'ageTimespan' => $ageTimespan + $additionalTime,
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
        } else {
            $information['countDownLateTimespan'] = null;
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

    public function getEnCours(array       $locations,
                               array       $natures = [],
                               bool        $onlyLate = false,
                               ?int        $limitOnlyLate = 100,
                               Utilisateur $user = null,
                               bool        $useTruckArrivals = false): array
    {
        $packRepository = $this->entityManager->getRepository(Pack::class);
        $dropsCounter = 0;
        $workedDaysRepository = $this->entityManager->getRepository(DaysWorked::class);
        $workFreeDaysRepository = $this->entityManager->getRepository(WorkFreeDay::class);
        $daysWorked = $workedDaysRepository->getWorkedTimeForEachDaysWorked();
        $freeWorkDays = $workFreeDaysRepository->getWorkFreeDaysToDateTime();
        $emplacementInfo = [];

        $fields = [
            "pack.code",
            "lastDrop.datetime",
            "emplacement.dateMaxTime",
            "emplacement.label",
            "pack_arrival.id AS arrivalId",
            ...$useTruckArrivals
                ? [
                    "pack.truckArrivalDelay AS truckArrivalDelay",
                ] : []
        ];

        if ($onlyLate) {
            $maxQueryResultLength = 200;
            while (count($emplacementInfo) < $limitOnlyLate) {
                $oldestDrops = [];
                $oldestDrops[] = $packRepository->getCurrentPackOnLocations(
                    $locations,
                    [
                        'natures' => $natures,
                        'isCount' => false,
                        'field' => Stream::from($fields)->join(","),
                        'limit' => $maxQueryResultLength,
                        'start' => $dropsCounter,
                        'order' => 'asc',
                        'onlyLate' => true
                    ]
                );
                $oldestDrops = $oldestDrops[0];
                if (empty($oldestDrops)) {
                    break;
                }
                foreach ($oldestDrops as $oldestDrop) {
                    $dateMvt = $oldestDrop['datetime'];
                    $movementAge = $this->timeService->getIntervalFromDate($daysWorked, $dateMvt, $freeWorkDays);
                    $dateMaxTime = $oldestDrop['dateMaxTime'];
                    $truckArrivalDelay = $useTruckArrivals ? $oldestDrop["truckArrivalDelay"] : 0;
                    $timeInformation = $this->getTimeInformation($movementAge, $dateMaxTime, $truckArrivalDelay);
                    $isLate = $timeInformation['countDownLateTimespan'] < 0;
                    if ($isLate) {
                        $emplacementInfo[] = [
                            'LU' => $oldestDrop['code'],
                            'delay' => $timeInformation['ageTimespan'],
                            'date' => $dateMvt->format(($user && $user->getDateFormat() ? $user->getDateFormat() : 'd/m/Y') . ' H:i:s'),
                            'late' => $isLate,
                            'emp' => $oldestDrop['label'],
                            'linkedArrival' => $this->templating->render('en_cours/datatableOnGoingRow.html.twig', [
                                'arrivalId' => $oldestDrop['arrivalId'],
                            ]),
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
                [
                    'natures' => $natures,
                    'isCount' => false,
                    'field' => Stream::from($fields)->join(","),
                ]
            );
            $oldestDrops = $oldestDrops[0];
            foreach ($oldestDrops as $oldestDrop) {
                $dateMvt = $oldestDrop['datetime'];
                $movementAge = $this->timeService->getIntervalFromDate($daysWorked, $dateMvt, $freeWorkDays);
                $dateMaxTime = $oldestDrop['dateMaxTime'];
                $truckArrivalDelay = $useTruckArrivals ? intval($oldestDrop["truckArrivalDelay"]) : 0;
                $timeInformation = $this->getTimeInformation($movementAge, $dateMaxTime, $truckArrivalDelay);
                $isLate = $timeInformation['countDownLateTimespan'] < 0;
                $emplacementInfo[] = [
                    'LU' => $oldestDrop['code'],
                    'delay' => $timeInformation['ageTimespan'],
                    'date' => $dateMvt->format(($user && $user->getDateFormat() ? $user->getDateFormat() : 'd/m/Y') . ' H:i:s'),
                    'late' => $isLate,
                    'emp' => $oldestDrop['label'],
                    'linkedArrival' => $this->templating->render('en_cours/datatableOnGoingRow.html.twig', [
                        'arrivalId' => $oldestDrop['arrivalId'],
                    ]),
                ];
            }
        }

        return $emplacementInfo;
    }



    /**
     * @param $handle
     * @param CSVExportService $CSVExportService
     * @param array $encours
     */
    public function putOngoingPackLine($handle,
                                        CSVExportService $CSVExportService,
                                        array $encours)
    {
        $line = [
            $encours['emp'] ?: '',
            $encours['LU'] ?: '',
            $encours['date'] ?: '',
            $encours['delay'] ? $this->renderMillisecondsToDelay($encours['delay']): '',
            FormatHelper::bool($encours['late'])
        ];
        $CSVExportService->putLine($handle, $line);
    }

    /**
     * @param int $milliseconds
     * @return string
     */
    public function renderMillisecondsToDelay(int $milliseconds): string
    {
        $seconds = floor($milliseconds / 1000);
        $totalMinutes = ($seconds / 60);
        $minutes = floor($totalMinutes % 60);
        $hours = floor($totalMinutes / 60);

        $hoursString = ($hours > 0 ? ($hours < 10 ? '0' : '') . $hours : '00');
        $minutesString = ($minutes > 0 ? ($minutes < 10 ? '0' : '') . $minutes : '00');

        return ($hoursString . ' h ' . $minutesString . ' min');

    }

}
