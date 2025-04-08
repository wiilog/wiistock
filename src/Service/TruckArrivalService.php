<?php

namespace App\Service;


use App\Entity\Action;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\TruckArrival;
use App\Entity\TruckArrivalLine;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment;
use WiiCommon\Helper\Stream;

class TruckArrivalService
{

    #[Required]
    public FieldModesService $fieldModesService;

    #[Required]
    public TruckArrivalLineService $truckArrivalLineService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public UserService $userService;

    #[Required]
    public Environment $templating;

    #[Required]
    public RouterInterface $router;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public PDFGeneratorService $PDFGeneratorService;

    #[Required]
    public CSVExportService $CSVExportService;

    public function getDataForDatatable(EntityManagerInterface $entityManager,
                                        Request                $request,
                                        Utilisateur            $user): array {
        $truckArrivalRepository = $entityManager->getRepository(TruckArrival::class);
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);

        $params = $request->request;
        $fromDashboard = $params->getBoolean("fromDashboard");
        $countNoLinkedTruckArrival = $params->getBoolean("countNoLinkedTruckArrival");
        $carrierTrackingNumberNotAssigned = $params->getBoolean("carrierTrackingNumberNotAssigned");
        $locationsFilter = $params->all("locations");

        if($fromDashboard) {
            $filters = [
                ...($locationsFilter ? [["field" => "unloadingLocation", "value" => $locationsFilter]] : []),
                ...($carrierTrackingNumberNotAssigned && !$countNoLinkedTruckArrival ? [["field" => "carrierTrackingNumberNotAssigned", "value" => true]] : []),
                ...($countNoLinkedTruckArrival ? [["field" => "countNoLinkedTruckArrival", "value" => true]] : []),
            ];
        } else {
            $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_TRUCK_ARRIVAL, $user);
        }

        $queryResult = $truckArrivalRepository->findByParamsAndFilters($request->request, $filters, $user, $this->fieldModesService);
        $truckArrivals = Stream::from($queryResult['data'])
            ->map(function (TruckArrival $truckArrival) use ($entityManager) {
                return $this->dataRowTruckArrival($truckArrival, $entityManager);
            })
            ->toArray();

        return [
            'data' => $truckArrivals,
            'recordsFiltered' => $queryResult['count'] ?? null,
            'recordsTotal' => $queryResult['total'] ?? null,
        ];
    }

    public function dataRowTruckArrival(TruckArrival $truckArrival, EntityManagerInterface $entityManager): array {
        $formatService = $this->formatService;

        $lineHasReserve = !$truckArrival->getTrackingLines()->isEmpty() &&
            Stream::from($truckArrival->getTrackingLines())
                ->some(fn(TruckArrivalLine $line) => $line->getReserve());
        $reserves = $truckArrival->getReserves();

        return [
            'actions' => $this->templating->render('utils/action-buttons/dropdown.html.twig', [
                'actions' => [
                    [
                        'title' => 'Détails',
                        'icon' => 'fa fa-eye',
                        "actionOnClick" => true,
                        'attributes' => [
                            'onclick' => "window.location.href = '{$this->router->generate('truck_arrival_show', ['id' => $truckArrival->getId()])}'",
                        ]
                    ],
                    [
                        "title" => "Imprimer",
                        "icon" => "wii-icon wii-icon-printer-black",
                        "attributes" => [
                            "data-id" => $truckArrival->getId(),
                            "onclick" => "printTruckArrivalLabel($(this))"
                        ]
                    ],
                    [
                        'hasRight' => $this->userService->hasRightFunction(Menu::TRACA, Action::CREATE_ARRIVAL),
                        'title' => 'Créer arrivage UL',
                        'icon' => 'fa fa-plus',
                        'attributes' => [
                            'onclick' => "window.location.href = '{$this->router->generate('arrivage_index', ['truckArrivalId' => $truckArrival->getId()])}'",
                        ],
                    ],
                    [
                        'hasRight' => $this->userService->hasRightFunction(Menu::TRACA, Action::DELETE_TRUCK_ARRIVALS),
                        'title' => 'Supprimer',
                        'icon' => 'fa fa-trash',
                        'class' => 'truck-arrival-delete',
                        'attributes' => [
                            "data-id" => $truckArrival->getId(),
                            "onclick" => "deleteTruckArrival($(this))"
                        ]
                    ],
                ],
            ]),
            'id' => $truckArrival->getId(),
            'driver' => $formatService->driver($truckArrival->getDriver()),
            'creationDate' => $formatService->datetime($truckArrival->getCreationDate()),
            'unloadingLocation' => $formatService->location($truckArrival->getUnloadingLocation()),
            'registrationNumber' => $truckArrival->getRegistrationNumber(),
            'number' => $truckArrival->getNumber(),
            'trackingLinesNumber' => Stream::from($truckArrival->getTrackingLines())
                ->map(fn(TruckArrivalLine $line) =>
                    ($line->getReserve()?->getReserveType()->isDisableTrackingNumber() ? '<img src="/svg/exclamation.svg" alt="Désactivé" width="15px">' : '') . $line->getNumber())
                ->filter()
                ->join(', '),
            'countTrackingLines' => $truckArrival->getTrackingLines()
                    ->filter(fn(TruckArrivalLine $line) => $line->getArrivals()->count())
                    ->count()
                . '/'
                . $truckArrival->getTrackingLines()
                    ->filter(fn(TruckArrivalLine $line) => !$line?->getReserve()?->getReserveType()->isDisableTrackingNumber())
                    ->count(),
            'operator' => $formatService->user($truckArrival->getOperator()),
            'reserves' => $reserves->isEmpty() && !$lineHasReserve ? 'non' : 'oui',
            'trackingLinesReserves' =>  Stream::from($truckArrival->getTrackingLines())
                ->map(fn(TruckArrivalLine $line) =>  $formatService->reserveType($line->getReserve()?->getReserveType()))
                ->unique()
                ->filter()
                ->join(', '),
            'carrier' => $formatService->carrier($truckArrival->getCarrier()),
            'late' => Stream::from($truckArrival->getTrackingLines())
                ->some(fn (TruckArrivalLine $line) => $this->truckArrivalLineService->lineIsLate($line, $entityManager)),
        ];
    }

    public function getVisibleColumns(Utilisateur $user): array {
        $columnsVisible = $user->getFieldModes('truckArrival');
        $columns = [
            ['title' => 'Chauffeur', 'name' => 'driver'],
            ['title' => 'Date de création', 'name' => 'creationDate'],
            ['title' => 'Emplacement de déchargement', 'name' => 'unloadingLocation'],
            ['title' => 'Immatriculation', 'name' => 'registrationNumber'],
            ['title' => 'Numéro d\'arrivage camion', 'name' => 'number'],
            ['title' => 'N° de tracking transporteur', 'name' => 'trackingLinesNumber', 'orderable' => false,],
            ['title' => 'Nombre de n° de tracking associés', 'name' => 'countTrackingLines', 'orderable' => false,],
            ['title' => 'Opérateur', 'name' => 'operator'],
            ['title' => 'Réserve(s)', 'name' => 'reserves'],
            ['title' => 'Réserve sur n° tracking', 'name' => 'trackingLinesReserves', 'orderable' => false,],
            ['title' => 'Transporteur', 'name' => 'carrier'],
        ];
        return $this->fieldModesService->getArrayConfig($columns, [], $columnsVisible);
    }

    public function createHeaderDetailsConfig(TruckArrival $truckArrival): array {
        $carrier = $truckArrival->getCarrier();
        $driver = $truckArrival->getDriver();
        $operator = $truckArrival->getOperator();
        $creationDate = $truckArrival->getCreationDate();
        $unloadingLocation = $truckArrival->getUnloadingLocation();
        $attachments = $truckArrival->getAttachments();

        return [
            [
                'label' => 'Transporteur',
                'value' => $carrier ? $carrier->getLabel() : '',
                'show' => ['fieldName' => 'transporteur'],
                'isRaw' => true
            ],
            [
                'label' => 'Chauffeur',
                'value' => $driver ? $driver->getPrenomNom() : '',
                'show' => ['fieldName' => 'chauffeur'],
                'isRaw' => true
            ],
            [
                'label' => 'Immatriculation',
                'value' => $truckArrival->getRegistrationNumber() ?? '',
                'show' => ['fieldName' => 'immatriculation'],
                'isRaw' => true
            ],
            [
                'label' => 'Opérateur',
                'value' => $operator ? $operator->getUsername() : '',
                'show' => ['fieldName' => 'operateur'],
                'isRaw' => true
            ],
            [
                'label' => 'Date de création',
                'value' => $creationDate ? $creationDate->format('d/m/Y H:i:s') : '',
                'show' => ['fieldName' => 'dateCreation'],
                'isRaw' => true
            ],
            [
                'label' => 'Emplacement de déchargement',
                'value' => $unloadingLocation ? $unloadingLocation->getLabel() : '',
                'show' => ['fieldName' => 'emplacement'],
                'isRaw' => true
            ],
            [
                'label' => 'Pièces jointes',
                'value' => !$attachments->isEmpty() ? $attachments->toArray() : '',
                'isAttachments' => true,
                'isNeededNotEmpty' => true,
            ],
        ];
    }

    public function getLabelConfig(TruckArrival $truckArrival): array
    {
        $labels = [
            "{$truckArrival->getCarrier()->getLabel()}",
            "{$this->formatService->datetime($truckArrival->getCreationDate())}",
        ];

        $barCodeConfig = [
            "code" => $truckArrival->getNumber(),
            "labels" => array_filter($labels, function (string $label) {
                return !empty($label);
            }),
        ];

        $fileName = $this->PDFGeneratorService->getBarcodeFileName([$barCodeConfig], "arrivage_camion");

        return [
            $fileName,
            $barCodeConfig,
        ];
    }

    public function serialize(TruckArrival $truckArrival): array {
        $carrierTrackingNumbers = Stream::from($truckArrival->getTrackingLines())
            ->map(static fn(TruckArrivalLine $line) => $line->getNumber())
            ->join(', ');

        $lineHasReserve = !$truckArrival->getTrackingLines()->isEmpty() &&
            Stream::from($truckArrival->getTrackingLines())
                ->some(fn(TruckArrivalLine $line) => $line->getReserve());

        return [
            FixedFieldEnum::number->value => $truckArrival->getNumber(),
            FixedFieldEnum::carrier->value => $this->formatService->carrier($truckArrival->getCarrier()),
            FixedFieldEnum::driver->value => $this->formatService->driver($truckArrival->getDriver()),
            FixedFieldEnum::carrierTrackingNumber->value => $carrierTrackingNumbers,
            FixedFieldEnum::carrierTrackingNumberReserve->value => $this->formatService->bool($lineHasReserve),
        ];
    }

    public function getCsvHeader(): array {
        return [
            FixedFieldEnum::number->value,
            FixedFieldEnum::carrier->value,
            FixedFieldEnum::driver->value,
            FixedFieldEnum::carrierTrackingNumber->value,
            FixedFieldEnum::carrierTrackingNumberReserve->value,
            FixedFieldEnum::createdAt->value,
            FixedFieldEnum::operator->value,
            FixedFieldEnum::registrationNumber->value,
            FixedFieldEnum::unloadingLocation->value,
            "Réserve(s)",
        ];
    }

    public function getExportFunction(DateTime               $dateTimeMin,
                                      DateTime               $dateTimeMax,
                                      EntityManagerInterface $entityManager): callable {
        $truckArrivalRepository = $entityManager->getRepository(TruckArrival::class);
        $truckArrivals = $truckArrivalRepository->iterateByDates($dateTimeMin, $dateTimeMax);

        return function ($handle) use ($truckArrivals) {
            foreach ($truckArrivals as $truckArrival) {
                $this->CSVExportService->putLine($handle, [
                    $truckArrival["number"],
                    $truckArrival["carrier"],
                    $truckArrival["driver"],
                    $truckArrival["carrierTrackingNumber"],
                    $truckArrival["carrierTrackingNumberReserve"],
                    $this->formatService->datetime($truckArrival["createdAt"]),
                    $truckArrival["operator"],
                    $truckArrival["registrationNumber"],
                    $truckArrival["unloadingLocation"],
                    $truckArrival["hasReserve"],
                ]);
            }
        };
    }
}
