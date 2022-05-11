<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Entity\Attachment;
use App\Entity\CategorieCL;
use App\Entity\Emplacement;
use App\Entity\FreeField;
use App\Entity\Pack;
use App\Entity\Setting;
use App\Entity\TrackingMovement;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportDeliveryOrderPack;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportDeliveryRequestLine;
use App\Entity\Transport\TransportRequest;
use App\Entity\Transport\TransportRound;
use App\Entity\Transport\TransportRoundLine;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use App\Service\TrackingMovementService;
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
            ->map(function(TransportRound $round) use ($freeFieldRepository) {
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

                $doneDeliveries = 0;
                foreach ($lines as $line) {
                    $packs = $line->getOrder()->getPacks();
                    $partiallyLoaded = Stream::from($packs)
                        ->some(fn(TransportDeliveryOrderPack $orderPack) => $orderPack->getState() !== TransportDeliveryOrderPack::LOADED_STATE && $orderPack->getRejectReason());
                    if(!$partiallyLoaded) {
                        $doneDeliveries += 1;
                    }
                }

                return [
                    'id' => $round->getId(),
                    'number' => $round->getNumber(),
                    'status' => FormatHelper::status($round->getStatus()),
                    'date' => FormatHelper::date($round->getExpectedAt()),
                    'estimated_distance' => $round->getEstimatedDistance(),
                    'estimated_time' => str_replace(':', 'h', $round->getEstimatedTime()) . 'min',
                    'done_transports' => Stream::from($lines)
                        ->filter(fn(TransportRoundLine $line) => $line->getFulfilledAt())
                        ->count(),
                    'total_transports' => count($lines),
                    'loaded_packs' => $loadedPacks,
                    'total_loaded' => $totalLoaded,
                    'done_deliveries' => $doneDeliveries,
                    'total_deliveries' => Stream::from($lines)
                        ->filter(fn(TransportRoundLine $line) => $line->getOrder()->getRequest() instanceof TransportDeliveryRequest)
                        ->count(),
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
                                'id' => $line->getTransportRound()->getId(),
                                'number' => $request->getNumber(),
                                'type' => FormatHelper::type($request->getType()),
                                'type_icon' => $request->getType()?->getLogo() ? $_SERVER["APP_URL"] . $request->getType()->getLogo()->getFullPath() : null,
                                'kind' => $isCollect ? 'collect' : 'delivery',
                                'collect' => $collect ? [
                                    'type' => $collect->getType()->getLabel(),
                                    'type_icon' => $collect->getType()?->getLogo() ? $_SERVER["APP_URL"] . $collect->getType()->getLogo()->getFullPath() : null,
                                    'time_slot' => $collect->getTimeSlot()->getName(),
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
                                    ->map(fn(FreeField $freeField) => [
                                        'id' => $freeField->getId(),
                                        'label' => $freeField->getLabel(),
                                        'value' => $freeFieldsValues[$freeField->getId()] ?? '',
                                    ]),
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
            })->toArray();

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
    public function rejectPack(Request $request, EntityManagerInterface $manager): Response {
        $data = $request->request;
        $pack = $manager->getRepository(Pack::class)->findOneBy(['code' => $data->get('pack')]);
        $rejectMotive = $data->get('rejectMotive');

        $transportDeliveryOrderPack = $pack->getTransportDeliveryOrderPack();

        $transportDeliveryOrderPack
            ->setRejectedBy($this->getUser())
            ->setRejectReason($rejectMotive)
            ->setState(TransportDeliveryOrderPack::REJECTED_STATE);

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
        $location = $manager->getRepository(Emplacement::class)->find($data->get('location'));
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
}