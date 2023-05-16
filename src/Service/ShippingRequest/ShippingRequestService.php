<?php

namespace App\Service\ShippingRequest;

use App\Entity\FiltreSup;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\ShippingRequest\ShippingRequestExpectedLine;
use App\Entity\Utilisateur;
use App\Service\FormatService;
use App\Service\VisibleColumnService;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class ShippingRequestService {

    #[Required]
    public VisibleColumnService $visibleColumnService;

    #[Required]
    public Security $security;

    #[Required]
    public FormatService $formatService;

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
            ['title' => 'Livraison à titre gracieux', 'name' => 'freeDelivery'],
            ['title' => 'Articles conformes', 'name' => 'compliantArticles'],
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

    public function getDataForDatatable(Request $request, EntityManager $entityManager) : array {
        $shippingRepository = $entityManager->getRepository(ShippingRequest::class);
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);
        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_SHIPPING, $this->security->getUser());

        $queryResult = $shippingRepository->findByParamsAndFilters(
            $request->request,
            $filters,
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

        $formatService = $this->formatService;

        $row = [
            "actions" => $this->templating->render('shipping_request/actions.html.twig', [
                'url' => $url,
            ]),
            "number" => $shipping->getNumber(),
            "status" => $formatService->status($shipping->getStatus()),
            "createdAt" => $formatService->datetime($shipping->getCreatedAt()),
            "requestCaredAt" => $formatService->datetime($shipping->getRequestCaredAt()),
            "validatedAt" => $formatService->datetime($shipping->getValidatedAt()),
            "plannedAt" => $formatService->datetime($shipping->getPlannedAt()),
            "expectedPickedAt" => $formatService->datetime($shipping->getExpectedPickedAt()),
            "treatedAt" => $formatService->datetime($shipping->getTreatedAt()),
            "requesters" => $formatService->users($shipping->getRequesters()),
            "customerOrderNumber" => $shipping->getCustomerOrderNumber(),
            "freeDelivery" => $formatService->bool($shipping->isFreeDelivery()),
            "compliantArticles" => $formatService->bool($shipping->isCompliantArticles()),
            "customerName" => $shipping->getCustomerName(),
            "customerRecipient" => $shipping->getCustomerRecipient(),
            "customerPhone" => $shipping->getCustomerPhone(),
            "customerAddress" => $shipping->getCustomerAddress(),
            "carrier" => $formatService->carrier($shipping->getCarrier()),
            "trackingNumber" => $shipping->getTrackingNumber(),
            "shipment" => ShippingRequest::SHIPMENT_LABELS[$shipping->getShipment()] ?? '',
            "carrying" => ShippingRequest::CARRYING_LABELS[$shipping->getCarrying()] ?? '',
            "comment" => $shipping->getComment(),
            "grossWeight" => $shipping->getGrossWeight(),
        ];

        return $row;
    }

    public function createHeaderTransportDetailsConfig(ShippingRequest $shippingRequest){
        $packsCount = $shippingRequest->getExpectedLines()->count();

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
