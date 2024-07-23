<?php


namespace App\Service;


use App\Entity\Pack;
use App\Entity\DaysWorked;
use App\Entity\TrackingMovement;
use App\Entity\Utilisateur;
use App\Entity\WorkFreeDay;
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

    #[Required]
    public FieldModesService $fieldModesService;

    #[Required]
    public TranslationService $translationService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public TrackingMovementService  $trackingMovementService;

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
    public function getLastEnCoursForLate(EntityManagerInterface $entityManager)
    {
        return $this->getEnCours($entityManager, [], [], true);
    }

    public function getEnCours(EntityManagerInterface $entityManager,
                               array                  $locations,
                               array                  $natures = [],
                               bool                   $onlyLate = false,
                               bool                   $fromOnGoing = false,
                               Utilisateur            $user = null,
                               bool                   $useTruckArrivals = false): array
    {
        $packRepository = $entityManager->getRepository(Pack::class);
        $workedDaysRepository = $entityManager->getRepository(DaysWorked::class);
        $workFreeDaysRepository = $entityManager->getRepository(WorkFreeDay::class);

        $daysWorked = $workedDaysRepository->getWorkedTimeForEachDaysWorked();
        $freeWorkDays = $workFreeDaysRepository->getWorkFreeDaysToDateTime();
        $result = [];
        $dropsCounter = 0;

        $fields = [
            "pack.code AS code",
            "lastDrop.datetime AS datetime",
            "emplacement.dateMaxTime AS dateMaxTime",
            "emplacement.label AS label",
            "pack_arrival.id AS arrivalId",
            ...$useTruckArrivals
                ? ["pack.truckArrivalDelay AS truckArrivalDelay",]
                : []
        ];

        $maxQueryResultLength = 200;
        $limitOnlyLate = 100;
        $ongoingOnLocation = $packRepository->getCurrentPackOnLocations(
            $locations,
            [
                'natures' => $natures,
                'isCount' => false,
                'field' => Stream::from($fields)->join(","),
                "onlyLate" => $onlyLate,
                'fromOnGoing' => $fromOnGoing,
                ...($onlyLate
                    ? [
                        'limit' => $maxQueryResultLength,
                        'start' => $dropsCounter,
                        'order' => 'asc',
                    ]
                    : []),
            ]
        );
        foreach ($ongoingOnLocation as $pack) {
            $dateMvt = $pack['datetime'];
            $movementAge = $this->timeService->getIntervalFromDate($daysWorked, $dateMvt, $freeWorkDays);
            $dateMaxTime = $pack['dateMaxTime'];
            $truckArrivalDelay = $useTruckArrivals ? intval($pack["truckArrivalDelay"]) : 0;
            $timeInformation = $this->getTimeInformation($movementAge, $dateMaxTime, $truckArrivalDelay);
            $isLate = $timeInformation['countDownLateTimespan'] < 0;

            $fromColumnData = $fromOnGoing
                ? $this->trackingMovementService->getFromColumnData([
                    "entity" => $pack['entity'],
                    "entityId" => $pack['entityId'],
                    "entityNumber" => $pack['entityNumber'],
                ])
                : [];

            if(!$onlyLate || ($isLate && count($result) < $limitOnlyLate)){
                $result[] = [
                    'LU' => $pack['code'],
                    'delay' => $timeInformation['ageTimespan'],
                    'delayTimeStamp' => $timeInformation['ageTimespan'],
                    'date' => $dateMvt->format(($user && $user->getDateFormat() ? $user->getDateFormat() : 'd/m/Y') . ' H:i:s'),
                    'late' => $isLate,
                    'emp' => $pack['label'],
                    'libelle' => $pack['reference_label'] ?? null,
                    'reference' => $pack['reference_reference'] ?? null,
                    ...($fromOnGoing
                        ? ['origin' => $this->templating->render('tracking_movement/datatableMvtTracaRowFrom.html.twig', $fromColumnData)]
                        : []),
                ];
            }
        }

        return $result;
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
            $encours['delay'] ?: '',
            $this->formatService->bool($encours['late']),
            $encours['reference'] ?: '',
            $encours['libelle'] ?: '',
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

    public function getVisibleColumnsConfig(Utilisateur $currentUser): array {
        $columnsVisible = $currentUser->getVisibleColumns()['onGoing'];

        $columns = [
            ['title' => 'Issu de', 'name' => 'origin'],
            ['title' => $this->translationService->translate('Traçabilité', 'Général', 'Unité logistique', false), 'name' => 'LU', 'searchable' => true],
            ['title' => $this->translationService->translate('Traçabilité', 'Encours', 'Date de dépose', false), 'name' => 'date',  'type' => "customDate",'searchable' => true],
            ['title' => $this->translationService->translate('Traçabilité', 'Encours', 'Délai', false), 'name' => 'delay', 'searchable' => true],
            ['title' => 'Référence', 'name' => 'reference', 'searchable' => true],
            ['title' => 'Libellé', 'name' => 'libelle', 'searchable' => true],
        ];

        return $this->fieldModesService->getArrayConfig($columns, [], $columnsVisible);
    }
}
