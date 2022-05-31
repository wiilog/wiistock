<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Entity\Attachment;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\FreeField;
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
use App\Entity\Transport\TransportRound;
use App\Entity\Transport\TransportRoundLine;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use App\Service\AttachmentService;
use App\Service\StatusHistoryService;
use App\Service\TrackingMovementService;
use App\Service\Transport\TransportHistoryService;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use WiiCommon\Helper\Stream;

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

        /** @var TransportRoundLine $line */
        $totalLoaded = 0;
        foreach ($lines as $line) {
            $totalLoaded += Stream::from($line->getOrder()->getPacks())
                ->filter(fn(TransportDeliveryOrderPack $orderPack) => !$orderPack->getRejectReason())
                ->count();
        }

        $loadedPacks = 0;
        foreach ($lines as $line) {
            $loadedPacks += Stream::from($line->getOrder()->getPacks())
                ->filter(fn(TransportDeliveryOrderPack $orderPack) => $orderPack->getState() === TransportDeliveryOrderPack::LOADED_STATE && !$orderPack->getRejectReason())
                ->count();
        }

        $readyDeliveries = 0;
        foreach ($lines as $line) {
            if($line->getOrder()->getRequest() instanceof TransportDeliveryRequest) {
                $isReady = Stream::from($line->getOrder()->getPacks())
                    ->filter(fn(TransportDeliveryOrderPack $orderPack) => $orderPack->getState() === null)
                    ->isEmpty();

                if ($isReady) {
                    $readyDeliveries += 1;
                }
            }
        }

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
                $line->getOrder()->getRequest() instanceof TransportCollectRequest &&
                $line->getOrder()->getStatus()->getCode() === TransportOrder::STATUS_FINISHED);

        $depositedPacks = Stream::from($collectedOrders)
            ->flatMap(fn(TransportRoundLine $line) => $line->getOrder()->getPacks())
            ->filter(fn(TransportDeliveryOrderPack $pack) => $pack->getState() === TransportDeliveryOrderPack::RETURNED_STATE)
            ->count();

        $toDeposit = Stream::from($collectedOrders)
            ->flatMap(fn(TransportRoundLine $line) => $line->getOrder()->getPacks())
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
                ->filter(fn(TransportRoundLine $line) => $line->getOrder()->getRequest() instanceof TransportDeliveryRequest)
                ->count(),
            'loaded_packs' => $loadedPacks,
            'total_loaded' => $totalLoaded,
            'done_transports' => Stream::from($lines)
                ->filter(fn(TransportRoundLine $line) => $line->getFulfilledAt())
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
            'lines' => Stream::from($lines)
                ->filter(fn(TransportRoundLine $line) =>
                    !$line->getCancelledAt()
                    || $line->getCancelledAt() > $line->getTransportRound()->getBeganAt())
                ->map(fn(TransportRoundLine $line) => $this->serializeTransport($manager, $line)),
            "to_finish" => Stream::from($lines)
                ->map(fn(TransportRoundLine $line) => $line->getFulfilledAt() || $line->getCancelledAt() || $line->getRejectedAt())
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
                ])
                ->toArray();
        } else {
            $naturesToCollect = null;
        }

        return [
            'id' => $request->getId(),
            'number' => $request->getNumber(),
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
            'expected_at' => $isCollect
                ? $request->getTimeSlot()?->getName()
                : FormatHelper::datetime($request->getExpectedAt()),
            'estimated_time' => $line->getEstimatedAt()?->format('H:i'),
            'expected_time' => $request->getExpectedAt()?->format('H:i'),
            'time_slot' => $isCollect ? $request->getTimeSlot()?->getName() : null,
            'contact' => [
                'file_number' => $contact->getFileNumber(),
                'name' => $contact->getName(),
                'address' => str_replace("\n", "<br>", $contact->getAddress()),
                'contact' => $contact->getContact(),
                'person_to_contact' => $contact->getPersonToContact(),
                'observation' => $contact->getObservation(),
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
            'success' => $request->getStatus()->getCode() === TransportRequest::STATUS_FINISHED,
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

        return $this->json([
            'pack' => explode(",", $packRejectMotives),
            'delivery' => explode(",", $deliveryRejectMotives),
            'collect' => explode(",", $collectRejectMotives)
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
        $round = $manager->find(TransportRound::class, $data->get('round'));
        $now = new DateTime();
        $user = $this->getUser();

        $manager->flush();
        return $this->json([
            'success' => true
        ]);
    }

    /**
     * @Rest\Post("/api/start-round", name="api_start_round", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function startRound(Request $request, EntityManagerInterface $manager): Response {
        $data = $request->request;
        $round = $manager->find(TransportRound::class, $data->get('round'));

        $statusRepository = $manager->getRepository(Statut::class);
        $roundOngoing = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ROUND, TransportRound::STATUS_ONGOING);
        $deliveryRequestOngoing = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_DELIVERY, TransportRequest::STATUS_ONGOING);
        $collectRequestOngoing = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_COLLECT, TransportRequest::STATUS_ONGOING);
        $deliveryOrderOngoing = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_DELIVERY, TransportOrder::STATUS_ONGOING);
        $collectOrderOngoing = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_COLLECT, TransportOrder::STATUS_ONGOING);

        $round->setStatus($roundOngoing)
            ->setBeganAt(new DateTime());

        foreach($round->getTransportRoundLines() as $line) {
            $order = $line->getOrder();
            $request = $order->getRequest();

            if($request instanceof TransportDeliveryRequest) {
                $request->setStatus($deliveryRequestOngoing);
                $order->setStatus($deliveryOrderOngoing);
            } else {
                $request->setStatus($collectRequestOngoing);
                $order->setStatus($collectOrderOngoing);
            }
        }

        $manager->flush();

        return $this->json([
            "success" => true
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

        if($order->getRequest() instanceof TransportDeliveryRequest) {
            foreach($order->getPacks() as $line) {
                if(!$line->getRejectedBy()) {
                    $line->setState(TransportDeliveryOrderPack::DELIVERED_STATE);
                    $trackingMovement = $trackingMovementService
                        ->createTrackingMovement($line->getPack(), $patient, $this->getUser(), $now, true, true,TrackingMovement::TYPE_DEPOSE);
                    $manager->persist($trackingMovement);
                }
            }
        } else {
            $collectedPacks = Stream::from(json_decode($data->get('collectedPacks'), true))
                ->keymap(fn(array $nature) => [$nature["nature_id"], $nature["collected_quantity"]])
                ->toArray();

            foreach($order->getRequest()->getLines() as $line) {
                $line->setCollectedQuantity($collectedPacks[$line->getNature()->getId()]);
            }
        }

        $order
            ->setComment($data->get('comment'))
            ->setTreatedAt($now)
            ->getTransportRoundLines()->last()
                ->setFulfilledAt($now);

        if($signatureAttachment) {
            $order->setSignature($signatureAttachment);
        }

        if($signatureAttachment) {
            $order->addAttachment($photoAttachment);
        }

        $requestCategory = $request instanceof TransportCollectRequest
            ? CategorieStatut::TRANSPORT_REQUEST_COLLECT
            : CategorieStatut::TRANSPORT_REQUEST_DELIVERY;

        $orderCategory = $request instanceof TransportCollectRequest
            ? CategorieStatut::TRANSPORT_ORDER_COLLECT
            : CategorieStatut::TRANSPORT_ORDER_DELIVERY;

        $statusRepository = $manager->getRepository(Statut::class);
        $requestStatus = $statusRepository->findOneByCategorieNameAndStatutCode($requestCategory, TransportRequest::STATUS_FINISHED);
        $orderStatus = $statusRepository->findOneByCategorieNameAndStatutCode($orderCategory, TransportOrder::STATUS_FINISHED);

        $order->getRequest()->setStatus($requestStatus);
        $order->setStatus($requestStatus);

        $statusHistoryRequest = $statusHistoryService->updateStatus($manager, $order->getRequest(), $requestStatus);
        $statusHistoryOrder = $statusHistoryService->updateStatus($manager, $order, $orderStatus);

        $historyService->persistTransportHistory($manager, $order->getRequest(), TransportHistoryService::TYPE_FINISHED, [
            "user" => $this->getUser(),
            "history" => $statusHistoryRequest,
            "attachments" => [
                ...($signatureAttachment ? [$signatureAttachment] : []),
                ...($photoAttachment ? [$photoAttachment] : []),
            ],
        ]);

        $historyService->persistTransportHistory($manager, $order, TransportHistoryService::TYPE_FINISHED, [
            "user" => $this->getUser(),
            "history" => $statusHistoryOrder,
            "attachments" => [
                ...($signatureAttachment ? [$signatureAttachment] : []),
                ...($photoAttachment ? [$photoAttachment] : []),
            ],
        ]);

        $manager->flush();

        return $this->json([
            "success" => true,
            "data" => $this->serializeTransport($manager, $order->getRequest()),
        ]);
    }

}