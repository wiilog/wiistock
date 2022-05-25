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
        $freeFieldRepository = $manager->getRepository(FreeField::class);
        $user = $this->getUser();

        $transportRounds = $transportRoundRepository->findMobileTransportRoundsByUser($user);
        $data = Stream::from($transportRounds)
            ->filter(fn(TransportRound $round) => Stream::from($round->getTransportRoundLines())
                ->every(fn(TransportRoundLine $line) => !$line->getOrder()->isRejected()))
            ->map(function(TransportRound $round) use ($freeFieldRepository) {
                $lines = Stream::from($round->getTransportRoundLines())
                    ->filter(fn(TransportRoundLine $line) => !$line->getOrder()->isRejected());

                $totalLoaded = Stream::from($lines)
                    ->reduce(fn(int $acc, TransportRoundLine $line) => $acc + Stream::from($line->getOrder()->getPacks())
                            ->filter(fn(TransportDeliveryOrderPack $orderPack) => !$orderPack->getRejectReason())
                            ->count());

                $loadedPacks = Stream::from($lines)
                    ->reduce(fn(int $acc, TransportRoundLine $line) => $acc + Stream::from($line->getOrder()->getPacks())
                            ->filter(fn(TransportDeliveryOrderPack $orderPack) =>
                                $orderPack->getState() === TransportDeliveryOrderPack::LOADED_STATE && !$orderPack->getRejectReason())
                            ->count());

                $readyDeliveries = Stream::from($lines)
                    ->reduce(function(int $acc, TransportRoundLine $line) {
                        $isReady = Stream::from($line->getOrder()->getPacks())
                            ->filter(fn(TransportDeliveryOrderPack $orderPack) => $orderPack->getState() === null)
                            ->isEmpty();

                        return $isReady ? $acc + 1 : $acc;
                    });

                return [
                    'id' => $round->getId(),
                    'number' => $round->getNumber(),
                    'status' => FormatHelper::status($round->getStatus()),
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
                    'lines' => Stream::from($lines)
                        ->filter(fn(TransportRoundLine $line) =>
                            !$line->getCancelledAt()
                            || $line->getCancelledAt() > $line->getTransportRound()->getBeganAt())
                        ->map(function(TransportRoundLine $line) use ($freeFieldRepository) {
                            $order = $line->getOrder();
                            $request = $order->getRequest();
                            $collect = $request instanceof TransportDeliveryRequest ? $request->getCollect() : null;
                            $contact = $request->getContact();
                            $isCollect = $request instanceof TransportCollectRequest;
                            $categoryFF = $isCollect
                                ? CategorieCL::COLLECT_TRANSPORT
                                : CategorieCL::DELIVERY_TRANSPORT;
                            $freeFields = $freeFieldRepository->findByTypeAndCategorieCLLabel($request->getType(),
                                                                                              $categoryFF);
                            $freeFieldsValues = $request->getFreeFields();
                            $temperatureRanges = Stream::from($request->getLines())
                                ->filter(fn($line) => $line instanceof TransportDeliveryRequestLine)
                                ->keymap(fn(TransportDeliveryRequestLine $line) => [
                                    $line->getNature()->getLabel(),
                                    $line->getTemperatureRange()?->getValue()
                                ])->toArray();

                            return [
                                'id' => $line->getOrder()->getRequest()->getId(),
                                'number' => $request->getNumber(),
                                'type' => FormatHelper::type($request->getType()),
                                'type_icon' => $request->getType()?->getLogo() ? $_SERVER["APP_URL"] . $request->getType()->getLogo()->getFullPath() : null,
                                'kind' => $isCollect ? 'collect' : 'delivery',
                                'collect' => $collect ? [
                                    'type' => $collect->getType()->getLabel(),
                                    'type_icon' => $collect->getType()?->getLogo() ? $_SERVER["APP_URL"] . $collect->getType()->getLogo()->getFullPath() : null,
                                    'time_slot' => $collect->getTimeSlot()?->getName(),
                                    'success' => $collect->getStatus()->getCode() === TransportRequest::STATUS_FINISHED,
                                    'failure' => in_array($collect->getStatus()->getCode(), [
                                        TransportRequest::STATUS_NOT_DELIVERED,
                                        TransportRequest::STATUS_NOT_COLLECTED,
                                        TransportRequest::STATUS_CANCELLED,
                                    ]),
                                ] : null,
                                'packs' => Stream::from($line->getOrder()->getPacks())
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
                                            'loaded' => $orderPack->getState() === TransportDeliveryOrderPack::LOADED_STATE
                                        ];
                                    }),
                                'expected_at' => $isCollect
                                    ? $request->getTimeSlot()->getName()
                                    : FormatHelper::datetime($request->getExpectedAt()),
                                'estimated_time' => $line->getEstimatedAt()?->format('H:i'),
                                'expected_time' => $request->getExpectedAt()?->format('H:i'),
                                'time_slot' => $isCollect ? $request->getTimeSlot()->getName() : null,
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
                                ]),
                            ];
                        }),
                ];
            })->values();

        return $this->json($data);
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
    public function rejectPack(Request $request,
                               EntityManagerInterface $manager,
                               TransportHistoryService $historyService,
                               StatusHistoryService $statusHistoryService): Response {
        $data = $request->request;
        $pack = $manager->getRepository(Pack::class)->findOneBy(['code' => $data->get('pack')]);
        $round = $manager->find(TransportRound::class, $data->get('round'));
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

        if($order->isRejected()) {
            $statusRepository = $manager->getRepository(Statut::class);
            $toPrepareStatus = $statusRepository
                ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_DELIVERY, TransportRequest::STATUS_TO_PREPARE);

            $toAssignStatus = $statusRepository
                ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_DELIVERY, TransportOrder::STATUS_TO_ASSIGN);

            $statusHistoryRequest = $statusHistoryService->updateStatus($manager, $request, $toPrepareStatus);
            $historyService->persistTransportHistory($manager, $request, TransportHistoryService::TYPE_REJECTED_DELIVERY, [
                'user' => $this->getUser(),
                'history' => $statusHistoryRequest,
                'round' => $round
            ]);

            $statusHistoryOrder = $statusHistoryService->updateStatus($manager, $order, $toAssignStatus);
            $historyService->persistTransportHistory($manager, $order, TransportHistoryService::TYPE_REJECTED_DELIVERY, [
                'user' => $this->getUser(),
                'history' => $statusHistoryOrder,
                'round' => $round
            ]);

            $manager->flush();
        }

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

        foreach ($packs as $pack) {
            $orderPack = $pack->getTransportDeliveryOrderPack();
            $orderPack
                ->setState(TransportDeliveryOrderPack::LOADED_STATE);

            $trackingMovement = $trackingMovementService
                ->createTrackingMovement($pack, $location, $user, $now, true, true,TrackingMovement::TYPE_DEPOSE);
            $manager->persist($trackingMovement);
        }

        if($round->getStatus()->getCode() !== TransportRound::STATUS_ONGOING) {
            $onGoingStatus = $manager->getRepository(Statut::class)
                ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ROUND, TransportRound::STATUS_ONGOING);
            $round->setStatus($onGoingStatus);
        }

        $manager->flush();
        return $this->json([
            'success' => true
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
                                    AttachmentService $attachmentService): Response {
        $data = $request->request;
        $files = $request->files;
        $order = $manager->find(TransportOrder::class, $data->get('id'));
        $request = $order->getRequest();
        $now = new DateTime('now');

        $signature = $files->get('signature');
        $photo = $files->get('photo');

        $signatureAttachment = $attachmentService->createAttachements([$signature])[0];
        $photoAttachment = $attachmentService->createAttachements([$photo])[0];

        $order
            ->setComment($data->get('comment'))
            ->setSignature($signatureAttachment)
            ->addAttachment($photoAttachment)
            ->setTreatedAt($now);

        $categoryStatus = $request instanceof TransportCollectRequest
            ? CategorieStatut::TRANSPORT_ORDER_COLLECT
            : CategorieStatut::TRANSPORT_ORDER_DELIVERY;
        $status = $manager->getRepository(Statut::class)
            ->findOneByCategorieNameAndStatutCode($categoryStatus, TransportOrder::STATUS_FINISHED);

        $statusHistoryRequest = $statusHistoryService->updateStatus($manager, $order, $status);

        $historyService->persistTransportHistory($manager, $order, TransportHistoryService::TYPE_FINISHED, [
            'user' => $this->getUser(),
            'history' => $statusHistoryRequest,
            'attachments' => [$signatureAttachment, $photoAttachment]
        ]);

        $manager->flush();
        return $this->json([
            'success' => true
        ]);
    }

    /**
     * @Rest\Post("/api/patch-round-status", name="api_patch_round_status", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function patchRoundStatus(Request $request,
                                     EntityManagerInterface $manager,
                                     TransportHistoryService $historyService,
                                     StatusHistoryService $statusHistoryService): Response {
        $round = $manager->find(TransportRound::class, $request->request->get('round'));
        $user = $this->getUser();
        $now = new DateTime();

        $statusRespository = $manager->getRepository(Statut::class);
        $onGoingStatus = $statusRespository
            ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ROUND, TransportRound::STATUS_ONGOING);

        $round
            ->setStatus($onGoingStatus)
            ->setBeganAt($now);

        $orders = Stream::from($round->getTransportRoundLines())->map(fn(TransportRoundLine $line) => $line->getOrder());
        $requests = Stream::from($orders)->map(fn(TransportOrder $order) => $order->getRequest());
        $entities = Stream::from($orders)->concat($requests)->toArray();

        foreach ($entities as $entity) {
            if($entity instanceof TransportOrder) {
                $statusCode = TransportOrder::STATUS_ONGOING;
                $categoryStatus = $entity->getRequest() instanceof TransportCollectRequest
                    ? CategorieStatut::TRANSPORT_ORDER_COLLECT
                    : CategorieStatut::TRANSPORT_ORDER_DELIVERY;
            } else {
                $statusCode = TransportRequest::STATUS_ONGOING;
                $categoryStatus = $entity instanceof TransportCollectRequest
                    ? CategorieStatut::TRANSPORT_REQUEST_COLLECT
                    : CategorieStatut::TRANSPORT_REQUEST_DELIVERY;
            }
            $status = $statusRespository->findOneByCategorieNameAndStatutCode($categoryStatus, $statusCode);

            $statusHistoryRequest = $statusHistoryService->updateStatus($manager, $entity, $status);
            $historyService->persistTransportHistory($manager, $entity, TransportHistoryService::TYPE_ONGOING, [
                'user' => $user,
                'history' => $statusHistoryRequest,
            ]);
        }

        $manager->flush();
        return $this->json([
            'success' => true
        ]);
    }
}