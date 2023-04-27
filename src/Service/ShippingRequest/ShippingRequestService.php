<?php

namespace App\Service\ShippingRequest;

use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\ShippingRequest\ShippingRequestExpectedLine;
use App\Entity\Utilisateur;
use App\Service\VisibleColumnService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class ShippingRequestService {

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public VisibleColumnService $visibleColumnService;

    #[Required]
    public Security $security;

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public RouterInterface $router;

    public function getVisibleColumnsConfig(Utilisateur $currentUser): array {
        $columnsVisible = $currentUser->getVisibleColumns()['shippingRequest'];
        $columns = [
            ['name' => 'actions', 'alwaysVisible' => true, 'orderable' => false, 'class' => 'noVis'],
            ['title' => 'Numéro', 'name' => 'number'],
            ['title' => 'Statut', 'name' => 'status'],
            ['title' => 'Date de création', 'name' => 'createdAt'],
            ['title' => 'Date de prise en charge souhaitée', 'name' => 'requestCaredAt'],
            ['title' => 'Date de validation', 'name' => 'validatedAt'],
            ['title' => 'Date de planification', 'name' => 'plannedAt'],
            ['title' => 'Date d\'enlèvement prévu', 'name' => 'expectedPickedAt'],
            ['title' => 'Date d\'expédition', 'name' => 'treatedAt'],
            ['title' => 'Demandeur', 'name' => 'requesters'],
            ['title' => 'N° commande client', 'name' => 'customerOrderNumber'],
            ['title' => 'Transporteur', 'name' => 'freeDelivery'],
            ['title' => 'Transporteur', 'name' => 'compliantArticles'],
            ['title' => 'Client', 'name' => 'customerName'],
            ['title' => 'A l\'attention de', 'name' => 'customerRecipient'],
            ['title' => 'Téléphone', 'name' => 'customerPhone'],
            ['title' => 'Adresse de livraison', 'name' => 'customerAddress'],
            ['title' => 'Transporteur', 'name' => 'carrier'],
            ['title' => 'Numéro tracking', 'name' => 'trackingNumber'],
            ['title' => 'Envoi', 'name' => 'shipment'],
            ['title' => 'Port', 'name' => 'carrying'],
            ['title' => 'Commentaire', 'name' => 'comment'],
            ['title' => 'Poids brut (kg)', 'name' => 'grossWeight'],
        ];

        return $this->visibleColumnService->getArrayConfig($columns, [], $columnsVisible);
    }

    public function getDataForDatatable(Request $request) : array{
        $shippingRepository = $this->entityManager->getRepository(ShippingRequest::class);

        $queryResult = $shippingRepository->findByParamsAndFilters(
            $request->request,
            [],
            $this->visibleColumnService,
            [
                'user' => $this->security->getUser(),
            ]
        );

        $shippingRequests = $queryResult['data'];

        $rows = [];
        foreach ($shippingRequests as $shipping) {
            $rows[] = $this->dataRowShipping($shipping);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    public function dataRowShipping(ShippingRequest $shipping): array
    {
        $url = $this->router->generate('shipping_show_page', [
            "id" => $shipping->getId()
        ]);

        $row = [
            "actions" => $this->templating->render('shipping_request/actions.html.twig', [
                'url' => $url,
            ]),
            "number" => $shipping->getNumber(),
            "status" => $shipping->getStatus()->getCode(),
            "createdAt" => $shipping->getCreatedAt()->format("d/m/Y H:i"),
            "requestCaredAt" => $shipping->getRequestCaredAt()->format("d/m/Y H:i"),
            "validatedAt" => $shipping->getValidatedAt()->format("d/m/Y H:i"),
            "plannedAt" => $shipping->getPlannedAt()->format("d/m/Y H:i"),
            "expectedPickedAt" => $shipping->getExpectedPickedAt()->format("d/m/Y H:i"),
            "treatedAt" => $shipping->getTreatedAt()->format("d/m/Y H:i"),
            "requesters" => implode(",", Stream::from($shipping->getRequesters())
                ->map(fn(Utilisateur $requester) => $requester->getUsername())
                ->toArray()),
            "customerOrderNumber" => $shipping->getCustomerOrderNumber(),
            "freeDelivery" => $shipping->isFreeDelivery(),
            "compliantArticles" => $shipping->isCompliantArticles(),
            "customerName" => $shipping->getCustomerName(),
            "customerRecipient" => $shipping->getCustomerRecipient(),
            "customerPhone" => $shipping->getCustomerPhone(),
            "customerAddress" => $shipping->getCustomerAddress(),
            "carrier" => $shipping->getCarrier() ? $shipping->getCarrier()->getLabel() : '',
            "trackingNumber" => $shipping->getTrackingNumber(),
            "shipment" => $shipping->getShipment(),
            "carrying" => $shipping->getCarrying(),
            "comment" => $shipping->getComment(),
            "grossWeight" => $shipping->getGrossWeight(),
        ];

        return $row;
    }

    public function createHeaderTransportDetailsConfig(ShippingRequest $shippingRequest){
        $packsCount = $shippingRequest->getLines()->count();

        return $this->templating->render('shipping_request/show-transport-header.html.twig', [
            'shipping' => $shippingRequest,
            'packsCount' => $packsCount,
            'totalValue' => Stream::from($shippingRequest->getExpectedLines())
                ->map(fn(ShippingRequestExpectedLine $expectedLine) => $expectedLine->getPrice())
                ->sum(),
            'netWeight' => Stream::from($shippingRequest->getExpectedLines())
                ->map(fn(ShippingRequestExpectedLine $expectedLine) => $expectedLine->getWeight())
                ->sum(),
        ]);
    }
}
