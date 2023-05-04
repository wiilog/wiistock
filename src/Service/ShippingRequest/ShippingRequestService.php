<?php

namespace App\Service\ShippingRequest;

use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\ShippingRequest\ShippingRequestExpectedLine;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Service\FormatService;
use App\Service\VisibleColumnService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
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

    public function getDataForDatatable(EntityManagerInterface $entityManager, Request $request) : array{
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
        $url = $this->router->generate('shipping_show_page', [
            "id" => $shipping->getId()
        ]);

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

    public function updateShippingRequest(EntityManagerInterface $entityManager, ShippingRequest $shippingRequest, $data): bool {
        $carrierRepository = $entityManager->getRepository(Transporteur::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $requiredFields= [
            'requesters',
            'requesterPhoneNumbers',
            'customerOrderNumber',
            'customerName',
            'customerAddress',
            'customerPhone',
            'requestCaredAt',
            'shipment',
            'carrying',
        ];

        foreach ($requiredFields as $requiredField) {
            if (empty($data[$requiredField])) {
                throw new FormException("Une erreur est survenue un champ requis est manquant");
            }
        }

        $requestersIds = Stream::from(explode(',', $data['requesters'] ?? ''))->filter();
        if (count($requestersIds) > 0) {
            $requesters = new ArrayCollection(
               $requestersIds
                    ->map(fn($requesterId) => $userRepository->find($requesterId))
                    ->toArray());
            $shippingRequest->setRequesters($requesters);
        } else {
            throw new FormException("Vous devez sélectionner au moins un demandeur");
        }

        $carrierId = $data['carrier'] ?? '';
        if ($carrierId) {
            $carrier = $carrierRepository->find($carrierId);
            $shippingRequest->setCarrier($carrier);
        } else {
            throw new FormException("Vous devez sélectionner un transporteur");
        }

        $shippingRequest
            ->setRequesterPhoneNumbers(explode(',', $data['requesterPhoneNumbers'] ?? ''))
            ->setCustomerOrderNumber($data['customerOrderNumber'] ?? '')
            ->setFreeDelivery(boolval($data['freeDelivery'] ?? false))
            ->setCompliantArticles(boolval($data['compliantArticles'] ?? false))
            ->setCustomerName($data['customerName'] ?? '')
            ->setCustomerPhone($data['customerPhone'] ?? '')
            ->setCustomerRecipient($data['customerRecipient'] ?? '')
            ->setCustomerAddress($data['customerAddress'] ?? '')
            ->setRequestCaredAt($this->formatService->parseDatetime($data['requestCaredAt'] ?? '', ))
            ->setShipment($data['shipment'] ?? '')
            ->setCarrying($data['carrying'] ?? '')
            ->setComment($data['comment'] ?? '');

        return true;
    }

    public function createHeaderTransportDetailsConfig(ShippingRequest $shippingRequest) {
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
