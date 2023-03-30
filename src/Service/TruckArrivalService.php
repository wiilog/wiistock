<?php

namespace App\Service;


use App\Entity\Action;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\TruckArrival;
use App\Entity\TruckArrivalLine;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment;
use WiiCommon\Helper\Stream;

class TruckArrivalService
{

    #[Required]
    public VisibleColumnService $visibleColumnService;

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
    public FieldsParamService $fieldsParamService;

    public function getDataForDatatable(EntityManagerInterface $entityManager,
                                        Request                $request,
                                        Utilisateur            $user): array {
        $truckArrivalRepository = $entityManager->getRepository(TruckArrival::class);
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_TRUCK_ARRIVAL, $user);
        $queryResult = $truckArrivalRepository->findByParamsAndFilters($request->query, $filters, $user, $this->visibleColumnService);
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
            'trackingLinesNumber' => $formatService->truckArrivalLines($truckArrival->getTrackingLines()),
            'countTrackingLines' => $truckArrival->getTrackingLines()
                    ->filter(fn(TruckArrivalLine $line) => $line->getArrivals()->count())
                    ->count()
                . '/'
                . $truckArrival->getTrackingLines()->count(),
            'operator' => $formatService->user($truckArrival->getOperator()),
            'reserves' => $truckArrival->getReserves()->isEmpty() && !$lineHasReserve ? 'non' : 'oui',
            'carrier' => $formatService->carrier($truckArrival->getCarrier()),
            'late' => Stream::from($truckArrival->getTrackingLines())
                ->some(fn (TruckArrivalLine $line) => $this->truckArrivalLineService->lineIsLate($line, $entityManager))
        ];
    }

    public function getVisibleColumns(Utilisateur $user): array {
        $columnsVisible = $user->getVisibleColumns()['truckArrival'];
        $columns = [
            ['title' => 'Chauffeur', 'name' => 'driver'],
            ['title' => 'Date de création', 'name' => 'creationDate'],
            ['title' => 'Emplacement de déchargement', 'name' => 'unloadingLocation'],
            ['title' => 'Immatriculation', 'name' => 'registrationNumber'],
            ['title' => 'Numéro d\'arrivage camion', 'name' => 'number'],
            ['title' => 'N° de tracking transporteur', 'name' => 'trackingLinesNumber', 'orderable' => false,],
            ['title' => 'Nombre de n° de tracking associé', 'name' => 'countTrackingLines', 'orderable' => false,],
            ['title' => 'Opérateur', 'name' => 'operator'],
            ['title' => 'Réserve(s)', 'name' => 'reserves'],
            ['title' => 'Transporteur', 'name' => 'carrier'],
        ];
        return $this->visibleColumnService->getArrayConfig($columns, [], $columnsVisible);
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
                'value' => $attachments ? $attachments->toArray() : '',
                'isAttachments' => true,
                'isNeededNotEmpty' => true,
            ],
        ];
    }
}
