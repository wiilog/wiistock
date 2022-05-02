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
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportDeliveryRequestLine;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
use App\Entity\Transport\TransportRequestContact;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Helper\FormatHelper;
use App\Service\FreeFieldService;
use App\Service\SettingsService;
use App\Service\StatusHistoryService;
use App\Service\UniqueNumberService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Google\Service\CloudDomains\Contact;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\Attribute\Required;

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
            UniqueNumberService::DATE_COUNTER_FORMAT_TRANSPORT_REQUEST
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

        return $transportRequest;
    }

    public function updateTransportRequest(EntityManagerInterface   $entityManager,
                                           TransportRequest         $transportRequest,
                                           ?InputBag                 $data,
                                           Utilisateur              $loggedUser,
                                           ?TransportRequestContact $customContact = null,
                                           ?DateTime                $customExpectedAt = null): void {

        $natureRepository = $entityManager->getRepository(Nature::class);
        $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);

        $expectedAtStr = $data?->get('expectedAt');

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
        if (!$transportRequest->getStatus()) {
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
        else if ($transportRequest->getStatus()->getId() !== $status->getId()){
            throw new FormException('Impossible : votre modification engendre une modification du statut de la demande');
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
                $contact
                    ->setName($data->get('contactName') ?: $contact->getName())
                    ->setFileNumber($data->get('contactFileNumber') ?: $contact->getFileNumber())
                    ->setContact($data->get('contactContact') ?: $contact->getContact())
                    ->setAddress($data->get('contactAddress') ?: $contact->getAddress())
                    ->setPersonToContact($data->get('contactPersonToContact') ?: $contact->getPersonToContact())
                    ->setObservation($data->get('contactObservation') ?: $contact->getObservation());
            }
        }

        if ($transportRequest->getOrders()->isEmpty()
            && $status->getCode() !== TransportRequest::STATUS_AWAITING_VALIDATION) {
            $this->persistTransportOrder($entityManager, $transportRequest, $loggedUser, $subcontracted);
        }

        $transportRequest->setLines([]);
        $lines = json_decode($data?->get('lines', '[]') ?? "", true) ?: [];
        foreach ($lines as $line) {
            $selected = $line['selected'] ?? false;
            $natureId = $line['natureId'] ?? null;
            $quantity = $line['quantity'] ?? null;
            $temperatureId = $line['temperature'] ?? null;
            $nature = $natureId ? $natureRepository->find($natureId) : null;
            if ($selected && $nature) {
                if ($transportRequest instanceof TransportDeliveryRequest) {
                    $temperature = $temperatureId ? $temperatureRangeRepository->find($temperatureId) : null;
                    $line = new TransportDeliveryRequestLine();
                    $line->setTemperatureRange($temperature);
                }
                else if ($transportRequest instanceof TransportCollectRequest) {
                    $line = new TransportCollectRequestLine();
                    $line->setQuantityToCollect($quantity);
                }
                else {
                    throw new \RuntimeException('Unknown request type');
                }

                $line->setNature($nature);
                $transportRequest->addLine($line);

                $entityManager->persist($line);
            }
        }

        if ($transportRequest->getLines()->isEmpty()) {
            throw new FormException('Vous devez sélectionner au moins une nature de colis dans vote demande');
        }

        $entityManager->persist($transportRequest);
    }

    public function persistTransportOrder(EntityManagerInterface $entityManager,
                                          TransportRequest $transportRequest,
                                          Utilisateur $user,
                                          bool $subcontracted = false): TransportOrder {
        $statusRepository = $entityManager->getRepository(Statut::class);


        if ($transportRequest instanceof TransportDeliveryRequest) {
            $categoryStatusName = CategorieStatut::TRANSPORT_ORDER_DELIVERY;
            $statusCode = $subcontracted ? TransportOrder::STATUS_SUBCONTRACTED : TransportOrder::STATUS_TO_ASSIGN;
        }
        else if ($transportRequest instanceof TransportCollectRequest) {
            $categoryStatusName = CategorieStatut::TRANSPORT_ORDER_COLLECT;
            $statusCode = TransportOrder::STATUS_TO_CONTACT;
        }
        else {
            throw new \RuntimeException('Unknown request type');
        }

        $transportOrder = new TransportOrder();

        $status = $statusRepository->findOneByCategorieNameAndStatutCode($categoryStatusName, $statusCode);
        $statusHistory = $this->statusHistoryService->updateStatus($entityManager, $transportOrder, $status);
        $this->transportHistoryService->persistTransportHistory($entityManager, $transportOrder, TransportHistoryService::TYPE_REQUEST_CREATION, [
            'history' => $statusHistory,
            'user' => $user
        ]);

        $transportOrder
            ->setCreatedAt(new DateTime())
            ->setRequest($transportRequest)
            ->setSubcontracted($subcontracted);

        $entityManager->persist($transportOrder);

        return $transportOrder;
    }

    #[ArrayShape(["status" => Statut::class, "subcontracted" => "bool"])]
    private function getStatusRequest(EntityManagerInterface $entityManager,
                                      TransportRequest $transportRequest,
                                      DateTime $expectedAt): array {
        $statusRepository = $entityManager->getRepository(Statut::class);
        $now = new DateTime();

        $diff = $now->diff($expectedAt);
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
                $code = TransportRequest::STATUS_TO_PREPARE;
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
}
