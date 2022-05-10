<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Entity\Attachment;
use App\Entity\CategorieCL;
use App\Entity\FreeField;
use App\Entity\Pack;
use App\Entity\Setting;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportDeliveryOrderPack;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportDeliveryRequestLine;
use App\Entity\Transport\TransportRound;
use App\Entity\Transport\TransportRoundLine;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
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
        $transportRoundLineRepository = $manager->getRepository(TransportRoundLine::class);
        $freeFieldRepository = $manager->getRepository(FreeField::class);
        $user = $this->getUser();

        $transportRounds = $transportRoundRepository->findMobileTransportRoundsByUser($user);
        $data = Stream::from($transportRounds)
            ->map(function(TransportRound $round) use ($transportRoundLineRepository, $freeFieldRepository) {
                $lines = $transportRoundLineRepository->findLinesByRound($round);

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
                        ->filter(fn(TransportDeliveryOrderPack $orderPack) => $orderPack->isLoaded() && !$orderPack->getRejectReason())
                        ->count();
                }

                $doneDeliveries = 0;
                foreach ($lines as $line) {
                    $packs = $line->getOrder()->getPacks();
                    $partiallyLoaded = Stream::from($packs)
                        ->some(fn(TransportDeliveryOrderPack $orderPack) => !$orderPack->isLoaded() && $orderPack->getRejectReason());
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
                        ->map(function(TransportRoundLine $line) use ($freeFieldRepository) {
                            $order = $line->getOrder();
                            $request = $order->getRequest();
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
                                'type_icon' => $request->getType()?->getLogo()?->getFullPath(),
                                'kind' => $isCollect ? 'collect' : 'delivery',
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
                                            'rejected' => !!$orderPack->getRejectReason()
                                        ];
                                    }),
                                'expected_at' => $isCollect
                                    ? $request->getTimeSlot()->getName()
                                    : FormatHelper::datetime($request->getExpectedAt()),
                                'estimated_time' => $line->getEstimatedAt()?->format('H:i'),
                                'time_slot' => $isCollect ? $request->getTimeSlot()->getName() : null,
                                'contact' => [
                                    'file_number' => $contact->getFileNumber(),
                                    'address' => $contact->getAddress(),
                                    'contact' => $contact->getContact(),
                                    'person_to_contact' => $contact->getPersonToContact(),
                                    'observation' => $contact->getObservation(),
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
            ->setRejectReason($rejectMotive);

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
    public function loadPacks(Request $request, EntityManagerInterface $manager): Response {
        $data = $request->request;
        $packs = $manager->getRepository(Pack::class)->findBy(['code' => json_decode($data->get('packs'))]);

        foreach ($packs as $pack) {
            $orderPack = $pack->getTransportDeliveryOrderPack();
            $orderPack->setLoaded(true);
        }

        $manager->flush();
        return $this->json([
            'success' => true
        ]);
    }
}