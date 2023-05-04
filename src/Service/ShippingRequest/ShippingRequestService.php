<?php

namespace App\Service\ShippingRequest;

use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\Utilisateur;
use App\Service\FormatService;
use App\Service\VisibleColumnService;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;

class ShippingRequestService {

    #[Required]
    public VisibleColumnService $visibleColumnService;

    #[Required]
    public Security $security;

    #[Required]
    public FormatService $formatService;

    public function getVisibleColumnsConfig(Utilisateur $currentUser): array {
        $columnsVisible = $currentUser->getVisibleColumns()['shippingRequest'];
        $columns = [
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
        $formatService = $this->formatService;

        $row = [
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
}
