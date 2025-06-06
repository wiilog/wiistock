<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Controller\AbstractController;
use App\Entity\Attachment;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\FreeField\FreeField;
use App\Entity\Nature;
use App\Entity\Notification;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportCollectRequestLine;
use App\Entity\Transport\TransportDeliveryOrderPack;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportDeliveryRequestLine;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
use App\Entity\Transport\TransportRequestLine;
use App\Entity\Transport\TransportRound;
use App\Entity\Transport\TransportRoundLine;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use App\Service\AttachmentService;
use App\Service\LocationService;
use App\Service\NotificationService;
use App\Service\OperationHistoryService;
use App\Service\SettingsService;
use App\Service\StatusHistoryService;
use App\Service\Tracking\PackService;
use App\Service\Tracking\TrackingMovementService;
use App\Service\TranslationService;
use App\Service\Transport\TransportRoundService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;

#[Route("/api/mobile")]
class TransportController extends AbstractController {

    #[Route("/transport-rounds", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function transportRounds(EntityManagerInterface $manager): Response
    {
        $transportRoundRepository = $manager->getRepository(TransportRound::class);
        $user = $this->getUser();

        $transportRounds = $transportRoundRepository->findMobileTransportRoundsByUser($user);
        $data = Stream::from($transportRounds)
            ->map(fn(TransportRound $round) => $this->serializeRound($manager, $round))
            ->toArray();

        return $this->json($data);
    }

    #[Route("/fetch-transport", methods: [self::GET])]
    #[Wii\RestVersionChecked]
    public function fetchSingleTransport(Request $request, EntityManagerInterface $manager): Response
    {
        $transportRequest = $manager->find(TransportRequest::class, $request->query->get("request"));

        return $this->json($this->serializeTransport($manager, $transportRequest));
    }

    #[Route("/fetch-round", methods: [self::GET])]
    #[Wii\RestVersionChecked]
    public function fetchSingleRound(Request $request, EntityManagerInterface $manager): Response {
        $round = $request->query->get("round") ? $manager->find(TransportRound::class, $request->query->get("round"))
            : $manager->find(TransportRequest::class, $request->query->get("request"))->getOrder()->getTransportRoundLines()->last()->getTransportRound();

        return $this->json($this->serializeRound($manager, $round));
    }

    private function serializeRound(EntityManagerInterface $manager, TransportRound $round)
    {
        $lines = $round->getTransportRoundLines();

        $totalLoaded = Stream::from($lines)
            ->flatMap(fn(TransportRoundLine $line) => $line->getOrder()->getPacks()->toArray())
            ->filter(fn(TransportDeliveryOrderPack $orderPack) => !$orderPack->getRejectReason() && $orderPack->getOrder()->getStatus()->getCode() !== TransportOrder::STATUS_CANCELLED)
            ->count();

        $loadedPacks = Stream::from($lines)
            ->flatMap(fn(TransportRoundLine $line) => $line->getOrder()->getPacks()->toArray())
            ->filter(fn(TransportDeliveryOrderPack $orderPack) => (
                $orderPack->getOrder()->getStatus()->getCode() !== TransportOrder::STATUS_CANCELLED && $orderPack->getState() && !$orderPack->getRejectReason()
            ))
            ->count();

        // deliveries where packing is done
        $readyDeliveries = Stream::from($lines)
            ->filter(fn(TransportRoundLine $line) => (
                $line->getOrder()->getRequest() instanceof TransportDeliveryRequest
                && !$line->getOrder()->getPacks()->isEmpty()
                && !Stream::from($line->getOrder()->getPacks())
                    ->every(fn(TransportDeliveryOrderPack $pack) => $pack->getRejectReason())
            ))
            ->count();

        $collectedPacks = 0;
        $packsToCollect = 0;
        foreach ($lines as $line) {
            $request = $line->getOrder()->getRequest();
            if (!($request instanceof TransportCollectRequest)) {
                continue;
            }

            $transportItems = $request->getLines();
            /** @var TransportCollectRequestLine $item */
            foreach ($transportItems as $item) {
                $collectedPacks += $item->getCollectedQuantity();
                $packsToCollect += $item->getQuantityToCollect();
            }
        }

        $notDeliveredOrders = Stream::from($lines)
            ->filter(fn(TransportRoundLine $line) => in_array($line->getOrder()->getStatus()->getCode(),
                [TransportOrder::STATUS_CANCELLED, TransportOrder::STATUS_NOT_DELIVERED]));

        $returned = Stream::from($notDeliveredOrders)
            ->flatMap(fn(TransportRoundLine $line) => $line->getOrder()->getPacks())
            ->filter(fn(TransportDeliveryOrderPack $pack) => $pack->getState() === TransportDeliveryOrderPack::RETURNED_STATE)
            ->count();

        $toReturn = Stream::from($notDeliveredOrders)
            ->flatMap(fn(TransportRoundLine $line) => $line->getOrder()->getPacks()
                ->filter(fn(TransportDeliveryOrderPack $orderPack) => $orderPack->getState() !== TransportDeliveryOrderPack::REJECTED_STATE))
            ->count();

        $collectedOrders = Stream::from($lines)
            ->filter(fn(TransportRoundLine $line) => ($line->getOrder()->getRequest() instanceof TransportCollectRequest
                    || $line->getOrder()->getRequest()->getCollect())
                && $line->getOrder()->getStatus()->getCode() === TransportOrder::STATUS_FINISHED);

        $depositedPacks = Stream::from($collectedOrders)
            ->flatMap(fn(TransportRoundLine $line) => $line->getOrder()->getRequest() instanceof TransportCollectRequest
                ? $line->getOrder()->getRequest()->getLines()
                : $line->getOrder()->getRequest()->getCollect()->getLines())
            ->map(fn(TransportRequestLine $line) => $line->getDepositedQuantity())
            ->sum();

        $toDeposit = Stream::from($collectedOrders)
            ->flatMap(fn(TransportRoundLine $line) => $line->getOrder()->getRequest() instanceof TransportCollectRequest
                ? $line->getOrder()->getRequest()->getLines()
                : $line->getOrder()->getRequest()->getCollect()->getLines())
            ->map(fn(TransportRequestLine $line) => $line->getCollectedQuantity())
            ->sum();

        $doneCollects = Stream::from($lines)
            ->filterMap(fn(TransportRoundLine $line) => $line->getOrder()?->getRequest())
            ->filter(fn(TransportRequest $request) => (
                ($request instanceof TransportCollectRequest && $request->getStatus()->getCode() !== TransportRequest::STATUS_NOT_COLLECTED)
                || ($request instanceof TransportDeliveryRequest && $request->getCollect()?->getStatus()->getCode() !== TransportRequest::STATUS_NOT_COLLECTED)
            ))
            ->count();

        return [
            'id' => $round->getId(),
            'number' => $round->getNumber(),
            'status' => FormatHelper::status($round->getStatus()),
            'is_ongoing' => $round->getStatus()->getCode() === TransportRound::STATUS_ONGOING,
            'date' => FormatHelper::date($round->getExpectedAt()),
            'estimated_distance' => $round->getEstimatedDistance(),
            'estimated_time' => str_replace(':', 'h', $round->getEstimatedTime()) . 'min',
            'ready_deliveries' => $readyDeliveries,
            'total_ready_deliveries' => Stream::from($lines)
                ->filter(fn(TransportRoundLine $line) => $line->getOrder()
                        ->getRequest() instanceof TransportDeliveryRequest)
                ->count(),
            'loaded_packs' => $loadedPacks,
            'total_loaded' => $totalLoaded,
            'done_transports' => Stream::from($lines)
                ->filter(function (TransportRoundLine $line) {
                    $order = $line->getOrder();
                    $request = $order->getRequest();

                    return ($line->getFulfilledAt() || $line->getFailedAt() || $line->getRejectedAt()
                            || ($request->getStatus()->getCode() !== TransportRequest::STATUS_ONGOING
                                && $request->getStatus()->getCode() !== TransportRequest::STATUS_TO_COLLECT
                                && $request->getStatus()->getCode() !== TransportRequest::STATUS_TO_DELIVER))
                        && !$line->getCancelledAt();
                })
                ->count(),
            'total_transports' => Stream::from($lines)
                ->filter(fn(TransportRoundLine $line) => !$line->getCancelledAt())
                ->count(),
            'collected_packs' => $collectedPacks,
            'to_collect_packs' => $packsToCollect,
            "not_delivered" => $notDeliveredOrders->count(),
            "returned_packs" => $returned,
            "packs_to_return" => $toReturn,
            "done_collects" => $doneCollects,
            "deposited_packs" => $depositedPacks,
            "packs_to_deposit" => $toDeposit,
            "deposited_delivery_packs" => $round->hasNoDeliveryToReturn(),
            "deposited_collect_packs" => $round->hasNoCollectToReturn(),
            "lines" => Stream::from($lines)
                ->filter(fn(TransportRoundLine $line) => !$line->getCancelledAt()
                    || ($line->getTransportRound()->getBeganAt()
                        && $line->getCancelledAt() > $line->getTransportRound()
                            ->getBeganAt()))
                ->sort(function (TransportRoundLine $a, TransportRoundLine $b) {
                    $aOrder = $a->getOrder();
                    $bOrder = $b->getOrder();
                    $aStatus = $aOrder->getStatus();
                    $bStatus = $bOrder->getStatus();
                    if (($aStatus?->getCode() === TransportOrder::STATUS_ONGOING && $bStatus?->getCode() === TransportOrder::STATUS_ONGOING)
                        || ($aStatus?->getCode() !== TransportOrder::STATUS_ONGOING && $bStatus?->getCode() !== TransportOrder::STATUS_ONGOING)) {
                        return $a->getPriority() <=> $b->getPriority();
                    } // at least one ongoing and one cancelled or finished then the ongoing is the first
                    else if ($aStatus?->getCode() === TransportOrder::STATUS_ONGOING) {
                        return -1;
                    } else { // if ($bStatus?->getCode() === TransportOrder::STATUS_ONGOING) {
                        return 1;
                    }
                })
                ->map(fn(TransportRoundLine $line) => $this->serializeTransport($manager, $line))
                ->values(),
            "to_finish" => Stream::from($lines)
                ->map(fn(TransportRoundLine $line) => $line->getFulfilledAt() || $line->getCancelledAt() || $line->getFailedAt() || $line->getRejectedAt())
                ->every(),
        ];
    }

    private function serializeTransport(EntityManagerInterface              $manager,
                                        TransportRoundLine|TransportRequest $request,
                                        TransportRoundLine                  $line = null): array
    {
        if ($request instanceof TransportRoundLine) {
            $line = $request;
            $order = $line->getOrder();
            $request = $order->getRequest();
        } else {
            $order = $request->getOrder();
            $line = $line ?? $order->getTransportRoundLines()->last();
        }

        $collect = $request instanceof TransportDeliveryRequest ? $request->getCollect() : null;
        $contact = $request->getContact();
        $isCollect = $request instanceof TransportCollectRequest;
        $categoryFF = $isCollect
            ? CategorieCL::COLLECT_TRANSPORT
            : CategorieCL::DELIVERY_TRANSPORT;
        $freeFields = $manager->getRepository(FreeField::class)->findByTypeAndCategorieCLLabel($request->getType(),
            $categoryFF);
        $freeFieldsValues = $request->getFreeFields();
        $temperatureRanges = Stream::from($request->getLines())
            ->filter(fn($line) => $line instanceof TransportDeliveryRequestLine)
            ->keymap(fn(TransportDeliveryRequestLine $line) => [
                $this->getFormatter()->nature($line->getNature()),
                $line->getTemperatureRange()?->getValue(),
            ])->toArray();

        if ($request instanceof TransportCollectRequest) {
            $naturesToCollect = $request->getLines()
                ->map(fn(TransportCollectRequestLine $line) => [
                    "nature_id" => $line->getNature()->getId(),
                    "nature" => $this->getFormatter()->nature($line->getNature()),
                    "color" => $line->getNature()->getColor(),
                    "quantity_to_collect" => $line->getQuantityToCollect(),
                    "collected_quantity" => $line->getCollectedQuantity(),
                    "deposited_quantity" => $line->getDepositedQuantity(),
                ])
                ->toArray();
        } else {
            $naturesToCollect = null;
        }

        $expectedAt = $isCollect && $request->getTimeSlot()
            ? FormatHelper::date($request->getExpectedAt()) . " " . $request->getTimeSlot()?->getName()
            : ($isCollect
                ? FormatHelper::datetime($request->getDelivery()?->getExpectedAt())
                : FormatHelper::datetime($request->getExpectedAt()));

        return [
            'id' => $request->getId(),
            'number' => $request->getNumber(),
            'status' => $this->getFormatter()->status($request->getStatus()),
            'type' => FormatHelper::type($request->getType()),
            'type_icon' => $request->getType()?->getLogo() ? $_SERVER["APP_URL"] . $request->getType()
                    ->getLogo()
                    ->getFullPath() : null,
            'kind' => $isCollect ? 'collect' : 'delivery',
            'collect' => $collect ? array_merge($this->serializeTransport($manager, $collect, $line), [
                "from_delivery" => true,
            ]) : null,
            'natures_to_collect' => $naturesToCollect,
            'packs' => Stream::from($order->getPacks())
                ->filter(fn(TransportDeliveryOrderPack $orderPack) => $orderPack->getState() !== TransportDeliveryOrderPack::RETURNED_STATE)
                ->map(function (TransportDeliveryOrderPack $orderPack) use ($temperatureRanges) {
                    $pack = $orderPack->getPack();
                    $nature = $pack->getNature();

                    return [
                        'code' => $pack->getCode(),
                        'nature' => $this->getFormatter()->nature($nature),
                        'nature_id' => $nature->getId(),
                        'temperature_range' => $temperatureRanges[$this->getFormatter()->nature($nature)],
                        'color' => $nature->getColor(),
                        'rejected' => $orderPack->getState() === TransportDeliveryOrderPack::REJECTED_STATE,
                        'loaded' => $orderPack->getState() === TransportDeliveryOrderPack::LOADED_STATE,
                        'delivered' => $orderPack->getState() === TransportDeliveryOrderPack::DELIVERED_STATE,
                        'returned' => $orderPack->getState() === TransportDeliveryOrderPack::RETURNED_STATE,
                    ];
                })
                ->values(),
            'expected_at' => $expectedAt,
            'estimated_time' => $line->getEstimatedAt()?->format('H:i'),
            'fulfilled_time' => $line->getFulfilledAt()?->format('H:i'),
            'expected_time' => $request->getExpectedAt()?->format('H:i'),
            'time_slot' => $isCollect ? $request->getTimeSlot()?->getName() : null,
            'contact' => [
                'file_number' => $contact->getFileNumber(),
                'name' => $contact->getName(),
                'address' => FormatHelper::phone(str_replace("\n", "<br>", $contact->getAddress())),
                'contact' => FormatHelper::phone($contact->getContact()),
                'person_to_contact' => FormatHelper::phone($contact->getPersonToContact()),
                'observation' => FormatHelper::phone($contact->getObservation()),
                'latitude' => $contact->getAddressLatitude(),
                'longitude' => $contact->getAddressLongitude(),
            ],
            'comment' => $order->getComment(),
            'photos' => Stream::from($order->getAttachments())
                ->map(fn(Attachment $attachment) => $attachment->getFullPath()),
            'signature' => $order->getSignature()?->getFullPath(),
            'reject_motive' => $order->getReturnReason(),
            'requester' => FormatHelper::user($request->getCreatedBy()),
            'free_fields' => Stream::from($freeFields)
                ->map(function (FreeField $freeField) use ($line, $freeFieldsValues) {
                    return [
                        'id' => $freeField->getId(),
                        'label' => $freeField->getLabel(),
                        'value' => FormatHelper::freeField($freeFieldsValues[$freeField->getId()] ?? "", $freeField),
                    ];
                }),
            'priority' => $line->getPriority(),
            'cancelled' => !!$line->getCancelledAt(),
            'success' => (
                (
                    $request->getStatus()->getCode() === TransportRequest::STATUS_FINISHED
                    || (
                        // si c'est une collecte d'une livraison collecte => false
                        !($request instanceof TransportCollectRequest && $request->getDelivery())
                        && $line->getFulfilledAt()
                    )
                )
                && !$line->getFailedAt()
                && !$line->getRejectedAt()
            ),
            'failure' =>
                ($request instanceof TransportCollectRequest
                && $request->getDelivery()
                && $request->getStatus()->getCode() === TransportRequest::STATUS_NOT_COLLECTED)
                || ($line->getRejectedAt() || $line->getFailedAt()),
        ];
    }

    #[Route("/reject-motives", methods: [self::GET])]
    #[Wii\RestVersionChecked]
    public function rejectMotives(EntityManagerInterface $manager,
                                  SettingsService        $settingsService): Response
    {
        $packRejectMotives = $settingsService->getValue($manager, Setting::TRANSPORT_ROUND_PACK_REJECT_MOTIVES);
        $deliveryRejectMotives = $settingsService->getValue($manager, Setting::TRANSPORT_ROUND_DELIVERY_REJECT_MOTIVES);
        $collectRejectMotives = $settingsService->getValue($manager, Setting::TRANSPORT_ROUND_COLLECT_REJECT_MOTIVES);

        $packRejectMotives = $packRejectMotives ? explode(",", $packRejectMotives) : [];
        $deliveryRejectMotives = explode(",", $deliveryRejectMotives);
        $collectRejectMotives = explode(",", $collectRejectMotives);

        $deliveryRejectMotives[] = 'Autre';
        $collectRejectMotives[] = 'Autre';
        return $this->json([
            'pack' => $packRejectMotives,
            'delivery' => array_unique($deliveryRejectMotives),
            'collect' => array_unique($collectRejectMotives),
        ]);
    }

    #[Route("/reject-pack", methods: [self::POST])]
    #[Wii\RestVersionChecked]
    public function rejectPack(Request                 $request,
                               EntityManagerInterface  $manager,
                               TransportRoundService   $transportRoundService,
                               OperationHistoryService $historyService): Response
    {
        $data = $request->request;
        $pack = $manager->getRepository(Pack::class)->findOneBy(['code' => $data->get('pack')]);
        $rejectMotive = $data->get('rejectMotive');
        $currentUser = $this->getUser();

        $transportDeliveryOrderPack = $pack->getTransportDeliveryOrderPack();
        $order = $transportDeliveryOrderPack->getOrder();

        $request = $order->getRequest();
        $round = $order->getTransportRoundLines()->last()->getTransportRound();

        $transportDeliveryOrderPack
            ->setRejectedBy($currentUser)
            ->setRejectReason($rejectMotive)
            ->setState(TransportDeliveryOrderPack::REJECTED_STATE);

        $round->setRejectedPackCount($round->getRejectedPackCount() + 1);

        $historyService->persistTransportHistory($manager,
            [$order, $request],
            OperationHistoryService::TYPE_DROP_REJECTED_PACK,
            [
                'user' => $currentUser,
                'pack' => $pack,
                'reason' => $rejectMotive,
            ]);

        $allPacksRejected = $order->getPacks()
            ->filter(fn(TransportDeliveryOrderPack $pack) => !$pack->getRejectedBy())
            ->isEmpty();

        if ($allPacksRejected) {
            $transportRoundService->reprepareTransportRoundDeliveryLine($manager, $order->getTransportRoundLines()->last(), $currentUser);
        }

        $manager->flush();

        return $this->json([
            'success' => true,
        ]);
    }

    #[Route("/load-packs", methods: [self::POST])]
    #[Wii\RestVersionChecked]
    public function loadPacks(Request                 $request,
                              EntityManagerInterface  $manager,
                              TrackingMovementService $trackingMovementService): Response
    {
        $data = $request->request;
        $packs = $manager->getRepository(Pack::class)->findBy(['code' => json_decode($data->get('packs'))]);
        $location = $manager->find(Emplacement::class, $data->get('location'));
        $now = new DateTime();
        $user = $this->getUser();

        foreach ($packs as $pack) {
            $pack->getTransportDeliveryOrderPack()?->setState(TransportDeliveryOrderPack::LOADED_STATE);

            $trackingMovement = $trackingMovementService
                ->createTrackingMovement(
                    $pack,
                    $location,
                    $user,
                    $now,
                    true,
                    true,
                    TrackingMovement::TYPE_DEPOSE,
                    [
                        'quantity' => $pack->getQuantity()
                    ]
                );
            $manager->persist($trackingMovement);
        }

        $manager->flush();
        return $this->json([
            'success' => true,
        ]);
    }

    #[Route("/finish-round", methods: [self::POST])]
    #[Wii\RestVersionChecked]
    public function finishRound(Request                 $request,
                                EntityManagerInterface  $manager,
                                TransportRoundService   $transportRoundService,
                                TrackingMovementService $trackingMovementService,
                                StatusHistoryService    $statusHistoryService,
                                OperationHistoryService $operationHistoryService): Response
    {
        $data = $request->request;
        $locationRepository = $manager->getRepository(Emplacement::class);
        $packRepository = $manager->getRepository(Pack::class);
        $round = $manager->find(TransportRound::class, $data->get('round'));
        $location = $locationRepository->find($data->get('location'));
        $packs = json_decode($data->get('packs'));
        $now = new DateTime();
        $user = $this->getUser();

        if (!empty($packs)) {
            $packsDropLocation = $locationRepository->find($data->get('packsDropLocation'));
            $packs = $packRepository->findBy(['code' => $packs]);

            $depositedTransports = [];
            $transportById = [];
            foreach ($packs as $pack) {
                $trackingMovement = $trackingMovementService->createTrackingMovement(
                    $pack,
                    $packsDropLocation,
                    $user,
                    $now,
                    true,
                    true,
                    TrackingMovement::TYPE_DEPOSE,
                    [
                        'quantity' => $pack->getQuantity()
                    ]
                );

                $manager->persist($trackingMovement);

                $transportDeliveryOrderPack = $pack->getTransportDeliveryOrderPack();
                $transportDeliveryOrderPack
                    ->setReturnedAt($now)
                    ->setState(TransportDeliveryOrderPack::RETURNED_STATE);

                $transport = $transportDeliveryOrderPack->getOrder();
                $transportById[$transport->getId()] = $transport;
                $depositedTransports[$transport->getId()][] = $pack;
            }

            foreach ($depositedTransports as $transport => $packs) {
                $transport = $transportById[$transport];

                $operationHistoryService->persistTransportHistory(
                    $manager,
                    [$transport, $transport->getRequest()],
                    OperationHistoryService::TYPE_PACKS_FAILED,
                    [
                        "user" => $user,
                        "message" => Stream::from($packs)
                            ->map(fn(Pack $pack) => $pack->getCode())
                            ->join(", "),
                        "location" => $packsDropLocation,
                    ]
                );
            }
        }

        $emptyRoundPack = $manager->getRepository(Pack::class)->findOneBy(['code' => Pack::EMPTY_ROUND_PACK]);
        $trackingMovement = $trackingMovementService->createTrackingMovement(
            $emptyRoundPack,
            $location,
            $user,
            $now,
            true,
            true,
            TrackingMovement::TYPE_EMPTY_ROUND,
            [
                'quantity' => 1
            ]
        );
        $manager->persist($trackingMovement);

        $round
            ->setRealDistance($transportRoundService->calculateRoundRealDistance($round))
            ->setEndedAt($now);

        $collectsToReturn = Stream::from($round->getTransportRoundLines())
            ->filterMap(fn(TransportRoundLine $line) => $line->getOrder()
                ->getRequest() instanceof TransportCollectRequest
                ? $line->getOrder()->getRequest()
                : $line->getOrder()->getRequest()->getCollect())
            ->flatMap(fn(TransportCollectRequest $collect) => $collect->getLines())
            ->filter(fn(TransportCollectRequestLine $line) => $line->getCollectedQuantity() != 0)
            ->count();

        if ($collectsToReturn === 0) {
            $round->setNoCollectToReturn(true);
        }

        $deliveriesWithPacksToReturn = Stream::from($round->getTransportRoundLines())
            ->filter(fn(TransportRoundLine $line) => Stream::from($line->getOrder()->getPacks())
                ->some(fn(TransportDeliveryOrderPack $pack) => $pack->getState() === TransportDeliveryOrderPack::LOADED_STATE))
            ->count();
        if ($deliveriesWithPacksToReturn === 0) {
            $round->setNoDeliveryToReturn(true);
        }

        $finishedStatus = $manager->getRepository(Statut::class)
            ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ROUND, TransportRound::STATUS_FINISHED);
        $statusHistoryService->updateStatus($manager, $round, $finishedStatus, [
            "initiatedBy" => $user,
        ]);

        $manager->flush();

        return $this->json([
            "success" => true,
        ]);
    }

    #[Route("/start-round", methods: [self::POST])]
    #[Wii\RestVersionChecked]
    public function startRound(Request                 $request,
                               EntityManagerInterface  $manager,
                               StatusHistoryService    $statusHistoryService,
                               TransportRoundService   $transportRoundService,
                               OperationHistoryService $historyService,
                               TranslationService      $translation): Response
    {
        $data = $request->request;
        $user = $this->getUser();
        $round = $manager->find(TransportRound::class, $data->get('round'));

        $statusRepository = $manager->getRepository(Statut::class);
        $roundOngoing = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ROUND,
            TransportRound::STATUS_ONGOING);
        $deliveryRequestOngoing = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_DELIVERY,
            TransportRequest::STATUS_ONGOING);
        $collectRequestOngoing = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_COLLECT,
            TransportRequest::STATUS_ONGOING);
        $deliveryOrderOngoing = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_DELIVERY,
            TransportOrder::STATUS_ONGOING);
        $collectOrderOngoing = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_COLLECT,
            TransportOrder::STATUS_ONGOING);

        if (!$round->getDeliverer()?->getVehicle()) {
            return $this->json([
                "success" => false,
                "msg" => "Vous n'avez pas de véhicule assigné, la tournée ne peut pas commencer",
            ]);
        }

        $round
            ->setStatus($roundOngoing)
            ->setBeganAt(new DateTime());

        //freeze the locations in case the deliverer's vehicle changes in the future
        $round->setLocations($round->getDeliverer()->getVehicle()->getLocations()->toArray());

        //freeze the vehicle in case it changes in the future
        $round->setVehicle($round->getDeliverer()->getVehicle());

        $hasRejected = false;

        $statusHistoryService->updateStatus($manager, $round, $deliveryOrderOngoing, [
            "initiatedBy" => $user,
        ]);

        foreach ($round->getTransportRoundLines() as $line) {
            $order = $line->getOrder();
            $request = $order->getRequest();

            if ($request->getStatus()->getCode() === TransportRequest::STATUS_CANCELLED) {
                continue;
            }

            if ($request instanceof TransportDeliveryRequest) {
                $hasPacks = !$order->getPacks()->isEmpty();
                $allRejected = $order->getPacks()
                    ->filter(fn(TransportDeliveryOrderPack $pack) => !$pack->getRejectedBy())
                    ->isEmpty();

                if ($hasPacks && !$allRejected) {
                    $requestStatus = $deliveryRequestOngoing;
                    $orderStatus = $deliveryOrderOngoing;
                } else if ($allRejected) {
                    $hasRejected = true;
                    $transportRoundService->rejectTransportRoundDeliveryLine($manager, $line, $user);
                }
            } else {
                $requestStatus = $collectRequestOngoing;
                $orderStatus = $collectOrderOngoing;
            }

            if (isset($requestStatus) && isset($orderStatus) && !$line->getRejectedAt()) {
                $statusHistoryRequest = $statusHistoryService->updateStatus($manager,
                    $request,
                    $deliveryRequestOngoing,
                    [
                        "initiatedBy" => $user,
                    ]
                );
                $statusHistoryOrder = $statusHistoryService->updateStatus($manager, $order, $deliveryOrderOngoing, [
                    "initiatedBy" => $user,
                ]);

                $historyService->persistTransportHistory($manager, $request, OperationHistoryService::TYPE_ONGOING, [
                    "user" => $user,
                    "history" => $statusHistoryRequest,
                ]);

                $historyService->persistTransportHistory($manager, $order, OperationHistoryService::TYPE_ONGOING, [
                    "user" => $user,
                    "history" => $statusHistoryOrder,
                ]);
            }
        }


        if ($round->getTransportRoundLines()->isEmpty()) {
            if ($hasRejected) {
                return $this->json([
                    "success" => false,
                    "msg" => "La tournée ne peut pas être débutée car aucune " . mb_strtolower($translation->translate("Demande", "Livraison", "Livraison", false)) . " de la tournée n'est valide",
                ]);
            } else {
                return $this->json([
                    "success" => false,
                    "msg" => "La tournée ne contient aucune ligne et ne peut être débutée",
                ]);
            }
        }

        $transportRoundService->updateTransportRoundLinePriority($round);

        $manager->flush();

        return $this->json([
            "success" => true,
            "round" => $this->serializeRound($manager, $round),
        ]);
    }

    #[Route("/has-new-packs", methods: [self::GET])]
    #[Wii\RestVersionChecked]
    public function hasNewPacks(Request $request, EntityManagerInterface $manager): Response
    {
        $data = $request->query;
        $round = $manager->find(TransportRound::class, $data->get('round'));
        $currentPacks = json_decode($data->get('packs'));

        $lines = $round->getTransportRoundLines();
        $updatedPacks = Stream::from($lines)
            ->filter(fn(TransportRoundLine $line) => $line->getOrder()->getStatus()->getCode() !== TransportOrder::STATUS_CANCELLED)
            ->flatMap(fn(TransportRoundLine $line) => $line->getOrder()->getPacks())
            ->map(fn(TransportDeliveryOrderPack $orderPack) => $orderPack->getPack()->getCode())
            ->toArray();

        $hasNewPacks = !Stream::diff($currentPacks, $updatedPacks)->isEmpty();

        return $this->json([
            "success" => true,
            "has_new_packs" => $hasNewPacks,
        ]);
    }

    #[Route("/finish-transport", methods: [self::POST])]
    #[Wii\RestVersionChecked]
    public function finishTransport(Request                 $request,
                                    EntityManagerInterface  $manager,
                                    OperationHistoryService $historyService,
                                    StatusHistoryService    $statusHistoryService,
                                    TrackingMovementService $trackingMovementService,
                                    AttachmentService       $attachmentService,
                                    LocationService         $locationService): Response
    {
        $data = $request->request;
        $files = $request->files;
        $request = $manager->find(TransportRequest::class, $data->get('id'));
        $originalRequest = $request;
        $order = $request->getOrder();
        $now = new DateTime('now');
        $user = $this->getUser();

        $isEdit = $request->getStatus()->getCode() !== TransportRequest::STATUS_ONGOING
            && $request->getStatus()->getCode() !== TransportRequest::STATUS_TO_DELIVER
            && $request->getStatus()->getCode() !== TransportRequest::STATUS_TO_COLLECT
            && $request->getStatus()->getCode() !== TransportRequest::STATUS_AWAITING_PLANNING
            && $request->getStatus()->getCode() !== TransportRequest::STATUS_AWAITING_VALIDATION;

        $signature = $files->get('signature');
        $photo = $files->get('photo');
        $files = [
            ...($signature ? [$signature] : []),
            ...($photo ? [$photo] : []),
        ];

        $locationRepository = $manager->getRepository(Emplacement::class);
        $patient = $locationRepository->findOneBy(["label" => "Patient"]);
        if (!$patient) {
            $patient = $locationService->persistLocation($manager, [
                FixedFieldEnum::name->name => "Patient",
                FixedFieldEnum::description->name => "Unités logistiques livrées chez un patient",
            ]);
        }

        $comment = $data->get('comment');

        if ($comment && $comment != $order->getComment()) {
            $this->updateTransportComment($manager, $historyService, $request, $comment);
        }

        $attachments = $attachmentService->persistAttachments($manager, $files, ["attachmentContainer" => $order]);

        if (!empty($attachments)) {
            $this->updateTransportAttachment($manager, $historyService, $request, $attachments);
        }

        if (!$isEdit) {
            $order->setTreatedAt($now);

            if ($request instanceof TransportCollectRequest && $request->getDelivery()) {
                $lastLine = $request->getDelivery()->getOrder()->getTransportRoundLines()->last();
            } else {
                $lastLine = $order->getTransportRoundLines()->last();
            }

            if ($lastLine) {
                $lastLine->setFulfilledAt($now);
            }

            if ($request instanceof TransportDeliveryRequest) {
                foreach ($order->getPacks() as $line) {
                    if (!$line->getRejectedBy()) {
                        $line->setState(TransportDeliveryOrderPack::DELIVERED_STATE);
                        $trackingMovement = $trackingMovementService
                            ->createTrackingMovement($line->getPack(),
                                $patient,
                                $user,
                                $now,
                                true,
                                true,
                                TrackingMovement::TYPE_DEPOSE,
                            [
                                'quantity' =>  $line->getPack()->getQuantity()
                            ]);
                        $manager->persist($trackingMovement);
                    }
                }
            } else {
                $collectedPacks = Stream::from(json_decode($data->get('collectedPacks'), true))
                    ->keymap(fn(array $nature) => [$nature["nature_id"], $nature["collected_quantity"]])
                    ->toArray();

                foreach ($order->getRequest()->getLines() as $line) {
                    if (isset($collectedPacks[$line->getNature()->getId()])) {
                        $line->setCollectedQuantity($collectedPacks[$line->getNature()->getId()]);
                    }
                }
            }

            // si c'est une collecte ou une livraison sans collecte
            if ($request instanceof TransportCollectRequest || $request->getCollect() === null) {
                $statusRepository = $manager->getRepository(Statut::class);

                // si on termine la collecte d'une livraison collecte, alors il faut
                // mettre a jour les données de la livraison car celles de la collecte
                // ne sont pas utilisées
                if ($request instanceof TransportCollectRequest && $request->getDelivery()) {
                    $request->setStatus($statusRepository->findOneByCategorieNameAndStatutCode(
                        CategorieStatut::TRANSPORT_REQUEST_COLLECT,
                        TransportRequest::STATUS_FINISHED
                    ));

                    $request = $request->getDelivery();
                    $order = $request->getOrder();
                }

                $requestCategory = $request instanceof TransportCollectRequest
                    ? CategorieStatut::TRANSPORT_REQUEST_COLLECT
                    : CategorieStatut::TRANSPORT_REQUEST_DELIVERY;

                $orderCategory = $request instanceof TransportCollectRequest
                    ? CategorieStatut::TRANSPORT_ORDER_COLLECT
                    : CategorieStatut::TRANSPORT_ORDER_DELIVERY;

                $requestStatus = $statusRepository->findOneByCategorieNameAndStatutCode($requestCategory,
                    TransportRequest::STATUS_FINISHED);
                $orderStatus = $statusRepository->findOneByCategorieNameAndStatutCode($orderCategory,
                    TransportOrder::STATUS_FINISHED);

                $statusHistoryRequest = $statusHistoryService->updateStatus($manager, $order->getRequest(), $requestStatus, [
                    "initiatedBy" => $user,
                ]);
                $statusHistoryOrder = $statusHistoryService->updateStatus($manager, $order, $orderStatus, [
                    "initiatedBy" => $user,
                ]);
            }

            if($originalRequest instanceof TransportCollectRequest && $originalRequest->getDelivery()) {
                $lastFinishedTransportOrderHistory = $order->getLastTransportHistory(OperationHistoryService::TYPE_FINISHED);
                $lastFinishedTransportRequestHistory = $request->getLastTransportHistory(OperationHistoryService::TYPE_FINISHED);

                foreach ([$lastFinishedTransportOrderHistory, $lastFinishedTransportRequestHistory] as $history) {
                    if ($history) {
                        $history
                            ->setDate($now)
                            ->setType(OperationHistoryService::TYPE_FINISHED_BOTH);
                    }
                }
            } else {
                $historyService->persistTransportHistory($manager, $order->getRequest(), OperationHistoryService::TYPE_FINISHED, [
                    "user" => $this->getUser(),
                    "history" => $statusHistoryRequest ?? null,
                ]);

                $historyService->persistTransportHistory($manager, $order, OperationHistoryService::TYPE_FINISHED, [
                    "user" => $this->getUser(),
                    "history" => $statusHistoryOrder ?? null,
                ]);
            }
        }

        $manager->flush();

        return $this->json([
            'success' => true,
        ]);
    }

    #[Route("/transport-failure", methods: [self::POST])]
    #[Wii\RestVersionChecked]
    public function transportFailure(Request                 $request,
                                     EntityManagerInterface  $manager,
                                     StatusHistoryService    $statusHistoryService,
                                     OperationHistoryService $historyService,
                                     AttachmentService       $attachmentService,
                                     SettingsService         $settingsService,
                                     NotificationService     $notificationService,
                                     TranslationService      $translation): Response
    {
        $data = $request->request;
        $files = $request->files;
        $request = $manager->find(TransportRequest::class, $data->get('transport'));
        $motive = $data->get('motive');
        $comment = $data->get('comment');
        $order = $request->getOrder();
        $user = $this->getUser();
        $now = new DateTime();

        $entityForStatusCheck = $request instanceof TransportCollectRequest && $request->getDelivery()
            ? $request->getDelivery()
            : $request;

        $isEdit = $entityForStatusCheck->getStatus()->getCode() !== TransportRequest::STATUS_ONGOING
            && $entityForStatusCheck->getStatus()->getCode() !== TransportRequest::STATUS_TO_DELIVER
            && $entityForStatusCheck->getStatus()->getCode() !== TransportRequest::STATUS_TO_COLLECT
            && $entityForStatusCheck->getStatus()->getCode() !== TransportRequest::STATUS_AWAITING_PLANNING;

        if ($comment && $order->getComment() != $comment) {
            $this->updateTransportComment($manager, $historyService, $request, $comment);
        }

        $lastFailedOrderHistory = $order->getLastTransportHistory(OperationHistoryService::TYPE_FAILED_DELIVERY, OperationHistoryService::TYPE_FAILED_COLLECT);
        if (!$lastFailedOrderHistory || ($lastFailedOrderHistory->getReason() !== $motive)) {
            $order->setReturnReason($motive);

            $requests = [$request];
            if($request instanceof TransportDeliveryRequest && $request->getCollect()) {
                $requests[] = $request->getCollect();
            }

            foreach($requests as $entity) {
                $historyType = $entity instanceof TransportDeliveryRequest ? OperationHistoryService::TYPE_FAILED_DELIVERY : OperationHistoryService::TYPE_FAILED_COLLECT;

                $entity = $entity instanceof TransportCollectRequest && $entity->getDelivery() ? $entity->getDelivery() : $entity;
                $historyService->persistTransportHistory($manager, $entity, $historyType, [
                    "user" => $user,
                    "reason" => $motive,
                ]);

                $historyService->persistTransportHistory($manager, $entity->getOrder(), $historyType, [
                    "user" => $user,
                    "reason" => $motive,
                ]);
            }
        }

        $signature = $files->get('signature');
        $photo = $files->get('photo');
        $files = [
            ...($signature ? [$signature] : []),
            ...($photo ? [$photo] : []),
        ];

        $attachments = $attachmentService->persistAttachments($manager, $files, ["attachmentContainer" => $order]);
        if (!empty($attachments)) {
            $this->updateTransportAttachment($manager, $historyService, $request, $attachments);
        }

        if (!$isEdit) {
            $order->setTreatedAt($now);

            // si ce n'est pas la collecte d'une livraison collecte
            // alors on met la ligne en failed
            if (!($request instanceof TransportCollectRequest && $request->getDelivery())) {
                $lastLine = $order->getTransportRoundLines()->last();

                if ($lastLine) {
                    $lastLine
                        ->setFulfilledAt($now)
                        ->setFailedAt($now);
                }
            }

            foreach ([$request, $order] as $entity) {
                if ($entity instanceof TransportOrder) {
                    [$categoryStatus, $statusCode] = $entity->getRequest() instanceof TransportCollectRequest
                        ? [CategorieStatut::TRANSPORT_ORDER_COLLECT, TransportOrder::STATUS_NOT_COLLECTED]
                        : [CategorieStatut::TRANSPORT_ORDER_DELIVERY, TransportOrder::STATUS_NOT_DELIVERED];
                } else {
                    [$categoryStatus, $statusCode] = $entity instanceof TransportCollectRequest
                        ? [CategorieStatut::TRANSPORT_REQUEST_COLLECT, TransportRequest::STATUS_NOT_COLLECTED]
                        : [CategorieStatut::TRANSPORT_REQUEST_DELIVERY, TransportRequest::STATUS_NOT_DELIVERED];
                }

                $status = $manager->getRepository(Statut::class)
                    ->findOneByCategorieNameAndStatutCode($categoryStatus, $statusCode);

                $collectHasDelivery = $request instanceof TransportCollectRequest && $request->getDelivery();

                if (!$collectHasDelivery) {
                    $statusHistoryService->updateStatus($manager, $entity, $status, [
                        "initiatedBy" => $user,
                    ]);
                } else {
                    //pas d'historique de statut car livraison collecte
                    $notCollectedStatus = $manager->getRepository(Statut::class)
                        ->findOneByCategorieNameAndStatutCode($categoryStatus, $statusCode);
                    $entity->setStatus($notCollectedStatus);
                }
            }

            // notification WEB si c'est une livraison ratée
            if ($request instanceof TransportDeliveryRequest) {
                $notificationTitle = 'Notification';
                $notificationContent = 'Une ' . mb_strtolower($translation->translate("Demande", "Livraison", "Demande de livraison", false)) . ' n\'a pas pu être livrée';
                $notificationImage = '/svg/cross-red.svg';

                $notificationService->send('notifications-web', $notificationTitle, $notificationContent, [
                    'title' => $notificationTitle,
                    'content' => $notificationContent,
                    'image' => $_SERVER['APP_URL'] . $notificationImage,
                ], true);

                $emitted = new Notification();
                $emitted
                    ->setContent($notificationContent)
                    ->setSource($request->getNumber())
                    ->setTriggered(new DateTime());

                $users = $manager->getRepository(Utilisateur::class)->findBy(['status' => true]);
                $manager->persist($emitted);
                foreach ($users as $user) {
                    $user->addUnreadNotification($emitted);
                }
            }

            // si la livraison n'a pas été possible et qu'on était dans le cas d'une
            // livraison collecte, alors on passe la collecte en non collecté aussi
            // car le livreur ne pourra pas la faire non plus
            if ($request instanceof TransportDeliveryRequest && $request->getCollect()) {
                $collect = $request->getCollect();
                $order = $collect->getOrder();

                foreach ([$collect, $order] as $entity) {
                    if ($entity instanceof TransportOrder) {
                        [$categoryStatus, $statusCode] = [
                            CategorieStatut::TRANSPORT_ORDER_COLLECT, TransportOrder::STATUS_NOT_COLLECTED,
                        ];
                    } else {
                        [$categoryStatus, $statusCode] = [
                            CategorieStatut::TRANSPORT_REQUEST_COLLECT, TransportRequest::STATUS_NOT_COLLECTED,
                        ];
                    }

                    //pas d'historique de statut car livraison collecte
                    $notCollectedStatus = $manager->getRepository(Statut::class)
                        ->findOneByCategorieNameAndStatutCode($categoryStatus, $statusCode);
                    $entity->setStatus($notCollectedStatus);
                }
            }

            // dans le cas d'une collecte qui n'est pas dans une livraison-collecte
            // en fonction du motif choisit, la collecte repassera dans le workflow
            if ($request instanceof TransportCollectRequest && !$request->getDelivery()) {
                $statusRepository = $manager->getRepository(Statut::class);

                $workflowEndMotives = $settingsService->getValue($manager, Setting::TRANSPORT_ROUND_COLLECT_WORKFLOW_ENDING_MOTIVE);
                $workflowEndMotives = explode(",", $workflowEndMotives);

                if (!in_array($motive, $workflowEndMotives)) {
                    $requestStatus = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_COLLECT,
                        TransportRequest::STATUS_AWAITING_PLANNING);
                    $orderStatus = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_COLLECT,
                        TransportOrder::STATUS_TO_CONTACT);

                    $statusHistoryService->updateStatus($manager, $request, $requestStatus, [
                        "initiatedBy" => $user,
                    ]);
                    $statusHistoryService->updateStatus($manager, $order, $orderStatus, [
                        "initiatedBy" => $user,
                    ]);

                    $request->setStatus($requestStatus)
                        ->setTimeSlot(null)
                        ->setValidatedDate(null);

                    $order->setStatus($orderStatus);
                }
            }

            // marque la livraison comme terminée si on est dans le cas d'une livraison collecte
            if($request instanceof TransportCollectRequest && $request->getDelivery() && $request->getDelivery()->getStatus()->getCode() === TransportRequest::STATUS_ONGOING) {
                $deliveryRequest = $request->getDelivery();
                $deliveryOrder = $deliveryRequest->getOrder();

                $requestFinished = $manager->getRepository(Statut::class)
                    ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_DELIVERY, TransportRequest::STATUS_FINISHED);

                $requestNotCollected = $manager->getRepository(Statut::class)
                    ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_COLLECT, TransportRequest::STATUS_NOT_COLLECTED);

                $orderFinished = $manager->getRepository(Statut::class)
                    ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_DELIVERY, TransportOrder::STATUS_FINISHED);

                $statusHistoryService->updateStatus($manager, $deliveryRequest, $requestFinished, [
                    "initiatedBy" => $user,
                ]);
                $statusHistoryService->updateStatus($manager, $deliveryOrder, $orderFinished, [
                    "initiatedBy" => $user,
                ]);
                $statusHistoryService->updateStatus($manager, $request, $requestNotCollected, [
                    "initiatedBy" => $user,
                ]);
            }
        }

        $manager->flush();

        return $this->json([
            "success" => true,
        ]);
    }

    #[Route("/deposit-transport-packs", methods: [self::POST])]
    #[Wii\RestVersionChecked]
    public function depositPacks(Request                 $request,
                                 EntityManagerInterface  $manager,
                                 OperationHistoryService $operationHistoryService,
                                 StatusHistoryService    $statusHistoryService,
                                 TrackingMovementService $trackingMovementService,
                                 PackService             $packService): Response
    {
        $data = $request->request;
        $packRepository = $manager->getRepository(Pack::class);
        $round = $manager->find(TransportRound::class, $data->get("round"));
        $depositedDeliveryPacks = json_decode($data->get("depositedDeliveryPacks"), true);
        $depositedCollectPacks = json_decode($data->get("depositedCollectPacks"), true);
        $location = $manager->find(Emplacement::class, $data->get("location"));

        $user = $this->getUser();

        if ($depositedDeliveryPacks) {
            $depositedTransports = [];
            $transportById = [];

            foreach ($depositedDeliveryPacks as $pack) {
                $pack = $packRepository->findOneBy(["code" => $pack["code"]]);

                $pack->getTransportDeliveryOrderPack()
                    ->setReturnedAt(new DateTime())
                    ->setState(TransportDeliveryOrderPack::RETURNED_STATE);

                $trackingMovement = $trackingMovementService->createTrackingMovement(
                    $pack,
                    $location,
                    $user,
                    new DateTime(),
                    true,
                    true,
                    TrackingMovement::TYPE_DEPOSE,
                    [
                        'quantity' => $pack->getQuantity()
                    ]
                );

                $transport = $pack->getTransportDeliveryOrderPack()->getOrder();
                $transportById[$transport->getId()] = $transport;
                $depositedTransports[$transport->getId()][] = $pack;

                $manager->persist($trackingMovement);
            }

            foreach ($depositedTransports as $transport => $packs) {
                $transport = $transportById[$transport];

                $operationHistoryService->persistTransportHistory(
                    $manager,
                    [$transport, $transport->getRequest()],
                    OperationHistoryService::TYPE_PACKS_FAILED,
                    [
                        "user" => $user,
                        "message" => Stream::from($packs)
                            ->map(fn(Pack $pack) => $pack->getCode())
                            ->join(", "),
                        "location" => $location,
                    ]
                );
            }

            $isDoneReturning = Stream::from($round->getTransportRoundLines())
                ->filter(fn(TransportRoundLine $line) => in_array($line->getOrder()->getStatus()->getCode(),
                    [TransportOrder::STATUS_CANCELLED, TransportOrder::STATUS_NOT_DELIVERED]))
                ->flatMap(fn(TransportRoundLine $line) => $line->getOrder()->getPacks())
                ->filter(fn(TransportDeliveryOrderPack $pack) => $pack->getState() !== TransportDeliveryOrderPack::RETURNED_STATE)
                ->isEmpty();

            if ($isDoneReturning) {
                $round->setNoDeliveryToReturn(true);
            }
        } else if ($depositedCollectPacks) {
            $statusRepository = $manager->getRepository(Statut::class);

            foreach ($depositedCollectPacks as $pack) {
                /** @var Nature $nature */
                $nature = $manager->find(Nature::class, $pack["nature_id"]);

                for ($i = 0; $i < $pack["quantity"]; $i++) {
                    $natureLabel = $this->getFormatter()->nature($nature);
                    $createdPack = $packService->createPackWithCode(TransportRound::NUMBER_PREFIX . "{$round->getNumber()}-$natureLabel-$i");

                    $trackingMovement = $trackingMovementService->createTrackingMovement(
                        $createdPack,
                        $location,
                        $user,
                        new DateTime(),
                        true,
                        true,
                        TrackingMovement::TYPE_DEPOSE,
                        [
                            'quantity' => $createdPack->getQuantity()
                        ]
                    );

                    $manager->persist($createdPack);
                    $manager->persist($trackingMovement);
                }

                if ($pack["quantity"]) {
                    foreach ($round->getTransportRoundLines() as $transport) {
                        $request = $transport->getOrder()->getRequest();
                        if ($request instanceof TransportDeliveryRequest && $request->getCollect()) {
                            $request = $request->getCollect();
                        }

                        if (!($request instanceof TransportCollectRequest)) {
                            continue;
                        }

                        foreach ($request->getLines() as $line) {
                            if (!$line->getCollectedQuantity() || $line->getDepositedQuantity() === $line->getCollectedQuantity()) {
                                continue;
                            }

                            if ($pack["quantity"] > $line->getCollectedQuantity()) {
                                $line->setDepositedQuantity($line->getCollectedQuantity());
                                $pack["quantity"] -= $line->getCollectedQuantity();
                            } else {
                                $line->setDepositedQuantity($pack["quantity"]);
                                $pack["quantity"] = 0;
                            }

                            if ($pack["quantity"] == 0) {
                                break 2;
                            }
                        }
                    }
                }
            }

            $round->setNoCollectToReturn(true);

            foreach ($round->getTransportRoundLines() as $transport) {
                $order = $transport->getOrder();
                $request = $order->getRequest();

                $isCollect = $request instanceof TransportCollectRequest;
                $isDeliveryCollect = $request instanceof TransportDeliveryRequest && $request->getCollect();
                if (($isCollect || $isDeliveryCollect)
                    && $request->getStatus()
                        ->getCode() === TransportRequest::STATUS_FINISHED) {
                    $requestCategory = CategorieStatut::TRANSPORT_REQUEST_COLLECT;
                    $orderCategory = CategorieStatut::TRANSPORT_ORDER_COLLECT;

                    $requestStatus = $statusRepository->findOneByCategorieNameAndStatutCode($requestCategory,
                        TransportRequest::STATUS_DEPOSITED);
                    $orderStatus = $statusRepository->findOneByCategorieNameAndStatutCode($orderCategory,
                        TransportOrder::STATUS_DEPOSITED);

                    $statusHistoryService->updateStatus($manager, $request, $requestStatus, [
                        "initiatedBy" => $user,
                    ]);
                    $statusHistoryService->updateStatus($manager, $order, $orderStatus, [
                        "initiatedBy" => $user,
                    ]);

                    $operationHistoryService->persistTransportHistory($manager,
                        $order->getRequest(),
                        OperationHistoryService::TYPE_PACKS_DEPOSITED,
                        [
                            "user" => $user,
                            "location" => $location,
                        ]);

                    $operationHistoryService->persistTransportHistory($manager,
                        $order,
                        OperationHistoryService::TYPE_PACKS_DEPOSITED,
                        [
                            "user" => $user,
                            "location" => $location,
                        ]);
                }
            }
        }

        $manager->flush();

        return $this->json([
            "success" => true,
        ]);
    }

    #[Route("/end-round-locations", methods: [self::GET])]
    #[Wii\RestVersionChecked]
    public function endRoundLocations(EntityManagerInterface $manager, SettingsService $settingsService): JsonResponse
    {
        $settingRepository = $manager->getRepository(Setting::class);
        $endRoundLocations = $settingsService->getValue($manager, Setting::TRANSPORT_ROUND_END_ROUND_LOCATIONS);

        $endRoundLocations = $endRoundLocations ? explode(",", $endRoundLocations) : [];
        $endRoundLocations = Stream::from($endRoundLocations)->map(fn(string $location) => (int)$location)->toArray();

        return $this->json([
            'endRoundLocations' => $endRoundLocations,
        ]);
    }

    #[Route("/packs-return-locations", methods: [self::GET])]
    #[Wii\RestVersionChecked]
    public function packsReturnLocations(EntityManagerInterface $manager, SettingsService $settingsService): JsonResponse
    {
        $undeliveredPacksLocations = $settingsService->getValue($manager, Setting::TRANSPORT_ROUND_REJECTED_PACKS_LOCATIONS);
        $collectedPacksLocations = $settingsService->getValue($manager, Setting::TRANSPORT_ROUND_COLLECTED_PACKS_LOCATIONS);

        $undeliveredPacksLocations = $undeliveredPacksLocations ? explode(",", $undeliveredPacksLocations) : [];
        $undeliveredPacksLocations = Stream::from($undeliveredPacksLocations)
            ->map(fn(string $location) => (int)$location)
            ->toArray();

        $collectedPacksLocations = $collectedPacksLocations ? explode(",", $collectedPacksLocations) : [];
        $collectedPacksLocations = Stream::from($collectedPacksLocations)
            ->map(fn(string $location) => (int)$location)
            ->toArray();

        return $this->json([
            'undeliveredPacksLocations' => $undeliveredPacksLocations,
            'collectedPacksLocations' => $collectedPacksLocations,
        ]);
    }

    public function updateTransportComment(EntityManagerInterface                           $manager,
                                           OperationHistoryService                          $historyService,
                                           TransportDeliveryRequest|TransportCollectRequest $request,
                                           ?string                                          $comment): void
    {
        $order = $request->getOrder();

        $order->setComment($comment);

        $historyService->persistTransportHistory($manager, $request, OperationHistoryService::TYPE_ADD_COMMENT, [
            "user" => $this->getUser(),
            "comment" => $comment,
        ]);

        if ($request instanceof TransportCollectRequest && $request->getDelivery()) {
            $historyService->persistTransportHistory($manager,
                $request->getDelivery(),
                OperationHistoryService::TYPE_ADD_COMMENT,
                [
                    "user" => $this->getUser(),
                    "comment" => $comment,
                ]);
        }

        $historyService->persistTransportHistory($manager,
            $request instanceof TransportCollectRequest
                ? ($request->getDelivery()?->getOrder() ?? $request->getOrder())
                : $request->getOrder() ,
            OperationHistoryService::TYPE_ADD_COMMENT, [
            "user" => $this->getUser(),
            "comment" => $comment,
        ]);
    }

    public function updateTransportAttachment(EntityManagerInterface                           $manager,
                                              OperationHistoryService                          $historyService,
                                              TransportDeliveryRequest|TransportCollectRequest $request,
                                              array                                            $attachments): void {
        $order =  $request instanceof TransportCollectRequest
            ? ($request->getDelivery()?->getOrder() ?? $request->getOrder())
            : $request->getOrder();

        $user = $this->getUser();

        $historyService->persistTransportHistory($manager, $request, OperationHistoryService::TYPE_ADD_ATTACHMENT, [
            "user" => $user,
            "attachments" => $attachments,
        ]);

        if ($request instanceof TransportCollectRequest && $request->getDelivery()) {
            $historyService->persistTransportHistory($manager,
                $request->getDelivery(),
                OperationHistoryService::TYPE_ADD_ATTACHMENT,
                [
                    "user" => $user,
                    "attachments" => $attachments,
                ]);
        }

        $historyService->persistTransportHistory($manager, $order, OperationHistoryService::TYPE_ADD_ATTACHMENT, [
            "user" => $user,
            "attachments" => $attachments,
        ]);
    }

}
