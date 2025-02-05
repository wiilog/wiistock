<?php


namespace App\Service;


use App\Entity\Tracking\Pack;
use App\Entity\Utilisateur;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Twig\Environment;
use WiiCommon\Helper\Stream;


class EnCoursService {

    public function __construct(
        private TrackingMovementService $trackingMovementService,
        private FormatService           $formatService,
        private Environment             $templating,
        private DateTimeService         $dateTimeService,
        private TranslationService      $translationService,
        private FieldModesService       $fieldModesService
    ) {}

    /**
     * @param DateInterval $movementAge
     * @param string|null $dateMaxTime
     * @return array containing :
     * countDownLateTimespan => not defined or the time remaining to be late
     * ageTimespan => the time stayed on the emp
     */
    public function getTimeInformation(DateInterval $movementAge, ?string $dateMaxTime, int $additionalTime = 0): array
    {
        $ageTimespan = $this->dateTimeService->convertDateIntervalToMilliseconds($movementAge);
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

        $result = [];
        $dropsCounter = 0;

        $fields = [
            "pack.code AS code",
            "lastOngoingDrop.datetime AS datetime",
            "emplacement.dateMaxTime AS dateMaxTime",
            "emplacement.label AS label",
            "pack_arrival.id AS arrivalId",
            "pack_arrival.numeroCommandeList AS arrivalOrderNumber",
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
            $movementAge = $this->dateTimeService->getWorkedPeriodBetweenDates($entityManager, $dateMvt, new DateTime("now"));

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
                    'orderNumbers' => $pack['arrivalOrderNumber'] ? Stream::from($pack['arrivalOrderNumber'])->join(",") : null,
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

    public function getVisibleColumnsConfig(Utilisateur $currentUser): array {
        $columnsVisible = $currentUser->getFieldModes('onGoing');

        $columns = [
            ['title' => 'Issu de', 'name' => 'origin'],
            ['title' => $this->translationService->translate('Traçabilité', 'Général', 'Unité logistique', false), 'name' => 'LU', 'searchable' => true],
            ['title' => $this->translationService->translate('Traçabilité', 'Encours', 'Date de dépose', false), 'name' => 'date',  'type' => "customDate",'searchable' => true],
            ['title' => $this->translationService->translate('Traçabilité', 'Encours', 'Délai', false), 'name' => 'delay', 'searchable' => true],
            ['title' => $this->translationService->translate('Arrivages UL', 'Champs fixes', 'N° commande / BL', false), 'name' => 'orderNumbers', 'orderable' => true, 'searchable' => true],
            ['title' => 'Référence', 'name' => 'reference', 'searchable' => true],
            ['title' => 'Libellé', 'name' => 'libelle', 'searchable' => true],
        ];

        return $this->fieldModesService->getArrayConfig($columns, [], $columnsVisible);
    }
}
