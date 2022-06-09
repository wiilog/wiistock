<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Entity\Attachment;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\FreeField;
use App\Entity\Nature;
use App\Entity\Notification;
use App\Entity\Pack;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\TrackingMovement;
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
use App\Service\GeoService;
use App\Service\NotificationService;
use App\Service\PackService;
use App\Service\StatusHistoryService;
use App\Service\TrackingMovementService;
use App\Service\Transport\TransportHistoryService;
use App\Service\Transport\TransportRoundService;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use WiiCommon\Helper\Stream;
use function GuzzleHttp\Promise\inspect;

class TransportController extends AbstractFOSRestController {

    private Utilisateur|null $user;

    public function getUser(): ?Utilisateur {
        return $this->user;
    }

    public function setUser(Utilisateur $user) {
        $this->user = $user;
    }


    /**
     * @Rest\Get("/api/transport-rounds", name="api_transport_rounds", methods={"GET"}, condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function transportRounds(EntityManagerInterface $manager): Response {
        $transportRoundRepository = $manager->getRepository(TransportRound::class);
        $user = $this->getUser();

        $transportRounds = $transportRoundRepository->findMobileTransportRoundsByUser($user);
        $data = Stream::from($transportRounds)
            ->map(fn(TransportRound $round) => $this->serializeRound($manager, $round))
            ->toArray();

        return $this->json($data);
    }

    /**
     * @Rest\Get("/api/fetch-transport", name="api_fetch_transport", methods={"GET"}, condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function fetchSingleTransport(Request $request, EntityManagerInterface $manager): Response {
        $transportRequest = $manager->find(TransportRequest::class, $request->query->get("request"));

        return $this->json($this->serializeTransport($manager, $transportRequest));
    }

    /**
     * @Rest\Get("/api/fetch-round", name="api_fetch_round", methods={"GET"}, condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function fetchSingleRound(Request $request, EntityManagerInterface $manager): Response {
        $round = $manager->find(TransportRound::class, $request->query->get("round"));

        return $this->json($this->serializeRound($manager, $round));
    }

    private function serializeRound(EntityManagerInterface $manager, TransportRound $round) {
        $lines = $round->getTransportRoundLines();

        $totalLoaded = Stream::from($lines)
            ->flatMap(fn(TransportRoundLine $line) => $line->getOrder()->getPacks()->toArray())
            ->filter(fn(TransportDeliveryOrderPack $orderPack) => !$orderPack->getRejectReason())
            ->count();

        $loadedPacks = Stream::from($lines)
            ->flatMap(fn(TransportRoundLine $line) => $line->getOrder()->getPacks()->toArray())
            ->filter(fn(TransportDeliveryOrderPack $orderPack) => (
                $orderPack->getState() && !$orderPack->getRejectReason()
            ))
            ->count();

        // deliveries where packing is done
        $readyDeliveries = Stream::from($lines)
            ->filter(fn(TransportRoundLine $line) => (
                $line->getOrder()->getRequest() instanceof TransportDeliveryRequest
                && !$line->getOrder()->getPacks()->isEmpty()
            ))
            ->count();

        $collectedPacks = 0;
        $packsToCollect = 0;
        foreach ($lines as $line) {
            $request = $line->getOrder()->getRequest();
            if(!($request instanceof TransportCollectRequest)) {
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
            ->filter(fn(TransportRoundLine $line) => in_array($line->getOrder()->getStatus()->getCode(), [TransportOrder::STATUS_CANCELLED, TransportOrder::STATUS_NOT_DELIVERED]));

        $returned = Stream::from($notDeliveredOrders)
            ->flatMap(fn(TransportRoundLine $line) => $line->getOrder()->getPacks())
            ->filter(fn(TransportDeliveryOrderPack $pack) => $pack->getState() === TransportDeliveryOrderPack::RETURNED_STATE)
            ->count();

        $toReturn = Stream::from($notDeliveredOrders)
            ->flatMap(fn(TransportRoundLine $line) => $line->getOrder()->getPacks())
            ->count();

        $collectedOrders = Stream::from($lines)
            ->filter(fn(TransportRoundLine $line) =>
                ($line->getOrder()->getRequest() instanceof TransportCollectRequest
                || $line->getOrder()->getRequest()->getCollect()) &&
                $line->getOrder()->getStatus()->getCode() === TransportOrder::STATUS_FINISHED);

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
                ->filter(fn(TransportRoundLine $line) => $line->getOrder()->getRequest() instanceof TransportDeliveryRequest)
                ->count(),
            'loaded_packs' => $loadedPacks,
            'total_loaded' => $totalLoaded,
            'done_transports' => Stream::from($lines)
                ->filter(function(TransportRoundLine $line) {
                    $order = $line->getOrder();
                    $request = $order->getRequest();

                    return $line->getFulfilledAt() && $request->getStatus()->getCode() !== TransportRequest::STATUS_ONGOING;
                 })
                ->count(),
            'total_transports' => count($lines),
            'collected_packs' => $collectedPacks,
            'to_collect_packs' => $packsToCollect,
            "not_delivered" => $notDeliveredOrders->count(),
            "returned_packs" => $returned,
            "packs_to_return" => $toReturn,
            "done_collects" => $collectedOrders->count(),
            "deposited_packs" => $depositedPacks,
            "packs_to_deposit" => $toDeposit,
            "deposited_delivery_packs" => $round->getDepositedDeliveryPacks(),
            "deposited_collect_packs" => $round->getDepositedCollectPacks(),
            "lines" => Stream::from($lines)
                ->filter(fn(TransportRoundLine $line) =>
                    !$line->getCancelledAt()
                    || ($line->getTransportRound()->getBeganAt() && $line->getCancelledAt() > $line->getTransportRound()->getBeganAt()))
                ->sort(fn(TransportRoundLine $a, TransportRoundLine $b) => (($a->getFulfilledAt() !== null) <=> ($b->getFulfilledAt() !== null)))
                ->map(fn(TransportRoundLine $line) => $this->serializeTransport($manager, $line)),
            "to_finish" => Stream::from($lines)
                ->map(fn(TransportRoundLine $line) => $line->getFulfilledAt() || $line->getCancelledAt())
                ->every(),
        ];
    }

    private function serializeTransport(EntityManagerInterface $manager, TransportRoundLine|TransportRequest $request, TransportRoundLine $line = null): array {
        if($request instanceof TransportRoundLine) {
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
                $line->getNature()->getLabel(),
                $line->getTemperatureRange()?->getValue()
            ])->toArray();

        if($request instanceof TransportCollectRequest) {
            $naturesToCollect = $request->getLines()
                ->map(fn(TransportCollectRequestLine $line) => [
                    "nature_id" => $line->getNature()->getId(),
                    "nature" => $line->getNature()->getLabel(),
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
            'status' => $request->getStatus()->getNom(),
            'type' => FormatHelper::type($request->getType()),
            'type_icon' => $request->getType()?->getLogo() ? $_SERVER["APP_URL"] . $request->getType()->getLogo()->getFullPath() : null,
            'kind' => $isCollect ? 'collect' : 'delivery',
            'collect' => $collect ? array_merge($this->serializeTransport($manager, $collect, $line), [
                "from_delivery" => true,
            ]) : null,
            'natures_to_collect' => $naturesToCollect,
            'packs' => Stream::from($order->getPacks())
                ->map(function(TransportDeliveryOrderPack $orderPack) use ($temperatureRanges) {
                    $pack = $orderPack->getPack();
                    $nature = $pack->getNature();

                    return [
                        'code' => $pack->getCode(),
                        'nature' => FormatHelper::nature($nature),
                        'nature_id' => $nature->getId(),
                        'temperature_range' => $temperatureRanges[$nature->getLabel()],
                        'color' => $nature->getColor(),
                        'rejected' => $orderPack->getState() === TransportDeliveryOrderPack::REJECTED_STATE,
                        'loaded' => $orderPack->getState() === TransportDeliveryOrderPack::LOADED_STATE,
                        'delivered' => $orderPack->getState() === TransportDeliveryOrderPack::DELIVERED_STATE,
                        'returned' => $orderPack->getState() === TransportDeliveryOrderPack::RETURNED_STATE,
                    ];
                }),
            'expected_at' => $expectedAt,
            'estimated_time' => $line->getEstimatedAt()?->format('H:i'),
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
            'requester' => FormatHelper::user($request->getCreatedBy()),
            'free_fields' => Stream::from($freeFields)
                ->map(function(FreeField $freeField) use($line, $freeFieldsValues) {
                    return [
                        'id' => $freeField->getId(),
                        'label' => $freeField->getLabel(),
                        'value' => FormatHelper::freeField($freeFieldsValues[$freeField->getId()] ?? "", $freeField),
                    ];
                }),
            'priority' => $line->getPriority(),
            'cancelled' => !!$line->getCancelledAt(),
            'success' => $request->getStatus()->getCode() === TransportRequest::STATUS_FINISHED ||
                $request instanceof TransportDeliveryRequest && $request->getCollect() && $line->getFulfilledAt(),
            'failure' => in_array($request->getStatus()->getCode(), [
                TransportRequest::STATUS_NOT_DELIVERED,
                TransportRequest::STATUS_NOT_COLLECTED,
                TransportRequest::STATUS_CANCELLED,
            ]),
        ];
    }

    /**
     * @Rest\Get("/api/reject-motives", name="api_reject_motives", methods={"GET"}, condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function rejectMotives(EntityManagerInterface $manager): Response {
        $settingRepository = $manager->getRepository(Setting::class);
        $packRejectMotives = $settingRepository->getOneParamByLabel(Setting::TRANSPORT_ROUND_PACK_REJECT_MOTIVES);
        $deliveryRejectMotives = $settingRepository->getOneParamByLabel(Setting::TRANSPORT_ROUND_DELIVERY_REJECT_MOTIVES);
        $collectRejectMotives = $settingRepository->getOneParamByLabel(Setting::TRANSPORT_ROUND_COLLECT_REJECT_MOTIVES);

        $packRejectMotives = explode(",", $packRejectMotives);
        $deliveryRejectMotives = explode(",", $deliveryRejectMotives);
        $collectRejectMotives = explode(",", $collectRejectMotives);

        $deliveryRejectMotives[] = 'Autre';
        $collectRejectMotives[] = 'Autre';
        return $this->json([
            'pack' => $packRejectMotives,
            'delivery' => $deliveryRejectMotives,
            'collect' => $collectRejectMotives
        ]);
    }

    /**
     * @Rest\Post("/api/reject-pack", name="api_reject_pack", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function rejectPack(Request $request, EntityManagerInterface $manager, TransportHistoryService $historyService): Response {
        $data = $request->request;
        $pack = $manager->getRepository(Pack::class)->findOneBy(['code' => $data->get('pack')]);
        $rejectMotive = $data->get('rejectMotive');

        $transportDeliveryOrderPack = $pack->getTransportDeliveryOrderPack();
        [$order, $request] = [$transportDeliveryOrderPack->getOrder(), $transportDeliveryOrderPack->getOrder()->getRequest()];

        $transportDeliveryOrderPack
            ->setRejectedBy($this->getUser())
            ->setRejectReason($rejectMotive)
            ->setState(TransportDeliveryOrderPack::REJECTED_STATE);

        $historyService->persistTransportHistory($manager, [$order, $request], TransportHistoryService::TYPE_DROP_REJECTED_PACK, [
            'user' => $this->getUser(),
            'pack' => $pack,
            'reason' => $rejectMotive
        ]);

        $manager->flush();
        return $this->json([
            'success' => true,
        ]);
    }

    /**
     * @Rest\Post("/api/load-packs", name="api_load_packs", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function loadPacks(Request $request,
                              EntityManagerInterface $manager,
                              TrackingMovementService $trackingMovementService): Response {
        $data = $request->request;
        $packs = $manager->getRepository(Pack::class)->findBy(['code' => json_decode($data->get('packs'))]);
        $location = $manager->find(Emplacement::class, $data->get('location'));
        $now = new DateTime();
        $user = $this->getUser();

        foreach ($packs as $pack) {
            $orderPack = $pack->getTransportDeliveryOrderPack();
            $orderPack
                ->setState(TransportDeliveryOrderPack::LOADED_STATE);

            $trackingMovement = $trackingMovementService
                ->createTrackingMovement($pack, $location, $user, $now, true, true,TrackingMovement::TYPE_DEPOSE);
            $manager->persist($trackingMovement);
        }

        $manager->flush();
        return $this->json([
            'success' => true
        ]);
    }

    /**
     * @Rest\Post("/api/finish-round", name="api_finish_round", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function finishRound(Request                 $request,
                               EntityManagerInterface  $manager,
                               GeoService $geoService,
                               TransportRoundService   $transportRoundService): Response {
        $data = $request->request;
        $round = $manager->find(TransportRound::class, $data->get('round'));

        $round->setRealDistance($transportRoundService->calculateRoundRealDistance($round, $geoService));
        $manager->flush();

        return $this->json([
            "success" => true,
        ]);
    }

    /**
     * @Rest\Post("/api/start-round", name="api_start_round", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function startRound(Request                 $request,
                               EntityManagerInterface  $manager,
                               StatusHistoryService    $statusHistoryService,
                               TransportRoundService   $transportRoundService,
                               TransportHistoryService $historyService): Response {
        $data = $request->request;
        $round = $manager->find(TransportRound::class, $data->get('round'));

        $statusRepository = $manager->getRepository(Statut::class);
        $roundOngoing = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ROUND, TransportRound::STATUS_ONGOING);
        $deliveryRequestOngoing = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_DELIVERY, TransportRequest::STATUS_ONGOING);
        $collectRequestOngoing = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_COLLECT, TransportRequest::STATUS_ONGOING);
        $deliveryOrderOngoing = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_DELIVERY, TransportOrder::STATUS_ONGOING);
        $collectOrderOngoing = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_COLLECT, TransportOrder::STATUS_ONGOING);

        $round
            ->setStatus($roundOngoing)
            ->setBeganAt(new DateTime());

        $hasRejected = false;

        $statusHistoryRound = $statusHistoryService->updateStatus($manager, $round, $deliveryOrderOngoing);

        $historyService->persistTransportHistory($manager, $round, TransportHistoryService::TYPE_ONGOING, [
            "user" => $this->getUser(),
            "history" => $statusHistoryRound
        ]);

        foreach($round->getTransportRoundLines() as $line) {
            $order = $line->getOrder();
            $request = $order->getRequest();

            if($request instanceof TransportDeliveryRequest) {
                $hasPacks = !$order->getPacks()->isEmpty();
                $allRejected = $order->getPacks()
                    ->filter(fn(TransportDeliveryOrderPack $pack) => !$pack->getRejectedBy())
                    ->isEmpty();

                if ($hasPacks && !$allRejected) {
                    $requestStatus = $deliveryRequestOngoing;
                    $orderStatus = $deliveryOrderOngoing;
                }
                else if($allRejected) {
                    $hasRejected = true;
                    $transportRoundService->rejectTransportRoundDeliveryLine($manager, $line, $this->getUser());
                }
            } else {
                $requestStatus = $collectRequestOngoing;
                $orderStatus = $collectOrderOngoing;
            }

            if (isset($requestStatus) && isset($orderStatus) && !$line->getOrder()->getRejectedAt()) {
                $statusHistoryRequest = $statusHistoryService->updateStatus($manager, $request, $deliveryRequestOngoing);
                $statusHistoryOrder = $statusHistoryService->updateStatus($manager, $order, $deliveryOrderOngoing);

                $historyService->persistTransportHistory($manager, $request, TransportHistoryService::TYPE_ONGOING, [
                    "user" => $this->getUser(),
                    "history" => $statusHistoryRequest,
                ]);

                $historyService->persistTransportHistory($manager, $order, TransportHistoryService::TYPE_ONGOING, [
                    "user" => $this->getUser(),
                    "history" => $statusHistoryOrder,
                ]);
            }
        }


        if ($round->getTransportRoundLines()->isEmpty()) {
            if ($hasRejected) {
                return $this->json([
                    "success" => false,
                    "msg" => "La tournée ne peut pas être débutée car aucune livraison de la tournée n'est valide"
                ]);
            }
            else {
                return $this->json([
                    "success" => false,
                    "msg" => "La tournée ne contient aucune ligne et ne peut être débutée"
                ]);
            }
        }

        $transportRoundService->updateTransportRoundLinePriority($round);

        $manager->flush();

        return $this->json([
            "success" => true,
            "round" => $this->serializeRound($manager, $round)
        ]);
    }

    /**
     * @Rest\Get("/api/has-new-packs", name="api_has_new_packs", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function hasNewPacks(Request $request, EntityManagerInterface $manager): Response {
        $data = $request->query;
        $round = $manager->find(TransportRound::class, $data->get('round'));
        $currentPacks = json_decode($data->get('packs'));

        $lines = $round->getTransportRoundLines();
        $updatedPacks = Stream::from($lines)
            ->map(fn(TransportRoundLine $line) => $line->getOrder()->getPacks());

        $updatedPackCodes = Stream::from($updatedPacks)
            ->reduce(function(array $acc, Collection $packs) {
                $acc[] = Stream::from($packs)
                    ->map(fn(TransportDeliveryOrderPack $orderPack) => $orderPack->getPack()->getCode())
                    ->toArray();
                return $acc;
            }, []);

        $updatedPackCodes = Stream::from($updatedPackCodes)->flatten()->toArray();

        $newPacks = Stream::diff($currentPacks, $updatedPackCodes)
            ->toArray();

        return $this->json([
            'success' => true,
            'has_new_packs' => !empty($newPacks)
        ]);
    }

    /**
     * @Rest\Post("/api/finish-transport", name="api_finish-transport", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function finishTransport(Request $request,
                                    EntityManagerInterface $manager,
                                    TransportHistoryService $historyService,
                                    StatusHistoryService $statusHistoryService,
                                    TrackingMovementService $trackingMovementService,
                                    AttachmentService $attachmentService): Response {
        $data = $request->request;
        $files = $request->files;
        $request = $manager->find(TransportRequest::class, $data->get('id'));
        $order = $request->getOrder();
        $now = new DateTime('now');

        $isEdit = $request->getStatus()->getCode() !== TransportRequest::STATUS_ONGOING &&
            $request->getStatus()->getCode() !== TransportRequest::STATUS_TO_DELIVER &&
            $request->getStatus()->getCode() !== TransportRequest::STATUS_TO_COLLECT &&
            $request->getStatus()->getCode() !== TransportRequest::STATUS_AWAITING_PLANNING;

        $signature = $files->get('signature');
        $photo = $files->get('photo');

        $signatureAttachment = $signature ? $attachmentService->createAttachements([$signature])[0] : null;
        $photoAttachment = $photo ? $attachmentService->createAttachements([$photo])[0] : null;

        $locationRepository = $manager->getRepository(Emplacement::class);
        $patient = $locationRepository->findOneBy(["label" => "Patient"]);
        if(!$patient) {
            $patient = (new Emplacement())
                ->setLabel("Patient")
                ->setDescription("Colis livrés chez un patient")
                ->setIsActive(true);

            $manager->persist($patient);
        }

        $comment = $data->get('comment');

        if ($comment && $comment != $order->getComment()) {
            $order->setComment($comment);

            $historyService->persistTransportHistory($manager, $request, TransportHistoryService::TYPE_ADD_COMMENT, [
                "user" => $this->getUser(),
                "comment" => $comment,
            ]);

            $historyService->persistTransportHistory($manager, $order, TransportHistoryService::TYPE_ADD_COMMENT, [
                "user" => $this->getUser(),
                "comment" => $comment,
            ]);
        }

        if ($signatureAttachment || $photoAttachment) {
            if($signatureAttachment) {
                $order->setSignature($signatureAttachment);
            }

            if($photoAttachment) {
                $order->addAttachment($photoAttachment);
            }

            $historyService->persistTransportHistory($manager, $request, TransportHistoryService::TYPE_ADD_ATTACHMENT, [
                "user" => $this->getUser(),
                "attachments" => [
                    ...($signatureAttachment ? [$signatureAttachment] : []),
                    ...($photoAttachment ? [$photoAttachment] : []),
                ],
            ]);

            $historyService->persistTransportHistory($manager, $order, TransportHistoryService::TYPE_ADD_ATTACHMENT, [
                "user" => $this->getUser(),
                "attachments" => [
                    ...($signatureAttachment ? [$signatureAttachment] : []),
                    ...($photoAttachment ? [$photoAttachment] : []),
                ],
            ]);
        }

        if(!$isEdit) {
            $order->setTreatedAt($now);

            $isCollectFromDelivery = $request instanceof TransportCollectRequest && $request->getDelivery();
            if (!$isCollectFromDelivery) {
                $lastLine = $order->getTransportRoundLines()->last();
                $lastLine->setFulfilledAt($now);
            }

            if ($request instanceof TransportDeliveryRequest) {
                foreach ($order->getPacks() as $line) {
                    if (!$line->getRejectedBy()) {
                        $line->setState(TransportDeliveryOrderPack::DELIVERED_STATE);
                        $trackingMovement = $trackingMovementService
                            ->createTrackingMovement($line->getPack(),
                                $patient,
                                $this->getUser(),
                                $now,
                                true,
                                true,
                                TrackingMovement::TYPE_DEPOSE);
                        $manager->persist($trackingMovement);
                    }
                }
            }
            else {
                $collectedPacks = Stream::from(json_decode($data->get('collectedPacks'), true))
                    ->keymap(fn(array $nature) => [$nature["nature_id"], $nature["collected_quantity"]])
                    ->toArray();

                foreach ($order->getRequest()->getLines() as $line) {
                    if(isset($collectedPacks[$line->getNature()->getId()])) {
                        $line->setCollectedQuantity($collectedPacks[$line->getNature()->getId()]);
                    }
                }
            }

            if ($request instanceof TransportCollectRequest || $request->getCollect() === null) {
                $statusRepository = $manager->getRepository(Statut::class);

                //si on termine la collecte d'une livraison collecte, alors il faut
                //mettre a jour les données de la livraison car celles de la collecte
                //ne sont pas utilisées
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

                $order->getRequest()->setStatus($requestStatus);
                $order->setStatus($requestStatus);

                $statusHistoryRequest = $statusHistoryService->updateStatus($manager,
                    $order->getRequest(),
                    $requestStatus);
                $statusHistoryOrder = $statusHistoryService->updateStatus($manager, $order, $orderStatus);

                $historyService->persistTransportHistory($manager,
                    $order->getRequest(),
                    TransportHistoryService::TYPE_FINISHED,
                    [
                        "user" => $this->getUser(),
                        "history" => $statusHistoryRequest,
                    ]);

                $historyService->persistTransportHistory($manager, $order, TransportHistoryService::TYPE_FINISHED, [
                    "user" => $this->getUser(),
                    "history" => $statusHistoryOrder,
                ]);
            }
        }

        $manager->flush();

        return $this->json([
            'success' => true
        ]);
    }

    /**
     * @Rest\Post("/api/transport-failure", name="api_transport_failure", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function transportFailure(Request                 $request,
                                     EntityManagerInterface  $manager,
                                     StatusHistoryService    $statusHistoryService,
                                     TransportHistoryService $historyService,
                                     AttachmentService       $attachmentService,
                                     NotificationService     $notificationService): Response {
        $data = $request->request;
        $files = $request->files;
        $request = $manager->find(TransportRequest::class, $data->get('transport'));
        $round = $manager->find(TransportRound::class, $data->get('round'));
        $motive = $data->get('motive');
        $comment = $data->get('comment');
        $order = $request->getOrder();
        $now = new DateTime();

        $isEdit = $request->getStatus()->getCode() !== TransportRequest::STATUS_ONGOING &&
            $request->getStatus()->getCode() !== TransportRequest::STATUS_TO_DELIVER &&
            $request->getStatus()->getCode() !== TransportRequest::STATUS_TO_COLLECT &&
            $request->getStatus()->getCode() !== TransportRequest::STATUS_AWAITING_PLANNING;

        if ($comment && $order->getComment() != $comment) {
            $order->setComment($comment);

            $historyService->persistTransportHistory($manager, $request, TransportHistoryService::TYPE_ADD_COMMENT, [
                "user" => $this->getUser(),
                "comment" => $comment,
            ]);

            $historyService->persistTransportHistory($manager, $order, TransportHistoryService::TYPE_ADD_COMMENT, [
                "user" => $this->getUser(),
                "comment" => $comment,
            ]);
        }

        $signature = $files->get('signature');
        $photo = $files->get('photo');

        $signatureAttachment = $signature ? $attachmentService->createAttachements([$signature])[0] : null;
        $photoAttachment = $photo ? $attachmentService->createAttachements([$photo])[0] : null;

        if($signatureAttachment) {
            $order->setSignature($signatureAttachment);
        }

        if($signatureAttachment) {
            $order->addAttachment($photoAttachment);
        }

        if ($signatureAttachment || $photoAttachment) {
            $historyService->persistTransportHistory($manager, $request, TransportHistoryService::TYPE_ADD_ATTACHMENT, [
                "user" => $this->getUser(),
                "attachments" => [
                    ...($signatureAttachment ? [$signatureAttachment] : []),
                    ...($photoAttachment ? [$photoAttachment] : []),
                ],
            ]);

            $historyService->persistTransportHistory($manager, $order, TransportHistoryService::TYPE_ADD_ATTACHMENT, [
                "user" => $this->getUser(),
                "attachments" => [
                    ...($signatureAttachment ? [$signatureAttachment] : []),
                    ...($photoAttachment ? [$photoAttachment] : []),
                ],
            ]);
        }

        if(!$isEdit) {
            $order->setTreatedAt($now);

            $historyService->persistTransportHistory($manager, $request, TransportHistoryService::TYPE_ADD_COMMENT, [
                "user" => $this->getUser(),
                "comment" => $motive,
            ]);

            $historyService->persistTransportHistory($manager, $order, TransportHistoryService::TYPE_ADD_COMMENT, [
                "user" => $this->getUser(),
                "comment" => $motive,
            ]);

            $isCollectFromDelivery = $request instanceof TransportCollectRequest && $request->getDelivery();
            if (!$isCollectFromDelivery) {
                $lastLine = $order->getTransportRoundLines()->last();
                $lastLine->setFulfilledAt($now);
            }

            foreach ([$request, $order] as $entity) {
                if ($entity instanceof TransportOrder) {
                    [$categoryStatus, $statusCode] = $entity->getRequest() instanceof TransportCollectRequest
                        ? [CategorieStatut::TRANSPORT_ORDER_COLLECT, TransportOrder::STATUS_NOT_COLLECTED]
                        : [CategorieStatut::TRANSPORT_ORDER_DELIVERY, TransportOrder::STATUS_NOT_DELIVERED];
                }
                else {
                    [$categoryStatus, $statusCode] = $entity instanceof TransportCollectRequest
                        ? [CategorieStatut::TRANSPORT_REQUEST_COLLECT, TransportRequest::STATUS_NOT_COLLECTED]
                        : [CategorieStatut::TRANSPORT_REQUEST_DELIVERY, TransportRequest::STATUS_NOT_DELIVERED];
                }

                $status = $manager->getRepository(Statut::class)
                    ->findOneByCategorieNameAndStatutCode($categoryStatus, $statusCode);

                $collectHasDelivery = $request instanceof TransportCollectRequest && $request->getDelivery();

                $statusHistory = null;
                if (!$collectHasDelivery) {
                    $statusHistory = $statusHistoryService->updateStatus($manager, $entity, $status, [
                        "setStatus" => true,
                    ]);
                }
                else {
                    //pas d'historique de statut car livraison collecte
                    $notCollectedStatus = $manager->getRepository(Statut::class)
                        ->findOneByCategorieNameAndStatutCode($categoryStatus, $statusCode);
                    $entity->setStatus($notCollectedStatus);
                }

                $historyService->persistTransportHistory($manager, $entity, TransportHistoryService::TYPE_FAILED, [
                    'user' => $this->getUser(),
                    'deliverer' => $round->getDeliverer(),
                    'round' => $round,
                    'history' => $statusHistory,
                    'reason' => $motive,
                    'comment' => $comment,
                    'date' => $now,
                    'attachments' => $photoAttachment ? [$photoAttachment] : null
                ]);
            }

            if ($request instanceof TransportDeliveryRequest) {
                $notificationTitle = 'Notification';
                $notificationContent = 'Une demande de livraison n\'a pas pu être livrée';
                $notificationImage = '/svg/cross-red.svg';

                $notificationService->send('notifications-web', $notificationTitle, $notificationContent, [
                    'title' => $notificationTitle,
                    'content' => $notificationContent,
                    'image' => $_SERVER['APP_URL'] . $notificationImage
                ], $_SERVER['APP_URL'] . $notificationImage, true);

                $emitted = new Notification();
                $emitted
                    ->setContent($notificationContent)
                    ->setSource($request->getNumber())
                    ->setTriggered(new DateTime());

                $users = $manager->getRepository(Utilisateur::class)->findBy(['status' => true]);
                $manager->persist($emitted);
                foreach ($users as $user) {
                    $user
                        ->addUnreadNotification($emitted);
                }
            }

            if ($request instanceof TransportDeliveryRequest && $request->getCollect()) {
                $collect = $request->getCollect();
                $order = $collect->getOrder();

                foreach ([$collect, $order] as $entity) {
                    if ($entity instanceof TransportOrder) {
                        [$categoryStatus, $statusCode] = [
                            CategorieStatut::TRANSPORT_ORDER_COLLECT, TransportOrder::STATUS_NOT_COLLECTED
                        ];
                    }
                    else {
                        [$categoryStatus, $statusCode] = [
                            CategorieStatut::TRANSPORT_REQUEST_COLLECT, TransportRequest::STATUS_NOT_COLLECTED
                        ];
                    }

                    //pas d'historique de statut car livraison collecte
                    $notCollectedStatus = $manager->getRepository(Statut::class)
                        ->findOneByCategorieNameAndStatutCode($categoryStatus, $statusCode);
                    $entity->setStatus($notCollectedStatus);
                }
            }

            if($request instanceof TransportCollectRequest && !$request->getDelivery()) {
                $settingsRepository = $manager->getRepository(Setting::class);
                $statusRepository = $manager->getRepository(Statut::class);

                $workflowEndMotives = $settingsRepository->getOneParamByLabel(Setting::TRANSPORT_ROUND_COLLECT_WORKFLOW_ENDING_MOTIVE);
                $workflowEndMotives = explode(",", $workflowEndMotives);

                if(!in_array($motive, $workflowEndMotives)) {
                    $requestStatus = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_COLLECT, TransportRequest::STATUS_AWAITING_PLANNING);
                    $orderStatus = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_COLLECT, TransportOrder::STATUS_TO_CONTACT);

                    $request->setStatus($requestStatus)
                        ->setTimeSlot(null);

                    $order->setStatus($orderStatus);
                }
            }
        }

        $manager->flush();

        return $this->json([
            "success" => true,
        ]);
    }

    /**
     * @Rest\Post("/api/deposit-transport-packs", name="api_deposit_transport_packs", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function depositPacks(Request $request, EntityManagerInterface $manager,
                                 TransportHistoryService $transportHistoryService,
                                 TrackingMovementService $trackingMovementService, PackService $packService): Response {
        $data = $request->request;
        $packRepository = $manager->getRepository(Pack::class);
        $round = $manager->find(TransportRound::class, $data->get("round"));
        $depositedDeliveryPacks = json_decode($data->get("depositedDeliveryPacks"), true);
        $depositedCollectPacks = json_decode($data->get("depositedCollectPacks"), true);
        $location = $manager->find(Emplacement::class, $data->get("location"));

        if($depositedDeliveryPacks) {
            $depositedTransports = [];
            $transportById = [];

            foreach($depositedDeliveryPacks as $pack) {
                $pack = $packRepository->findOneBy(["code" => $pack["code"]]);

                $pack->getTransportDeliveryOrderPack()->setState(TransportDeliveryOrderPack::RETURNED_STATE);

                $trackingMovement = $trackingMovementService->createTrackingMovement(
                    $pack,
                    $location,
                    $this->getUser(),
                    new DateTime(),
                    true,
                    true,
                    TrackingMovement::TYPE_DEPOSE
                );

                $transport = $pack->getTransportDeliveryOrderPack()->getOrder();
                $transportById[$transport->getId()] = $transport;
                $depositedTransports[$transport->getId()][] = $pack;

                $manager->persist($trackingMovement);
            }

            foreach($depositedTransports as $transport => $packs) {
                $transport = $transportById[$transport];

                $transportHistoryService->persistTransportHistory(
                    $manager,
                    [$transport, $transport->getRequest()],
                    TransportHistoryService::TYPE_PACKS_FAILED,
                    [
                        "user" => $this->getUser(),
                        "message" => Stream::from($packs)
                            ->map(fn(Pack $pack) => $pack->getCode())
                            ->join(", "),
                        "location" => $location,
                    ]
                );
            }

            $isDoneReturning = Stream::from($round->getTransportRoundLines())
                ->filter(fn(TransportRoundLine $line) => in_array($line->getOrder()->getStatus()->getCode(), [TransportOrder::STATUS_CANCELLED, TransportOrder::STATUS_NOT_DELIVERED]))
                ->flatMap(fn(TransportRoundLine $line) => $line->getOrder()->getPacks())
                ->filter(fn(TransportDeliveryOrderPack $pack) => $pack->getState() !== TransportDeliveryOrderPack::RETURNED_STATE)
                ->isEmpty();

            if($isDoneReturning) {
                $round->setDepositedDeliveryPacks(true);
            }
        } else if($depositedCollectPacks) {
            foreach ($depositedCollectPacks as $pack) {
                /** @var Nature $nature */
                $nature = $manager->find(Nature::class, $pack["nature_id"]);

                for ($i = 0; $i < $pack["quantity"]; $i++) {
                    $createdPack = $packService->createPackWithCode(TransportRound::NUMBER_PREFIX . "{$round->getNumber()}-{$nature->getLabel()}-$i");

                    $trackingMovement = $trackingMovementService->createTrackingMovement(
                        $createdPack,
                        $location,
                        $this->getUser(),
                        new DateTime(),
                        true,
                        true,
                        TrackingMovement::TYPE_DEPOSE
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
                            if (!$line->getCollectedQuantity()) {
                                continue;
                            }

                            if ($pack["quantity"] > $line->getCollectedQuantity()) {
                                $line->setDepositedQuantity($line->getCollectedQuantity());
                                $pack["quantity"] -= $line->getCollectedQuantity();
                            }
                            else {
                                $line->setDepositedQuantity($pack["quantity"]);
                                $pack["quantity"] = 0;
                            }

                            if ($pack["quantity"] == 0) {
                                break 2;
                            }
                        }
                    }
                }

                $requestsAndOrders = Stream::from($round->getTransportRoundLines())
                    ->filter(fn(TransportRoundLine $line) => $line->getOrder()->getRequest() instanceof TransportCollectRequest || $line->getOrder()->getRequest()->getCollect())
                    ->flatMap(fn(TransportRoundLine $line) => [$line->getOrder(), $line->getOrder()->getRequest()])
                    ->toArray();

                $transportHistoryService->persistTransportHistory(
                    $manager,
                    $requestsAndOrders,
                    TransportHistoryService::TYPE_PACKS_DEPOSITED,
                    [
                        "user" => $this->getUser(),
                        "location" => $location,
                    ]
                );

                $round->setDepositedCollectPacks(true);
            }
        }

        $manager->flush();

        return $this->json([
            "success" => true,
        ]);
    }

}
