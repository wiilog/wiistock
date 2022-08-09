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
use App\Entity\Transport\TransportRequestLine;
use App\Entity\Transport\TransportRound;
use App\Entity\Transport\TransportRoundLine;
use App\Helper\FormatHelper;
use App\Service\StringService;
use App\Service\Transport\TransportHistoryService;
use App\Service\Transport\TransportService;
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
    public const ROUND = "round";

    #[Route("/{type}/{id}/status-history-api", name: "status_history_api", options: ['expose' => true], methods: "GET")]
    public function statusHistoryApi(int $id,
                                     string $type,
                                     EntityManagerInterface $entityManager,
                                     TransportService $transportService): JsonResponse {
        $entity = null;
        if($type === self::ORDER) {
            $entity = $entityManager->find(TransportOrder::class, $id);
        }
        else if ($type === self::REQUEST) {
            $entity = $entityManager->find(TransportRequest::class, $id);

            $line = null;
            $order = $entity->getOrder();
            if($order) {
                $line = $order->getTransportRoundLines()->last() ?: null;
                if ($line) {
                    $estimatedAt = $line->getEstimatedAt();
                    $estimatedAtTime = $estimatedAt?->format("H:i");
                    $estimatedTimeSlot = $transportService->hourToTimeSlot($entityManager, $estimatedAtTime)
                        ?? $estimatedAtTime;
                }
            }
        }
        else if ($type === self::ROUND) {
            $entity = $entityManager->find(TransportRound::class, $id);
        }

        if ($entity instanceof TransportOrder) {
            $isDelivery = $entity->getRequest() instanceof TransportDeliveryRequest;
            $isDeliveryCollect = $isDelivery && $entity->getRequest()->getCollect();

            $statusWorkflowDeliveryCollect = TransportOrder::STATUS_WORKFLOW_DELIVERY_COLLECT;
            $request = $entity->getRequest();
            if ($request instanceof TransportDeliveryRequest
                && $entity->isFinished()
                && $request->getCollect()?->isNotCollected()) {
                array_pop($statusWorkflowDeliveryCollect);
            }
            $statusWorkflow =  $isDeliveryCollect
                ? $statusWorkflowDeliveryCollect
                : ($isDelivery
                    ? TransportOrder::STATUS_WORKFLOW_DELIVERY
                    : TransportOrder::STATUS_WORKFLOW_COLLECT);
        } else if ($entity instanceof TransportDeliveryRequest) {
            $statusWorkflowDeliveryCollect = TransportRequest::STATUS_WORKFLOW_DELIVERY_COLLECT;
            if($entity->isFinished() && $entity->getCollect()?->isNotCollected()) {
                array_pop($statusWorkflowDeliveryCollect);
            }
            $statusWorkflow = $entity->isSubcontracted()
                ? TransportRequest::STATUS_WORKFLOW_DELIVERY_SUBCONTRACTED
                : ($entity->getCollect()
                    ? TransportRequest::STATUS_WORKFLOW_DELIVERY_COLLECT
                    : TransportRequest::STATUS_WORKFLOW_DELIVERY_CLASSIC);
        } else if($entity instanceof TransportCollectRequest) {
            $statusWorkflow = TransportRequest::STATUS_WORKFLOW_COLLECT;
        } else if ($entity instanceof TransportRound) {
            $statusWorkflow = TransportRound::STATUS_WORKFLOW_ROUND;
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
                "round" => $line ?? null,
                "estimatedTimeSlot" => $estimatedTimeSlot ?? null,
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
                    ->sort(fn(TransportHistory $h1, TransportHistory $h2) => (
                        ($h2->getDate() <=> $h1->getDate())
                        ?: ($h2->getId() <=> $h1->getId())
                    ))
                    ->map(fn (TransportHistory $transportHistory) => [
                        'record' => $transportHistory,
                        'icon' => $transportHistoryService->getIconFromType($transportHistory->getOrder() ?? $transportHistory->getRequest(), $transportHistory->getType()),
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
            ? Stream::from($transportDelivery->getLines())
                ->filter(fn(TransportRequestLine $line) => $line instanceof TransportDeliveryRequestLine)
                ->sort(fn(TransportDeliveryRequestLine $a, TransportDeliveryRequestLine $b) => StringService::mbstrcmp($a->getNature()->getLabel(), $b->getNature()->getLabel()))
                ->toArray()
            : [];

        $transportCollectRequestLines = $transportCollect
            ? Stream::from($transportCollect->getLines())
                ->filter(fn(TransportRequestLine$line) => $line instanceof TransportCollectRequestLine)
                ->sort(fn(TransportCollectRequestLine $a, TransportCollectRequestLine $b) => StringService::mbstrcmp($a->getNature()->getLabel(), $b->getNature()->getLabel()))
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

    #[Route("/{round}/round-transport-history-api", name: "round_transport_history_api", options: ['expose' => true], methods: "GET")]
    public function roundTransportListApi(TransportRound $round): JsonResponse {
        $currentLine = $round->getCurrentOnGoingLine();

        $timelineConfig = $round->getTransportRoundLines()
            ->map(function(TransportRoundLine $line) use ($currentLine) {
                $order = $line->getOrder();
                $request = $order?->getRequest();
                return [
                    'name' => $request?->getContact()?->getName(),
                    'link' => $order
                        ? $this->generateUrl('transport_order_show', ['transport' => $order->getId()])
                        : null,
                    'hint' => $request instanceof TransportCollectRequest ? 'Collecte' : 'Livraison',
                    'emergency' => $order?->hasRejectedPacks() || $order?->isRejected(),
                    'cancelled' => $order?->isCancelled(),
                    'estimated' => $line->getEstimatedAt(),
                    'state' => $currentLine?->getId() === $line->getId()
                        ? 'current'
                        : ($order?->getTreatedAt() ? 'past' : 'future'),
                    'real' => $line->getFulfilledAt()?->format(' H:i'),
                ];
            })
            ->toArray();

        return $this->json([
            "success" => true,
            "template" => $this->renderView('transport/round/transport_timeline.html.twig', [
                'config' => $timelineConfig,
            ]),
        ]);
    }
}
