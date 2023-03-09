<?php

namespace App\Service;


use App\Entity\TruckArrival;
use App\Entity\TruckArrivalLine;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use WiiCommon\Helper\Stream;

class TruckArrivalService
{

    #[Required]
    public VisibleColumnService $visibleColumnService;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public Environment $templating;

    public function getDataForDatatable(Request $request, Utilisateur $user): array{
        $truckArrivalRepository = $this->entityManager->getRepository(TruckArrival::class);

        $queryResult = $truckArrivalRepository->findByParamsAndFilters($request->query, $user, $this->visibleColumnService);
        $truckArrivals = Stream::from($queryResult['data'])
            ->map(function (TruckArrival $truckArrival) {
                return $this->dataRowTruckArrival($truckArrival);
            })
            ->toArray();

        return [
            'data' => $truckArrivals,
            'recordsFiltered' => $queryResult['count'] ?? null,
            'recordsTotal' => $queryResult['total'] ?? null,
        ];
    }

    public function dataRowTruckArrival(TruckArrival $truckArrival): array{
        $formatService = $this->formatService;
        return [
            'actions' => $this->templating->render('utils/action-buttons.html.twig', [
                'noButton' => true,
                'actions' => [
                    [
                        'title' => 'Détails',
                        'icon' => 'fa fa-eye',
                        'class' => 'action-on-click',
                    ],
                    [
                        'title' => 'Supprimer',
                        'icon' => 'fa fa-trash',
                        'class' => 'delete',
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
            'countTrackingLines' => $truckArrival->getTrackingLines()->filter(fn(TruckArrivalLine $line) => $line->getArrival()->count())->count() . '/' . $truckArrival->getTrackingLines()->count(),
            'operator' => $formatService->user($truckArrival->getOperator()),
            'reserves' => $truckArrival->getReserves()->isEmpty() ? 'non' : 'oui',
            'carrier' => $formatService->carrier($truckArrival->getCarrier()),
        ];
    }

    public function getVisibleColumns(Utilisateur $user): array{
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
}
