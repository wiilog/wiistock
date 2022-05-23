<?php

namespace App\Service\Transport;

use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Nature;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Transport\CollectTimeSlot;
use App\Entity\Transport\TemperatureRange;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportCollectRequestLine;
use App\Entity\Transport\TransportDeliveryOrderPack;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportDeliveryRequestLine;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
use App\Entity\Transport\TransportRequestContact;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Exceptions\GeoException;
use App\Helper\FormatHelper;
use App\Service\FreeFieldService;
use App\Service\GeoService;
use App\Service\PackService;
use App\Service\SettingsService;
use App\Service\StatusHistoryService;
use App\Service\UniqueNumberService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class TransportService {

    #[Required]
    public UniqueNumberService $uniqueNumberService;

    #[Required]
    public SettingsService $settingsService;

    #[Required]
    public StatusHistoryService $statusHistoryService;

    #[Required]
    public TransportHistoryService $transportHistoryService;

    #[Required]
    public FreeFieldService $freeFieldService;

    #[Required]
    public PackService $packService;

    #[Required]
    public GeoService $geoService;

    public function persistTransportRequest(EntityManagerInterface $entityManager,
                                            Utilisateur $user,
                                            InputBag $data,
                                            ?TransportDeliveryRequest $mainDelivery = null): TransportRequest {

        $typeRepository = $entityManager->getRepository(Type::class);

        $transportRequestType = $data->get('requestType');
        if (($mainDelivery && $transportRequestType !== TransportRequest::DISCR_COLLECT)
            || !in_array($transportRequestType, [TransportRequest::DISCR_COLLECT, TransportRequest::DISCR_DELIVERY])) {
            throw new FormException("Veuillez sélectionner un type de demande de transport");
        }

        $typeStr = $data->get('type');

        if ($transportRequestType === TransportRequest::DISCR_DELIVERY) {
            $categoryType = CategoryType::DELIVERY_TRANSPORT;
            $transportRequest = new TransportDeliveryRequest();
        }
        else if ($transportRequestType === TransportRequest::DISCR_COLLECT) {
            $categoryType = CategoryType::COLLECT_TRANSPORT;
            $transportRequest = new TransportCollectRequest();

            if ($mainDelivery) {
                $transportRequest->setDelivery($mainDelivery);
            }
        }
        else {
            throw new \RuntimeException('Unknown request type');
        }

        $type = $typeRepository->findOneByCategoryLabel($categoryType, $typeStr);
        if (!isset($type)) {
            throw new FormException("Veuillez sélectionner un type pour votre demande de transport");
        }

        $number = $this->uniqueNumberService->create(
            $entityManager,
            null,
            TransportRequest::class,
            UniqueNumberService::DATE_COUNTER_FORMAT_TRANSPORT
        );

        if ($mainDelivery) {
            $expectedAt = clone $mainDelivery->getExpectedAt();
            $expectedAt->setTime(0, 0);
        }

        $this->updateTransportRequest($entityManager, $transportRequest, $data, $user, $mainDelivery?->getContact(), $expectedAt ?? null);

        $transportRequest
            ->setType($type)
            ->setNumber($number)
            ->setCreatedAt(new DateTime())
            ->setCreatedBy($user);

        $entityManager->persist($transportRequest);

        return $transportRequest;
    }

    public function updateTransportRequest(EntityManagerInterface   $entityManager,
                                           TransportRequest         $transportRequest,
                                           ?InputBag                $data,
                                           Utilisateur              $loggedUser,
                                           ?TransportRequestContact $customContact = null,
                                           ?DateTime                $customExpectedAt = null): array {

        $expectedAtStr = $data?->get('expectedAt');
        $creation = !$transportRequest->getId();

        if ($transportRequest->getId()
            && !$transportRequest->canBeUpdated()) {
            throw new FormException("Votre demande de transport n'est pas modifiable");
        }

        if ($transportRequest instanceof TransportDeliveryRequest) {
            $transportRequest
                ->setEmergency($data?->get('emergency'));
        }

        if (isset($customExpectedAt)) {
            $expectedAt = $customExpectedAt;
        }
        else if(isset($expectedAtStr)){
            $expectedAt = FormatHelper::parseDatetime($expectedAtStr);

            if (!$expectedAt) {
                throw new FormException("Le format de date est invalide");
            }
        }

        $expectedAt = $expectedAt ?? $transportRequest->getExpectedAt();

        ['status' => $status, 'subcontracted' => $subcontracted] = $this->getStatusRequest($entityManager, $transportRequest, $expectedAt);
        if ($creation) { // transport creation
            if ($subcontracted) {
                $settingRepository = $entityManager->getRepository(Setting::class);
                $this->transportHistoryService->persistTransportHistory($entityManager, $transportRequest, TransportHistoryService::TYPE_NO_MONITORING, [
                    'message' => $settingRepository->getOneParamByLabel(Setting::NON_BUSINESS_HOURS_MESSAGE) ?: ''
                ]);
            }

            $statusHistory = $this->statusHistoryService->updateStatus($entityManager, $transportRequest, $status);
            $this->transportHistoryService->persistTransportHistory($entityManager, $transportRequest, TransportHistoryService::TYPE_REQUEST_CREATION, [
                'history' => $statusHistory,
                'user' => $loggedUser,
            ]);
        }
        else {
            if (!$transportRequest->canBeUpdated()) {
                throw new FormException("La modification de cette demande de transport n'est pas autorisée");
            }

            $canChangeStatus = (
                $transportRequest->getExpectedAt() != $expectedAt
                && $transportRequest->getStatus()?->getId() !== $status->getId()
            );
            if ($canChangeStatus){
                $statusHistory = $this->statusHistoryService->updateStatus($entityManager, $transportRequest, $status);
            }

            $this->transportHistoryService->persistTransportHistory($entityManager, $transportRequest, TransportHistoryService::TYPE_REQUEST_EDITED, [
                'history' => $statusHistory ?? null,
                'user' => $loggedUser,
            ]);
        }

        $transportRequest
            ->setExpectedAt($expectedAt);

        $this->freeFieldService->manageFreeFields($transportRequest, $data?->all(), $entityManager);

        if ($customContact) {
            $transportRequest->setContact($customContact);
        }
        else {
            $contact = $transportRequest->getContact();

            if (!$data?->get('contactName') && !$contact->getName()) {
                throw new FormException('Vous devez saisir le nom du patient');
            }

            if (!$data?->get('contactFileNumber') && !$contact->getFileNumber()) {
                throw new FormException('Vous devez saisir le N° dossier');
            }

            if(isset($data)){
                $oldAddress = $contact->getAddress();
                $contact
                    ->setName($data->get('contactName') ?: $contact->getName())
                    ->setFileNumber($data->get('contactFileNumber') ?: $contact->getFileNumber())
                    ->setContact($data->get('contactContact') ?: $contact->getContact())
                    ->setAddress($data->get('contactAddress') ?: $contact->getAddress())
                    ->setPersonToContact($data->get('contactPersonToContact') ?: $contact->getPersonToContact())
                    ->setObservation($data->get('contactObservation') ?: $contact->getObservation());
            }
        }

        $transportOrder = $transportRequest->getOrder();
        if (!$transportOrder) {
            if ($status->getCode() !== TransportRequest::STATUS_AWAITING_VALIDATION) {
                $this->persistTransportOrder($entityManager, $transportRequest, $loggedUser);
            }
        }
        else {
            $this->updateTransportOrderStatus($entityManager, $transportRequest, $transportOrder, $loggedUser);
        }

        $linesResult = $this->updateTransportRequestLines($entityManager, $transportRequest, $data);

        if ($transportRequest->getLines()->isEmpty()) {
            throw new FormException('Vous devez sélectionner au moins une nature de colis dans vote demande');
        }

        $oldAddress = $oldAddress ?? null;
        $contact = $transportRequest->getContact();
        $address = $contact->getAddress();
        $oldLat = $contact->getAddressLatitude();
        $oldLon = $contact->getAddressLongitude();

        if ($address
            && (
                ($oldAddress && $oldAddress !== $address)
                || !$oldLat
                || !$oldLon
            )) {
            try {
                [$lat, $lon] = $this->geoService->fetchCoordinates($transportRequest->getContact()->getAddress());
            } catch (GeoException $exception) {
                throw new FormException($exception->getMessage());
            }
            $contact->setAddressLatitude($lat);
            $contact->setAddressLongitude($lon);
        }
        else if (!$address) {
            $contact->setAddressLatitude(null);
            $contact->setAddressLongitude(null);
        }

        return $linesResult;
    }

    public function persistTransportOrder(EntityManagerInterface $entityManager,
                                          TransportRequest $transportRequest,
                                          Utilisateur $user): TransportOrder {

        $transportOrder = new TransportOrder();
        $this->updateTransportOrderStatus($entityManager, $transportRequest, $transportOrder, $user);

        $transportOrder
            ->setCreatedAt(new DateTime())
            ->setRequest($transportRequest);

        $entityManager->persist($transportOrder);

        return $transportOrder;
    }

    public function updateTransportOrderStatus(EntityManagerInterface $entityManager,
                                               TransportRequest $transportRequest,
                                               TransportOrder $transportOrder,
                                               Utilisateur $user): void {

        $statusRepository = $entityManager->getRepository(Statut::class);

        if ($transportRequest instanceof TransportDeliveryRequest) {
            $categoryStatusName = CategorieStatut::TRANSPORT_ORDER_DELIVERY;
            $statusCode = match ($transportRequest->getStatus()?->getCode()) {
                TransportRequest::STATUS_TO_DELIVER, TransportRequest::STATUS_TO_PREPARE => TransportOrder::STATUS_TO_ASSIGN,
                TransportRequest::STATUS_SUBCONTRACTED => TransportOrder::STATUS_SUBCONTRACTED,
                TransportRequest::STATUS_AWAITING_VALIDATION => TransportOrder::STATUS_AWAITING_VALIDATION,
            };
        }
        else if ($transportRequest instanceof TransportCollectRequest) {
            $categoryStatusName = CategorieStatut::TRANSPORT_ORDER_COLLECT;
            $statusCode = match ($transportRequest->getStatus()?->getCode()) {
                TransportRequest::STATUS_AWAITING_PLANNING => TransportOrder::STATUS_TO_CONTACT,
                TransportRequest::STATUS_AWAITING_VALIDATION => TransportOrder::STATUS_AWAITING_VALIDATION,
            };
        }
        else {
            throw new \RuntimeException('Unknown request type');
        }

        $status = $statusRepository->findOneByCategorieNameAndStatutCode($categoryStatusName, $statusCode);
        $statusHistory = $this->statusHistoryService->updateStatus($entityManager, $transportOrder, $status);
        $this->transportHistoryService->persistTransportHistory($entityManager, $transportOrder, TransportHistoryService::TYPE_REQUEST_CREATION, [
            'history' => $statusHistory,
            'user' => $user
        ]);

        $transportOrder
            ->setSubcontracted($transportRequest->getStatus()?->getCode() === TransportRequest::STATUS_SUBCONTRACTED);
    }

    #[ArrayShape(["status" => Statut::class, "subcontracted" => "bool"])]
    private function getStatusRequest(EntityManagerInterface $entityManager,
                                      TransportRequest $transportRequest,
                                      DateTime $expectedAt): array {
        $statusRepository = $entityManager->getRepository(Statut::class);
        $now = (new DateTime())->setTime(0, 0);
        $expectedAtForDiff = (clone $expectedAt)->setTime(0, 0);

        $transportOrder = $transportRequest->getOrder();

        $diff = $now->diff($expectedAtForDiff);
        if ($transportRequest instanceof TransportDeliveryRequest) {
            $category = CategorieStatut::TRANSPORT_REQUEST_DELIVERY;

            $isWorked = $this->settingsService->isWorked($entityManager, $expectedAt);
            if (!$isWorked || $now > $expectedAt) {
                $code = TransportRequest::STATUS_SUBCONTRACTED;
                $subcontracted = true;
            }
            else if ($diff->days == 0) {
                $code = TransportRequest::STATUS_AWAITING_VALIDATION;
                $subcontracted = false;
            }
            else if ($expectedAt >= $now) {
                $code = ($transportOrder && !$transportOrder->getPacks()->isEmpty())
                    ? TransportRequest::STATUS_TO_DELIVER
                    : TransportRequest::STATUS_TO_PREPARE;
                $subcontracted = false;
            }
            else {
                throw new \RuntimeException('Unavailable expected date');
            }
        }
        else if ($transportRequest instanceof TransportCollectRequest) {
            $category = CategorieStatut::TRANSPORT_REQUEST_COLLECT;
            $code = $diff->days == 0
                ? TransportRequest::STATUS_AWAITING_VALIDATION
                : TransportRequest::STATUS_AWAITING_PLANNING;
            $subcontracted = false;
        }
        else {
            throw new \RuntimeException('Unknown request type');
        }

        $status = $statusRepository->findOneByCategorieNameAndStatutCode($category, $code);

        return [
            'status' => $status,
            'subcontracted' => $subcontracted
        ];
    }

    public function getTimeslot(EntityManagerInterface $manager, DateTime $date): ?CollectTimeSlot {
        $timeSlotRepository = $manager->getRepository(CollectTimeSlot::class);
        $timeSlots = $timeSlotRepository->findAll();

        $hour = $date->format("H");
        $minute = $date->format("i");
        foreach($timeSlots as $timeSlot) {
            [$startHour, $startMinute] = explode(":", $timeSlot->getStart());
            [$endHour, $endMinute] = explode(":", $timeSlot->getEnd());

            $isAfterStart = $hour > $startHour || ($hour == $startHour && $minute >= $startMinute);
            $isBeforeEnd = $hour < $endHour || ($hour == $endHour && $minute <= $endMinute);

            if ($isAfterStart && $isBeforeEnd) {
                return $timeSlot;
            }
        }

        return null;
    }

    #[ArrayShape(["createdPacks" => 'array'])]
    public function updateTransportRequestLines(EntityManagerInterface $entityManager,
                                                TransportRequest       $transportRequest,
                                                ?InputBag              $data): array {

        $natureRepository = $entityManager->getRepository(Nature::class);
        $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);

        $transportOrder = $transportRequest->getOrder();

        $lines = json_decode($data?->get('lines', '[]') ?? "", true) ?: [];

        $treatedNatures = [];

        $createdPacks = [];

        foreach ($lines as $line) {
            $selected = (bool) ($line['selected'] ?? false);
            $natureId = $line['natureId'] ?? null;
            $quantity = $line['quantity'] ?? null;
            $temperatureId = $line['temperature'] ?? null;
            $nature = $natureId ? $natureRepository->find($natureId) : null;
            if ($selected && $nature) {

                $line = $transportRequest->getLine($nature);
                $treatedNatures[] = $nature->getId();

                if (!isset($line)) {
                    if ($transportRequest instanceof TransportDeliveryRequest) {
                        $line = new TransportDeliveryRequestLine();
                    }
                    else if ($transportRequest instanceof TransportCollectRequest) {
                        $line = new TransportCollectRequestLine();
                    }
                    else {
                        throw new \RuntimeException('Unknown request type');
                    }

                    $transportRequest->addLine($line);
                    $entityManager->persist($line);
                }

                $line->setNature($nature);

                if ($line instanceof TransportDeliveryRequestLine) {
                    $temperature = $temperatureId ? $temperatureRangeRepository->find($temperatureId) : null;
                    $line->setTemperatureRange($temperature);

                    // adding packs in modification form
                    if ($transportOrder) {
                        $orderPackCountForNature = $transportOrder->getPacksForLine($line)->count();
                        $quantityDelta = $quantity - $orderPackCountForNature;
                        for ($packIndex = 0; $packIndex < $quantityDelta; $packIndex++) {
                            $createdPacks[] = $this->persistDeliveryPack($entityManager, $transportOrder, $nature);
                        }
                    }
                }
                else if ($line instanceof TransportCollectRequestLine) {
                    $line->setQuantityToCollect($quantity);
                }
                else {
                    throw new \RuntimeException('Unknown request type');
                }
            }
        }

        foreach ($transportRequest->getLines()->toArray() as $line) {
            if (!in_array($line->getNature()->getId(), $treatedNatures)
                && (
                    !$transportOrder
                    || $transportOrder->getPacksForLine($line)->count() === 0
                )) {
                $transportRequest->removeLine($line);
                $entityManager->remove($line);
            }
        }

        return [
            'createdPacks' => $createdPacks
        ];
    }

    public function persistDeliveryPack(EntityManagerInterface $entityManager,
                                        TransportOrder $transportOrder,
                                        Nature $nature): TransportDeliveryOrderPack {
        $orderPack = new TransportDeliveryOrderPack();
        $orderPack->setOrder($transportOrder);
        $pack = $this->packService->createPack(['orderLine' => $orderPack, 'nature' => $nature]);
        $entityManager->persist($orderPack);
        $entityManager->persist($pack);
        return $orderPack;
    }

    public function updateSubcontractedRequestStatus(EntityManagerInterface          $entityManager,
                                                     Utilisateur                     $loggedUser,
                                                     TransportRequest|TransportOrder $transport,
                                                     Statut                          $status,
                                                     DateTime                        $dateTime,
                                                     bool                            $setStatus): void {

        $statusHistory = $this->statusHistoryService->updateStatus($entityManager, $transport, $status, $dateTime, [
            'forceCreation' => false,
            'setStatus' => $setStatus
        ]);

        $this->transportHistoryService->persistTransportHistory($entityManager, $transport, TransportHistoryService::TYPE_SUBCONTRACT_UPDATE, [
            'history' => $statusHistory,
            'statusDate' => $dateTime,
            'user' => $loggedUser
        ]);
    }

    public function createPrintPackConfig(TransportRequest $transportRequest,
                                          string           $logo,
                                          array            $deliveryPackIds = []): array {
        $packs = Stream::from($transportRequest->getOrder()?->getPacks() ?: []);
        $contact = $transportRequest->getContact();
        $contactName = $contact->getName();
        $contactFileNumber = $contact->getFileNumber();
        $contactAddress = $contact->getAddress();

        $contactAddress = preg_replace('/\s(\d{5})/', "\n$1", $contactAddress);

        $maxLineLength = 40;
        $cleanedContactAddress = Stream::explode("\n", $contactAddress)
            ->flatMap(function (string $part) use ($maxLineLength) {
                $part = trim($part);
                $lineLength = strlen($part);
                if ($lineLength > $maxLineLength) {
                    $results = [];

                    while (!empty($part)) {
                        $words = explode(" ", $part);
                        $finalPart = "";
                        foreach ($words as $word) {
                            if (empty($finalPart) || strlen($finalPart) + strlen($word) < $maxLineLength) {
                                if (!empty($finalPart)) {
                                    $finalPart .= " ";
                                }
                                $finalPart .= $word;
                            } else {
                                break;
                            }
                        }
                        $results[] = trim($finalPart);
                        if (strlen($finalPart) < strlen($part)) {
                            $part = trim(substr($part, strlen($finalPart)));
                        } else {
                            break;
                        }
                    }
                    return $results;
                } else {
                    return [$part];
                }
            })
            ->filterMap(fn(string $line) => trim($line))
            ->toArray();

        $temperatureRanges = Stream::from($transportRequest->getLines())
            ->filter(fn($line) => $line instanceof TransportDeliveryRequestLine)
            ->keymap(fn(TransportDeliveryRequestLine $line) => [
                $line->getNature()->getLabel(),
                $line->getTemperatureRange()?->getValue()
            ])
            ->toArray();

        $filteredPacksEmpty = (
            empty($deliveryPackIds)
            || Stream::from($packs)
                ->filter(fn(TransportDeliveryOrderPack $pack) => in_array($pack->getId(), $deliveryPackIds))
                ->isEmpty()
        );

        $total = $packs->count();
        return $packs
            ->keymap(fn(TransportDeliveryOrderPack $pack, int $index) => [(string) ($index + 1), $pack])
            ->filter(fn(TransportDeliveryOrderPack $pack) => ($filteredPacksEmpty || in_array($pack->getId(), $deliveryPackIds)))
            ->map(fn(TransportDeliveryOrderPack $pack, int $position) => [
                'code' => $pack->getPack()->getCode(),
                'labels' => [
                    "$contactName - $contactFileNumber",
                    ...$cleanedContactAddress,
                    ($temperatureRanges[$pack->getPack()->getNature()->getLabel()] ?? '- °C'),
                    "$position/$total"
                ],
                'logo' => $logo
            ])
            ->values();
    }
}
