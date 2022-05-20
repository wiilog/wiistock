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
use App\Helper\FormatHelper;
use App\Service\CSVExportService;
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
                                           ?DateTime                $customExpectedAt = null): void {

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
        if (!$transportRequest->getId()) { // transport creation
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

            if ($transportRequest->getStatus()?->getId() !== $status->getId()){
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

        $this->updateTransportRequestLines($entityManager, $transportRequest, $data);

        if ($transportRequest->getLines()->isEmpty()) {
            throw new FormException('Vous devez sélectionner au moins une nature de colis dans vote demande');
        }

        [$lat, $lon] = $this->geoService->fetchCoordinates($transportRequest->getContact()->getAddress());
        $transportRequest->getContact()->setAddressLatitude($lat);
        $transportRequest->getContact()->setAddressLongitude($lon);
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

    public function updateTransportRequestLines(EntityManagerInterface $entityManager,
                                                TransportRequest       $transportRequest,
                                                ?InputBag              $data) {

        $natureRepository = $entityManager->getRepository(Nature::class);
        $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);

        $transportOrder = $transportRequest->getOrder();

        $lines = json_decode($data?->get('lines', '[]') ?? "", true) ?: [];

        $treatedNatures = [];

        foreach ($lines as $line) {
            $selected = $line['selected'] ?? false;
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
                }

                $line->setNature($nature);
                $transportRequest->addLine($line);

                if ($line instanceof TransportDeliveryRequestLine) {
                    $temperature = $temperatureId ? $temperatureRangeRepository->find($temperatureId) : null;
                    $line->setTemperatureRange($temperature);

                    // adding packs in modification form
                    if ($transportOrder) {
                        $orderPackCountForNature = $transportOrder->getPacksForLine($line)->count();
                        $quantityDelta = $quantity - $orderPackCountForNature;
                        for ($packIndex = 0; $packIndex < $quantityDelta; $packIndex++) {
                            $this->persistDeliveryPack($entityManager, $transportOrder, $nature);
                        }
                    }
                }
                else if ($line instanceof TransportCollectRequestLine) {
                    $line->setQuantityToCollect($quantity);
                }
                else {
                    throw new \RuntimeException('Unknown request type');
                }

                $entityManager->persist($line);
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

    public function putLine($output, CSVExportService $csvService, TransportRequest $request, $freeFieldsConfig): void {
        $statusRequest = $request->getLastStatusHistory();
        $freeFieldValues = $request->getFreeFields();
        $freeFields = [];

        foreach ($freeFieldsConfig['freeFields'] as $freeFieldId => $freeField) {
            $freeFields[] = FormatHelper::freeField($freeFieldValues[$freeFieldId] ?? '', $freeField);
        }
        $dataTransportRequest = [
            $request->getNumber(),
            $request instanceof TransportDeliveryRequest ? ($request->getCollect() ? "Livraison-Collecte" : "Livraison") : "Collecte",
            FormatHelper::type($request->getType()),
            FormatHelper::status($request->getStatus()),
            ...($request instanceof TransportDeliveryRequest ? [FormatHelper::bool(!empty($request->getEmergency()))] : []),
            FormatHelper::user($request->getCreatedBy()),
            $request->getContact()->getName(),
            $request->getContact()->getFileNumber(),
            str_replace("\n", " / ", $request->getContact()->getAddress()),
            $request->getContact()->getAddress() ? FormatHelper::bool($this->isMetropolis($request->getContact()->getAddress())) : '',
            FormatHelper::datetime($request->getExpectedAt()),
            ];

        if($request instanceof TransportDeliveryRequest) {
            $dataTransportDeliveryRequest = array_merge($dataTransportRequest, [
                FormatHelper::datetime($request->getValidatedDate()),
                isset($statusRequest[TransportRequest::STATUS_TO_PREPARE]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_TO_PREPARE]) : '',
                isset($statusRequest[TransportRequest::STATUS_TO_DELIVER]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_TO_DELIVER]) : '',
                isset($statusRequest[TransportRequest::STATUS_SUBCONTRACTED]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_SUBCONTRACTED]) : '',
                isset($statusRequest[TransportRequest::STATUS_ONGOING]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_ONGOING]) : '',
                isset($statusRequest[TransportRequest::STATUS_FINISHED]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_FINISHED]) : (isset($statusRequest[TransportRequest::STATUS_CANCELLED]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_CANCELLED]) : '' ),
                $request->getContact()->getObservation(),
            ], $freeFields);

            $packs = $request->getOrder()?->getPacks();

            if ($packs && !$packs->isEmpty()) {
                foreach ($packs as $pack) {
                    $dataTransportDeliveryRequestPacks = array_merge($dataTransportDeliveryRequest, [
                        $pack->getPack()?->getNature()?->getLabel() ?: '',
                        $pack->getPack()?->getQuantity() ?: '0',
                        $pack->getPack()?->getNature() ? $pack->getPackTemperature($pack->getPack()->getNature()) ?: '' : '',
                        $pack->getPack()?->getCode() ?: '',
                        $pack->getRejectedBy() ? 'Oui' : ($pack->getRejectReason() ? 'Oui' : 'Non'),
                        $pack->getRejectReason() ?: '',
                        FormatHelper::datetime($pack->getReturnedAt()),
                    ]);
                    $csvService->putLine($output, $dataTransportDeliveryRequestPacks );
                }
            }
            else {
                $csvService->putLine($output, $dataTransportDeliveryRequest);
            }
        }
        else if($request instanceof TransportCollectRequest) {
            $dataTransportCollectRequest = array_merge($dataTransportRequest, [
                FormatHelper::datetime($request->getValidatedDate()),
                FormatHelper::datetime($request->getCreatedAt()),
                isset($statusRequest[TransportRequest::STATUS_AWAITING_PLANNING]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_AWAITING_PLANNING]) : '',
                isset($statusRequest[TransportRequest::STATUS_TO_COLLECT]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_TO_COLLECT]) : '',
                isset($statusRequest[TransportRequest::STATUS_ONGOING]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_ONGOING]) : '',
                isset($statusRequest[TransportRequest::STATUS_FINISHED]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_FINISHED]) : (isset($statusRequest[TransportRequest::STATUS_CANCELLED]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_CANCELLED]) : '' ),
                isset($statusRequest[TransportRequest::STATUS_DEPOSITED]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_DEPOSITED]): '',
                $request->getContact()->getObservation(),
            ],$freeFields);

            $lines = $request->getLines()?:null;
            if ($lines && !$lines->isEmpty()) {
                /** @var TransportCollectRequestLine $line */
                foreach ($lines as $line) {
                    $dataTransportCollectRequestPacks = array_merge($dataTransportCollectRequest, [
                        $line->getNature()?->getLabel()? : '',
                        $line->getQuantityToCollect()? : '',
                        $line->getCollectedQuantity()? : '',
                    ]);
                    $csvService->putLine($output, $dataTransportCollectRequestPacks);
                }
            }
            else {
                $tableEmpty = ['','',''];
                $lines = array_merge($dataTransportCollectRequest, $tableEmpty);
                $csvService->putLine($output, $lines);
            }
        }
    }

    public function isMetropolis(string|null $address): ?bool  {
        $postalCodeMetropolisStr = $_SERVER['POSTAL_CODE_METROPOLIS'] ?? null;
        if($postalCodeMetropolisStr) {
            $postalCodeMetropolis = explode(",", $postalCodeMetropolisStr);
            preg_match("/\s(\d{5})/", $address, $postalCode);
            foreach ($postalCode as $code) {
                if (in_array($code, $postalCodeMetropolis)) {
                    return true;
                }
            }
        }
        return false;
    }
}
