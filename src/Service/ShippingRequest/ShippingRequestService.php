<?php

namespace App\Service\ShippingRequest;

use App\Entity\Article;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\ShippingRequest\ShippingRequestExpectedLine;
use App\Entity\ShippingRequest\ShippingRequestPack;
use App\Entity\Utilisateur;
use App\Service\CSVExportService;
use App\Service\FormatService;
use App\Service\VisibleColumnService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class ShippingRequestService {

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public VisibleColumnService $visibleColumnService;

    #[Required]
    public Security $security;

    #[Required]
    public CSVExportService $CSVExportService;

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
        $row = [
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
            "freeDelivery" => $shipping->isFreeDelivery() ? 'Oui' : 'Non',
            "compliantArticles" => $shipping->isCompliantArticles() ? 'Oui' : 'Non',
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

    public function putShippingRequestLine($output, ShippingRequest $shippingRequest, ShippingRequestPack|ShippingRequestExpectedLine $line, Article $article = null): void {
        $isPacked = isset($article) && $line instanceof ShippingRequestPack;
        if ($isPacked) {
            $expectedLine =$shippingRequest->getExpectedLine($article->getReferenceArticle());
        }

        $line = [
            $shippingRequest->getNumber(),
            $shippingRequest->getStatus()->getCode(),
            $shippingRequest->getCreatedAt()->format("d/m/Y H:i"),
            $shippingRequest->getValidatedAt()->format("d/m/Y H:i"),
            $shippingRequest->getPlannedAt()->format("d/m/Y H:i"),
            $shippingRequest->getExpectedPickedAt()->format("d/m/Y H:i"),
            $shippingRequest->getTreatedAt()->format("d/m/Y H:i"),
            $shippingRequest->getRequestCaredAt()->format("d/m/Y H:i"),
            implode(",", Stream::from($shippingRequest->getRequesters())
                ->map(fn(Utilisateur $requester) => $requester->getUsername())
                ->toArray()),
            $shippingRequest->getCustomerOrderNumber(),
            $this->formatService->bool($shippingRequest->isFreeDelivery()),
            $this->formatService->bool($shippingRequest->isCompliantArticles()),
            $shippingRequest->getCustomerName(),
            $shippingRequest->getCustomerRecipient(),
            $shippingRequest->getCustomerPhone(),
            $shippingRequest->getCustomerAddress(),

            $isPacked ? $line->getPack()->getCode() : '',
            $isPacked ? $line->getPack()->getNature()->getLabel() : '',
            $isPacked ? '' : $line->getReferenceArticle()->getReference(),
            $isPacked ? '' : $line->getReferenceArticle()->getLibelle(),
            $isPacked ? $article->getLabel() : '',
            $isPacked ? $article->getQuantite() : '',
            $isPacked ? $expectedLine->getPrice() : $line->getPrice(),
            $isPacked ? $expectedLine->getWeight() : $line->getWeight(),
            $isPacked ? $article->getQuantite()*$expectedLine->getPrice() : '',
            $isPacked ? $this->formatService->bool($expectedLine->getReferenceArticle()->isDangerousGoods()) : $this->formatService->bool($line->getReferenceArticle()->isDangerousGoods()),
            //FDS
            $isPacked ? $expectedLine->getReferenceArticle()->getOnuCode() : $line->getReferenceArticle()->getOnuCode(),
            $isPacked ? $expectedLine->getReferenceArticle()->getProductClass() : $line->getReferenceArticle()->getProductClass(),
            $isPacked ? $expectedLine->getReferenceArticle()->getNdpCode() : $line->getReferenceArticle()->getNdpCode(),
            $shippingRequest->getShipment(),
            $shippingRequest->getCarrying(),
            //nb colis = nombre de packLines
            //size
            //somme des poids net
            //grossWeight
            //somme des prix unitaires
            //nom transporteur
        ];

        $this->CSVExportService->putLine($output, $line);
    }
}
