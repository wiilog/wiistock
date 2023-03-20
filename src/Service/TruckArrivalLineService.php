<?php

namespace App\Service;


use App\Entity\Action;
use App\Entity\Arrivage;
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

class TruckArrivalLineService
{

    #[Required]
    public VisibleColumnService $visibleColumnService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public UserService $userService;

    #[Required]
    public Environment $templating;

    #[Required]
    public RouterInterface $router;

    public function getDataForDatatable(EntityManagerInterface $entityManager,
                                        Request                $request): array {
        $truckArrivalLineRepository = $entityManager->getRepository(TruckArrivalLine::class);
        $queryResult = $truckArrivalLineRepository->findByParamsAndFilters($request->query);

        $truckArrivalLines = Stream::from($queryResult['data'])
            ->map(function (TruckArrivalLine $truckArrivalLine) {
                return $this->dataRowTruckArrivalLine($truckArrivalLine);
            })
            ->toArray();

        return [
            'data' => $truckArrivalLines,
            'recordsFiltered' => $queryResult['count'] ?? null,
            'recordsTotal' => $queryResult['total'] ?? null,
        ];
    }

    public function dataRowTruckArrivalLine(TruckArrivalLine $truckArrivalLine): array {
        return [
            'actions' => $this->templating->render('utils/action-buttons.html.twig', [
                'noButton' => true,
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
            'lineNumber' => $truckArrivalLine->getNumber(),
            'associatedToUL' => $this->formatService->bool(!$truckArrivalLine->getArrivals()->isEmpty()),
            'arrivalLinks' => !$truckArrivalLine->getArrivals()->isEmpty()
                ? Stream::from($truckArrivalLine->getArrivals())
                    ->map(fn(Arrivage $arrivage) => '<a href="/arrivage/voir/'.$arrivage->getId().'"><i class="mr-2 fas fa-external-link-alt"></i>'.$arrivage->getNumeroArrivage().'</a><br>')
                    ->join('')
                : '',
            'operator' => $truckArrivalLine->getTruckArrival() ? $this->formatService->user($truckArrivalLine->getTruckArrival()->getOperator()) : '',
        ];
    }
}
