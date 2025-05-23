<?php

namespace App\Service;


use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\Menu;
use App\Entity\Setting;
use App\Entity\TruckArrivalLine;
use App\Exceptions\FormException;
use App\Service\WorkPeriod\WorkPeriodItem;
use App\Service\WorkPeriod\WorkPeriodService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment;
use WiiCommon\Helper\Stream;

class TruckArrivalLineService
{

    #[Required]
    public FieldModesService $fieldModesService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public UserService $userService;

    #[Required]
    public Environment $templating;

    #[Required]
    public RouterInterface $router;

    public function __construct(
        private SettingsService   $settingsService,
        private WorkPeriodService $workPeriodService,
    ) {}

    public function getDataForDatatable(EntityManagerInterface $entityManager,
                                        Request                $request): array {
        $truckArrivalLineRepository = $entityManager->getRepository(TruckArrivalLine::class);
        $queryResult = $truckArrivalLineRepository->findByParamsAndFilters($request->query);

        $truckArrivalLines = Stream::from($queryResult['data'])
            ->map(function (TruckArrivalLine $truckArrivalLine) use ($entityManager) {
                return $this->dataRowTruckArrivalLine($truckArrivalLine, $entityManager);
            })
            ->toArray();

        return [
            'data' => $truckArrivalLines,
            'recordsFiltered' => $queryResult['count'] ?? null,
            'recordsTotal' => $queryResult['total'] ?? null,
        ];
    }

    public function dataRowTruckArrivalLine(TruckArrivalLine $truckArrivalLine, EntityManagerInterface $entityManager): array {
        return [
            'actions' => $this->templating->render('utils/action-buttons/dropdown.html.twig', [
                'actions' => [
                    [
                        'hasRight' => $this->userService->hasRightFunction(Menu::TRACA, Action::DELETE_CARRIER_TRACKING_NUMBER),
                        'title' => 'Supprimer',
                        'icon' => 'wii-icon wii-icon-trash',
                        'class' => 'truck-arrival-lines-delete',
                        'attributes' => [
                            "data-id" => $truckArrivalLine->getId(),
                            "onclick" => "deleteTruckArrivalLine($(this))"
                        ]
                    ],
                ],
            ]),
            'id' => $truckArrivalLine->getId(),
            'disableTrackingNumber' => $truckArrivalLine?->getReserve()?->getReserveType()?->isDisableTrackingNumber() ? '<img src="/svg/exclamation.svg" alt="Désactivé" width="15px" title="Désactivé">' : '',
            'lineNumber' =>  $truckArrivalLine->getNumber(),
            'associatedToUL' => $this->formatService->bool(!$truckArrivalLine->getArrivals()->isEmpty()),
            'arrivalLinks' => !$truckArrivalLine->getArrivals()->isEmpty()
                ? Stream::from($truckArrivalLine->getArrivals())
                    ->map(fn(Arrivage $arrivage) => '<a href="/arrivage/voir/'.$arrivage->getId().'"><i class="mr-2 fas fa-external-link-alt"></i>'.$arrivage->getNumeroArrivage().'</a><br>')
                    ->join('')
                : '',
            'operator' => $this->formatService->user($truckArrivalLine->getTruckArrival()?->getOperator()),
            'late' => $this->lineIsLate($truckArrivalLine, $entityManager),
        ];
    }


    // TODO WIIS-11888
    public function lineIsLate(TruckArrivalLine $line, EntityManagerInterface $entityManager): bool {
        if (!$line->getArrivals()->isEmpty()) {
            return false;
        }

        $beforeStart = $this->settingsService->getValue($entityManager, Setting::TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_BEFORE_START);
        $beforeEnd = $this->settingsService->getValue($entityManager, Setting::TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_BEFORE_END);
        $afterStart = $this->settingsService->getValue($entityManager, Setting::TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_AFTER_START);
        $afterEnd = $this->settingsService->getValue($entityManager, Setting::TRUCK_ARRIVALS_PROCESSING_HOUR_CREATE_AFTER_END);

        if (!$beforeStart || !$beforeEnd || !$afterStart || !$afterEnd) {
            return false;
        }

        // This date is only used to compare times, the day will always be the same (now +1 day) and it will
        // be cloned and the times will be changed to compare them only
        $arbitraryDateToCompareTimes = (new DateTime())->modify('+1 day');

        // Here we create dates from the times in the settings, to compare them more easily with dates operators (<, >, ...)
        $beforeStartWithDate = (clone $arbitraryDateToCompareTimes)
            ->setTime(intval(explode(':', $beforeStart)[0]), intval(explode(':', $beforeStart)[1]));
        $beforeEndWithDate = (clone $arbitraryDateToCompareTimes)
            ->setTime(intval(explode(':', $beforeEnd)[0]), intval(explode(':', $beforeEnd)[1]));
        $afterStartWithDate = (clone $arbitraryDateToCompareTimes)
            ->setTime(intval(explode(':', $afterStart)[0]), intval(explode(':', $afterStart)[1]));
        $afterEndWithDate = (clone $arbitraryDateToCompareTimes)
            ->setTime(intval(explode(':', $afterEnd)[0]), intval(explode(':', $afterEnd)[1]));

        $now = new DateTime();
        $nowWithoutTime = (clone $now)->setTime(0, 0, 0);
        $nowWithOnlyTime = (clone $arbitraryDateToCompareTimes)
            ->setTime(intval($now->format('H')), intval($now->format('i')));

        $dateLine = $line->getTruckArrival()->getCreationDate();
        $dateWithoutTime = (clone $dateLine)->setTime(0, 0, 0);
        $dateWithOnlyTime = (clone $arbitraryDateToCompareTimes)
            ->setTime(intval($dateLine->format('H')), intval($dateLine->format('i')));

        $nextWorkedDay = clone $dateLine;
        $nextWorkedDay->setTime(0, 0, 0);
        $workedDays = $this->workPeriodService->get($entityManager, WorkPeriodItem::WORKED_DAYS);

        /**
         * Prevent infinite loop
         */
        if(empty($workedDays)){
           return false;
        }

        do {
            $nextWorkedDay->modify('+1 day');
        } while (!$this->workPeriodService->isOnWorkPeriod($entityManager, $nextWorkedDay, ["onlyDayCheck" => true]));

        // First possibility (created before 14:00, must be treated before 17:00 on the same day)
        $wasCreatedBeforeTresholdAndIsValid = $dateWithOnlyTime <= $beforeStartWithDate
            && ($nowWithoutTime > $dateWithoutTime || $nowWithOnlyTime > $beforeEndWithDate);

        // Second possibility (created after 14:00, must be treated before 12:00 on the next WORKED day)
        $wasCreatedAfterTresholdAndIsValid = $dateWithOnlyTime >= $afterStartWithDate
            && ($nowWithoutTime > $nextWorkedDay || ($nowWithoutTime->format('d/m/Y') === $nextWorkedDay->format('d/m/Y') && $nowWithOnlyTime > $afterEndWithDate));
        return $wasCreatedBeforeTresholdAndIsValid || $wasCreatedAfterTresholdAndIsValid;
    }

    public function checkForInvalidNumber(array $trackingNumbers, EntityManagerInterface $entityManager): void {
        $lineNumberRepository = $entityManager->getRepository(TruckArrivalLine::class);
        $notDuplicableLines = Stream::from($lineNumberRepository->findBy(['number' => $trackingNumbers], ['id' => 'DESC']) ?? [])
            ->filter(static fn(TruckArrivalLine $line) => (
                !$line->getReserve()
                || !($line->getReserve()->getReserveType()?->isDisableTrackingNumber())
            ));

        if (!$notDuplicableLines->isEmpty()) {
            $trackingNumberCount = $notDuplicableLines->count();

            $trackingNumbers = $notDuplicableLines
                ->map(static fn (TruckArrivalLine $line) => $line->getNumber())
                ->join(', ');

            throw new FormException($trackingNumberCount === 1
                ? "Le numéro de tracking {$trackingNumbers} a déjà été scanné dans un arrivage camion. Veuillez le supprimer pour enregistrer."
                : "Les numéros de tracking {$trackingNumbers} ont déjà été scannés dans un arrivage camion. Veuillez les supprimer pour enregistrer."
            );
        }
    }
}
