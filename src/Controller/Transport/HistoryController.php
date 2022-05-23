<?php


namespace App\Controller\Transport;

use App\Entity\StatusHistory;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportCollectRequestLine;
use App\Entity\Transport\TransportDeliveryOrderPack;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportDeliveryRequestLine;
use App\Entity\Transport\TransportHistory;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
use App\Helper\FormatHelper;
use App\Service\Transport\TransportHistoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Exception\RuntimeException;
use WiiCommon\Helper\Stream;

class HistoryController extends AbstractController
{
    public const REQUEST = "request";
    public const ORDER = "order";

    #[Route("/{type}/{id}/status-history-api", name: "status_history_api", options: ['expose' => true], methods: "GET")]
    public function statusHistoryApi(int $id, string $type, EntityManagerInterface $entityManager): JsonResponse {
        $entity = null;
        if($type === self::ORDER) {
            $entity = $entityManager->find(TransportOrder::class, $id);
        }
        else if ($type === self::REQUEST) {
            $entity = $entityManager->find(TransportRequest::class, $id);

            $round = null;
            $order = $entity->getOrder();
            if($order) {
                $round = $order->getTransportRoundLines()->last() ?: null;
            }
        }

        if ($entity instanceof TransportOrder) {
            $isDelivery = $entity->getRequest() instanceof TransportDeliveryRequest;
            $isDeliveryCollect = $isDelivery && $entity->getRequest()->getCollect();

            $statusWorkflow =  $isDeliveryCollect
                ? TransportOrder::STATUS_WORKFLOW_DELIVERY_COLLECT
                : ($isDelivery
                    ? TransportOrder::STATUS_WORKFLOW_DELIVERY
                    : TransportOrder::STATUS_WORKFLOW_COLLECT);
        } else if ($entity instanceof TransportDeliveryRequest) {
            $statusWorkflow = $entity->isSubcontracted()
                ? TransportRequest::STATUS_WORKFLOW_DELIVERY_SUBCONTRACTED
                : ($entity->getCollect()
                    ? TransportRequest::STATUS_WORKFLOW_DELIVERY_COLLECT
                    : TransportRequest::STATUS_WORKFLOW_DELIVERY_CLASSIC);
        } else if($entity instanceof TransportCollectRequest) {
            $statusWorkflow = TransportRequest::STATUS_WORKFLOW_COLLECT;
        } else {
            throw new RuntimeException('Unknown transport type');
        }

        return $this->json([
            "success" => true,
            "template" => $this->renderView('transport/request/timelines/status-history.html.twig', [
                "timeSlot" => $entity instanceof TransportCollectRequest ? $entity->getTimeSlot() : null,
                "statusWorkflow" => $statusWorkflow,
                "statusesHistory" => Stream::from($entity->getStatusHistory())
                    ->map(fn(StatusHistory $statusHistory) => [
                        "status" => FormatHelper::status($statusHistory->getStatus()),
                        "date" => FormatHelper::longDate($statusHistory->getDate(), ["short" => true, "time" => true])
                    ])
                    ->toArray(),
                "entity" => $entity,
                "round" => $round ?? null
            ]),
        ]);
    }

    #[Route("/{type}/{id}/transport-history-api", name: "transport_history_api", options: ['expose' => true], methods: "GET")]
    public function transportHistoryApi(int $id,
                                        string $type,
                                        TransportHistoryService $transportHistoryService,
                                        EntityManagerInterface $entityManager): JsonResponse {
        $entity = null;

        if($type === self::ORDER ) {
            $entity = $entityManager->find(TransportOrder::class, $id);
        }
        else if ($type === self::REQUEST) {
            $entity = $entityManager->find(TransportRequest::class, $id);
        }

        return $this->json([
            "success" => true,
            "template" => $this->renderView('transport/request/timelines/transport-history.html.twig', [
                "entity" => $entity,
                "history" => Stream::from($entity->getHistory())
                    ->sort(fn(TransportHistory $h1, TransportHistory $h2) => $h2->getDate() <=> $h1->getDate())
                    ->map(fn (TransportHistory $transportHistory) => [
                        'record' => $transportHistory,
                        'icon' => $transportHistoryService->getIconFromType($transportHistory->getType()),
                    ])
                    ->toArray()
            ]),
        ]);
    }

    #[Route("/{type}/{id}/transport-packs-api", name: "transport_packs_api", options: ['expose' => true], methods: "GET")]
    public function transportPacksApi(int $id, string $type, EntityManagerInterface $entityManager): JsonResponse {
        $transportRequest = null;

        if ($type === self::ORDER) {
            $transportRequest = $entityManager->find(TransportOrder::class, $id)->getRequest();
        }
        else if ($type === self::REQUEST) {
            $transportRequest = $entityManager->find(TransportRequest::class, $id);
        }

        $transportDelivery = $transportRequest instanceof TransportDeliveryRequest ? $transportRequest : null;
        $transportCollect = $transportRequest instanceof TransportCollectRequest ? $transportRequest : $transportDelivery->getCollect();

        $transportDeliveryRequestLines = $transportDelivery
            ? $transportDelivery->getLines()
                ->filter(fn($line) => $line instanceof TransportDeliveryRequestLine)
                ->toArray()
            : [];

        $transportCollectRequestLines = $transportCollect
            ? $transportCollect->getLines()
                ->filter(fn($line) => $line instanceof TransportCollectRequestLine)
                ->toArray()
            : [];

        $requestPacksList = Stream::from($transportRequest->getOrder()?->getPacks() ?: []);

        $packCounter = $requestPacksList->count();
        /* [natureId => [Pack, Pack]] */
        $associatedNaturesAndPacks = $requestPacksList
            ->keymap(function(TransportDeliveryOrderPack $transportDeliveryOrderPack) {
                $nature = $transportDeliveryOrderPack->getPack()->getNature();
                return [
                    $nature->getId(),
                    $transportDeliveryOrderPack
                ];
            }, true)
            ->toArray();

        if ($transportRequest instanceof TransportCollectRequest) {
            $packingLabel = '';
        }
        else {
            $packingLabel = $packCounter > 0
                ? ($packCounter . ' colis')
                : 'Colisage non fait';
        }

        return $this->json([
            "success" => true,
            "packingLabel" => $packingLabel,
            "template" => $this->renderView('transport/request/packs.html.twig', [
                "transportCollectRequestLines" => $transportCollectRequestLines,
                "transportDeliveryRequestLines" => $transportDeliveryRequestLines,
                "associatedNaturesAndPacks" => $associatedNaturesAndPacks,
                "request" => $transportRequest
            ]),
        ]);
    }
}
