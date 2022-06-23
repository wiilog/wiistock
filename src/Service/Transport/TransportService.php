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
use App\Service\CSVExportService;
use App\Service\FreeFieldService;
use App\Service\GeoService;
use App\Service\PackService;
use App\Service\SettingsService;
use App\Service\StatusHistoryService;
use App\Service\UniqueNumberService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use DoctrineExtensions\Query\Mysql\CountIf;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class TransportService {

    private const LYON_POSTAL_CODES = [
        69000, 69001, 69002, 69003, 69004,
        69005, 69006, 69007, 69008, 69009,
        69003, 69029, 69033, 69034, 69040,
        69044, 69046, 69063, 69068, 69069,
        69071, 69072, 69081, 69085, 69087,
        69088, 69089, 69091, 69096, 69100,
        69116, 69117, 69123, 69127, 69142,
        69143, 69149, 69152, 69153, 69163,
        69168, 69191, 69194, 69199, 69202,
        69204, 69205, 69207, 69233, 69244,
        69250, 69256, 69259, 69260, 69266,
        69271, 69273, 69275, 69276, 69278,
        69279, 69282, 69283, 69284, 69286,
        69290, 69292, 69293, 69296,
    ];

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

        $transportOrder = $transportRequest->getOrder();
        $orderInRound = (
            $transportOrder
            && $transportOrder->getStatus()?->getCode() === TransportOrder::STATUS_ASSIGNED
        );

        if ($transportRequest->getId() && !$transportRequest->canBeUpdated()) {
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
            $statusHistory = $this->statusHistoryService->updateStatus($entityManager, $transportRequest, $status);
            $this->transportHistoryService->persistTransportHistory($entityManager, $transportRequest, TransportHistoryService::TYPE_REQUEST_CREATION, [
                'history' => $statusHistory,
                'user' => $loggedUser,
            ]);

            if ($subcontracted) {
                $settingRepository = $entityManager->getRepository(Setting::class);
                $this->transportHistoryService->persistTransportHistory($entityManager, $transportRequest, TransportHistoryService::TYPE_SUBCONTRACTED, [
                    'user' => $loggedUser,
                ]);
                $this->transportHistoryService->persistTransportHistory($entityManager, $transportRequest, TransportHistoryService::TYPE_NO_MONITORING, [
                    'message' => $settingRepository->getOneParamByLabel(Setting::NON_BUSINESS_HOURS_MESSAGE) ?: ''
                ]);
            }
            elseif ($status == TransportRequest::STATUS_AWAITING_VALIDATION) {
                $settingRepository = $entityManager->getRepository(Setting::class);
                $this->transportHistoryService->persistTransportHistory($entityManager, $transportRequest, TransportHistoryService::TYPE_AWAITING_VALIDATION, [
                    'user' => $loggedUser,
                ]);
            }

        }
        else {
            if (!$transportRequest->canBeUpdated()) {
                throw new FormException("La modification de cette demande de transport n'est pas autorisée");
            }

            $oldStatus = $transportRequest->getStatus();
            $canChangeStatus = (
                $transportRequest->getExpectedAt() != $expectedAt
                && $transportRequest->getStatus()?->getId() !== $status->getId()
                && !$orderInRound
            );
            if ($canChangeStatus){
                $statusHistory = $this->statusHistoryService->updateStatus($entityManager, $transportRequest, $status);
            }
            $this->transportHistoryService->persistTransportHistory($entityManager, $transportRequest, TransportHistoryService::TYPE_REQUEST_EDITED, [
                'user' => $loggedUser,
                'history' => $statusHistory ?? null
            ]);

            if ($canChangeStatus) {
                if ($subcontracted) {
                    if ($oldStatus->getCode() !== TransportRequest::STATUS_SUBCONTRACTED) {
                        $settingRepository = $entityManager->getRepository(Setting::class);
                        $this->transportHistoryService->persistTransportHistory($entityManager, $transportRequest, TransportHistoryService::TYPE_NO_MONITORING, [
                            'message' => $settingRepository->getOneParamByLabel(Setting::NON_BUSINESS_HOURS_MESSAGE) ?: ''
                        ]);

                        $this->transportHistoryService->persistTransportHistory($entityManager, $transportRequest, TransportHistoryService::TYPE_SUBCONTRACTED, [
                            'user' => $loggedUser,
                        ]);
                    }
                }
                else if ($oldStatus->getCode() !== TransportRequest::STATUS_AWAITING_VALIDATION
                    && $status->getCode() === TransportRequest::STATUS_AWAITING_VALIDATION) {
                    $settingRepository = $entityManager->getRepository(Setting::class);
                    $this->transportHistoryService->persistTransportHistory($entityManager, $transportRequest, TransportHistoryService::TYPE_NO_MONITORING, [
                        'message' => $settingRepository->getOneParamByLabel(Setting::NON_BUSINESS_HOURS_MESSAGE) ?: ''
                    ]);
                    $this->transportHistoryService->persistTransportHistory($entityManager, $transportRequest, TransportHistoryService::TYPE_AWAITING_VALIDATION, [
                        'user' => $loggedUser,
                    ]);
                }
            }
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

        if (!$transportOrder) {
            $transportOrder = $this->persistTransportOrder($entityManager, $transportRequest, $loggedUser);
        }
        else if (!$orderInRound) {
            $this->updateOrderInitialStatus($entityManager, $transportRequest, $transportOrder, $loggedUser);
        }

        if (!$creation && $transportOrder) {
            $this->transportHistoryService->persistTransportHistory(
                $entityManager,
                $transportOrder,
                TransportHistoryService::TYPE_REQUEST_EDITED,
                [ 'user' => $loggedUser,]
            );
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
        $this->updateOrderInitialStatus($entityManager, $transportRequest, $transportOrder, $user);

        $transportOrder
            ->setCreatedAt(new DateTime())
            ->setRequest($transportRequest);

        $entityManager->persist($transportOrder);

        return $transportOrder;
    }

    public function updateOrderInitialStatus(EntityManagerInterface $entityManager,
                                             TransportRequest       $transportRequest,
                                             TransportOrder         $transportOrder,
                                             Utilisateur            $user): void {
        $creation = !$transportOrder->getId();

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

        if ($creation) {
            $transportHistoryType = TransportHistoryService::TYPE_REQUEST_CREATION;
        }
        else {
            $transportHistoryType = match($statusCode) {
                TransportOrder::STATUS_TO_ASSIGN => TransportHistoryService::TYPE_ACCEPTED,
                TransportOrder::STATUS_SUBCONTRACTED => TransportHistoryService::TYPE_SUBCONTRACTED,
                TransportOrder::STATUS_TO_CONTACT => TransportHistoryService::TYPE_AWAITING_PLANNING,
                TransportOrder::STATUS_AWAITING_VALIDATION => TransportHistoryService::TYPE_AWAITING_VALIDATION,
            };
        }

        $status = $statusRepository->findOneByCategorieNameAndStatutCode($categoryStatusName, $statusCode);
        if($transportOrder->getStatus()?->getId() !== $status->getId()) {
            $statusHistory = $this->statusHistoryService->updateStatus($entityManager, $transportOrder, $status);
            $this->transportHistoryService->persistTransportHistory($entityManager, $transportOrder, $transportHistoryType, [
                'history' => $statusHistory,
                'user' => $user
            ]);
        }

        $transportOrder
            ->setSubcontracted($transportRequest->getStatus()?->getCode() === TransportRequest::STATUS_SUBCONTRACTED);
    }

    #[ArrayShape(["status" => Statut::class, "subcontracted" => "bool"])]
    private function getStatusRequest(EntityManagerInterface $entityManager,
                                      TransportRequest       $transportRequest,
                                      DateTime               $expectedAt): array {
        $statusRepository = $entityManager->getRepository(Statut::class);
        $now = new DateTime();
        $nowAtMidnight = (clone $now)->setTime(0, 0);
        $expectedAtForDiff = (clone $expectedAt)->setTime(0, 0);

        $transportOrder = $transportRequest->getOrder();

        $diff = $nowAtMidnight->diff($expectedAtForDiff);
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

    public function putLineRequest($output, CSVExportService $csvService, TransportRequest $request, $freeFieldsConfig): void {
        $statusCodeExportCSV = [
            TransportRequest::STATUS_AWAITING_VALIDATION,
            TransportRequest::STATUS_TO_PREPARE,
            TransportRequest::STATUS_TO_DELIVER,
            TransportRequest::STATUS_ONGOING,
            TransportRequest::STATUS_FINISHED,
            TransportRequest::STATUS_CANCELLED,
            TransportRequest::STATUS_SUBCONTRACTED,
            TransportRequest::STATUS_AWAITING_PLANNING,
            TransportRequest::STATUS_TO_COLLECT,
            TransportRequest::STATUS_DEPOSITED
        ];

        $statusRequest = $request->getLastStatusHistory($statusCodeExportCSV);
        $freeFieldValues = $request->getFreeFields();
        $freeFields = [];

        foreach ($freeFieldsConfig['freeFields'] as $freeFieldId => $freeField) {
            $freeFields[] = FormatHelper::freeField($freeFieldValues[$freeFieldId] ?? '', $freeField);
        }
        $dataTransportRequest = [
            TransportRequest::NUMBER_PREFIX . $request->getNumber(),
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
                isset($statusRequest[TransportRequest::STATUS_AWAITING_VALIDATION]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_AWAITING_VALIDATION]) : '',
                isset($statusRequest[TransportRequest::STATUS_TO_PREPARE]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_TO_PREPARE]) : '',
                isset($statusRequest[TransportRequest::STATUS_TO_DELIVER]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_TO_DELIVER]) : '',
                isset($statusRequest[TransportRequest::STATUS_SUBCONTRACTED]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_SUBCONTRACTED]) : '',
                isset($statusRequest[TransportRequest::STATUS_ONGOING]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_ONGOING]) : '',
                isset($statusRequest[TransportRequest::STATUS_FINISHED]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_FINISHED]) : (isset($statusRequest[TransportRequest::STATUS_CANCELLED]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_CANCELLED]) : '' ),
                $request->getContact()->getObservation(),
            ]);

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
                    ], $freeFields);
                    $csvService->putLine($output, $dataTransportDeliveryRequestPacks);
                }
            }
            else {
                $offset = array_fill(0, 7, "");
                $csvService->putLine($output, array_merge($dataTransportDeliveryRequest, $offset, $freeFields));
            }
        }
        else if($request instanceof TransportCollectRequest) {
            $dataTransportCollectRequest = array_merge($dataTransportRequest, [
                FormatHelper::datetime($request->getValidatedDate()),
                isset($statusRequest[TransportRequest::STATUS_AWAITING_VALIDATION]) ? FormatHelper::datetime($request->getCreatedAt()): '',
                isset($statusRequest[TransportRequest::STATUS_AWAITING_PLANNING]) && isset($statusRequest[TransportRequest::STATUS_AWAITING_VALIDATION]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_AWAITING_PLANNING]) : '',
                isset($statusRequest[TransportRequest::STATUS_TO_COLLECT]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_TO_COLLECT]) : '',
                isset($statusRequest[TransportRequest::STATUS_ONGOING]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_ONGOING]) : '',
                isset($statusRequest[TransportRequest::STATUS_FINISHED]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_FINISHED]) : (isset($statusRequest[TransportRequest::STATUS_CANCELLED]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_CANCELLED]) : '' ),
                isset($statusRequest[TransportRequest::STATUS_DEPOSITED]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_DEPOSITED]): '',
                $request->getContact()->getObservation(),
            ]);

            $lines = $request->getLines()?:null;
            if ($lines && !$lines->isEmpty()) {
                /** @var TransportCollectRequestLine $line */
                foreach ($lines as $line) {
                    $dataTransportCollectRequestPacks = array_merge($dataTransportCollectRequest, [
                        $line->getNature()?->getLabel()? : '',
                        $line->getQuantityToCollect()? : '',
                        $line->getCollectedQuantity()? : '',
                    ], $freeFields);
                    $csvService->putLine($output, $dataTransportCollectRequestPacks);
                }
            }
            else {
                $offset = array_fill(0, 3, "");
                $lines = array_merge($dataTransportCollectRequest, $offset, $freeFields);
                $csvService->putLine($output, $lines);
            }
        }
    }

    public function isMetropolis(string|null $address): ?bool  {
        preg_match("/(\d{5})/", $address, $postalCode);
        foreach ($postalCode as $code) {
            if (in_array($code, self::LYON_POSTAL_CODES)) {
                return true;
            }
        }

        return false;
    }


    public function putLineOrder($output, CSVExportService $csvService, TransportOrder $order, $freeFieldsConfig): void {
        $statusCode = [
            TransportOrder::STATUS_TO_ASSIGN,
            TransportOrder::STATUS_ASSIGNED,
            TransportOrder::STATUS_ONGOING,
            TransportOrder::STATUS_FINISHED,
            TransportOrder::STATUS_CANCELLED,
        ];

        $request = $order->getRequest();
        $statusOrder = $order->getLastStatusHistory($statusCode);
        $freeFieldValues = $order->getRequest()->getFreeFields();
        $round = $order->getTransportRoundLines()->last();
        $freeFields = [];


        foreach ($freeFieldsConfig['freeFields'] as $freeFieldId => $freeField) {
            $freeFields[] = FormatHelper::freeField($freeFieldValues[$freeFieldId] ?? '', $freeField);
        }

        $transportRound = null;
        $transportRoundDeliverer = null;
        $estimatedDate = null;
        if($round) {
            $transportRound = $round->getTransportRound()->getNumber();
            $transportRoundDeliverer = $round->getTransportRound()->getDeliverer();
            $estimatedDate = $round->getTransportRound()->getTransportRoundLine($order)->getEstimatedAt();
        }


        $dataTransportRequest = [
            $request->getNumber(),
            $request instanceof TransportDeliveryRequest ? ($request->getCollect() ? "Livraison-Collecte" : "Livraison") : "Collecte",
            FormatHelper::type($request->getType()),
            FormatHelper::status($order->getStatus()),
            ...($request instanceof TransportDeliveryRequest ? [FormatHelper::bool(!empty($request->getEmergency()))] : []),
            FormatHelper::user($request->getCreatedBy()),
            $request->getContact()->getName(),
            $request->getContact()->getFileNumber(),
            str_replace("\n", " ", $request->getContact()->getAddress()),
            $request->getContact()->getAddress() ? FormatHelper::bool($this->isMetropolis($request->getContact()->getAddress())) : '',
            FormatHelper::datetime($request->getExpectedAt()),
        ];


        if($request instanceof TransportDeliveryRequest) {
            $dataTransportDeliveryRequest = array_merge($dataTransportRequest, [
                isset($statusOrder[TransportOrder::STATUS_TO_ASSIGN]) ? FormatHelper::datetime($statusOrder[TransportOrder::STATUS_TO_ASSIGN]) : '',
                isset($statusOrder[TransportOrder::STATUS_ASSIGNED]) ? FormatHelper::datetime($statusOrder[TransportOrder::STATUS_ASSIGNED]) : '',
                isset($statusOrder[TransportOrder::STATUS_ONGOING]) ? FormatHelper::datetime($statusOrder[TransportOrder::STATUS_ONGOING]) : '',
                FormatHelper::date($estimatedDate),
                isset($statusOrder[TransportOrder::STATUS_FINISHED]) ? FormatHelper::datetime($statusOrder[TransportOrder::STATUS_FINISHED]) : (isset($statusOrder[TransportOrder::STATUS_CANCELLED]) ? FormatHelper::datetime($statusOrder[TransportOrder::STATUS_CANCELLED]) : '' ),
                $transportRound,
                FormatHelper::user($transportRoundDeliverer),
                $request->getContact()->getObservation(),
            ]);

            $packs = $order->getPacks();

            if ($packs && !$packs->isEmpty()) {
                foreach ($packs as $pack) {
                    $dataTransportDeliveryRequestPacks = array_merge($dataTransportDeliveryRequest, [
                        $pack->getPack()?->getNature()?->getLabel() ?: '',
                        $pack->getPack()?->getQuantity() ?: '0',
                        $pack->getPack()?->getNature() ? $pack->getPackTemperature($pack->getPack()->getNature()) ?: '' : '',
                        $pack->getPack()?->getActivePairing()?->hasExceededThreshold() ? FormatHelper::bool($pack->getPack()?->getActivePairing()?->hasExceededThreshold()):"non",
                        $pack->getPack()?->getCode() ?: '',
                        $pack->getRejectedBy() ? 'Oui' : ($pack->getRejectReason() ? 'Oui' : 'Non'),
                        $pack->getRejectReason() ?: '',
                        FormatHelper::datetime($pack->getReturnedAt()),
                    ], $freeFields);
                    $csvService->putLine($output, $dataTransportDeliveryRequestPacks);
                }
            }
            else {
                $offset = array_fill(0, 8, "");
                $csvService->putLine($output, array_merge($dataTransportDeliveryRequest, $offset, $freeFields));
            }
        }
        else if($request instanceof TransportCollectRequest) {
            $dataTransportCollectRequest = array_merge($dataTransportRequest, [
                FormatHelper::datetime($request->getCreatedAt()),
                FormatHelper::datetime($request->getValidatedDate()),
                isset($statusOrder[TransportOrder::STATUS_TO_ASSIGN]) ? FormatHelper::datetime($statusOrder[TransportOrder::STATUS_TO_ASSIGN]) : '',
                isset($statusOrder[TransportOrder::STATUS_ASSIGNED]) ? FormatHelper::datetime($statusOrder[TransportOrder::STATUS_ASSIGNED]) : '',
                isset($statusOrder[TransportOrder::STATUS_ONGOING]) ? FormatHelper::datetime($statusOrder[TransportOrder::STATUS_ONGOING]) : '',
                FormatHelper::date($estimatedDate),
                isset($statusOrder[TransportOrder::STATUS_FINISHED]) ? FormatHelper::datetime($statusOrder[TransportOrder::STATUS_FINISHED]) : (isset($statusOrder[TransportOrder::STATUS_CANCELLED]) ? FormatHelper::datetime($statusOrder[TransportOrder::STATUS_CANCELLED]) : '' ),
                $order->getTreatedAt(),
                $transportRound,
                FormatHelper::user($transportRoundDeliverer),
                $request->getContact()->getObservation(),
            ]);

            $lines = $request->getLines()?:null;
            if ($lines && !$lines->isEmpty()) {
                /** @var TransportCollectRequestLine $line */
                foreach ($lines as $line) {
                    $dataTransportCollectRequestPacks = array_merge($dataTransportCollectRequest, [
                        $line->getNature()?->getLabel()? : '',
                        $line->getQuantityToCollect()? : '',
                        $line->getCollectedQuantity()? : '',
                    ], $freeFields);
                    $csvService->putLine($output, $dataTransportCollectRequestPacks);
                }
            }
            else {
                $offset = array_fill(0, 3, "");
                $csvService->putLine($output, array_merge($dataTransportCollectRequest, $offset, $freeFields));
            }
        }
    }

    public function updateSubcontractedRequestStatus(EntityManagerInterface          $entityManager,
                                                     Utilisateur                     $loggedUser,
                                                     TransportRequest|TransportOrder $transport,
                                                     Statut                          $status,
                                                     DateTime                        $date,
                                                     bool                            $setStatus): void {

        $statusHistory = $this->statusHistoryService->updateStatus($entityManager, $transport, $status, [
            'date' => $date,
            'forceCreation' => false,
            'setStatus' => $setStatus
        ]);

        $this->transportHistoryService->persistTransportHistory($entityManager, $transport, TransportHistoryService::TYPE_SUBCONTRACT_UPDATE, [
            'history' => $statusHistory,
            'statusDate' => $date,
            'user' => $loggedUser
        ]);
    }

    public function createPrintPackConfig(TransportRequest $transportRequest,
                                          ?string          $logo,
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
            ->map(function(TransportDeliveryOrderPack $pack, int $position) use ($logo, $contactName, $contactFileNumber, $cleanedContactAddress, $total, $temperatureRanges) {
                $temperatureRange = $temperatureRanges[$pack->getPack()?->getNature()?->getLabel()] ?? null;

                return [
                    'code' => $pack->getPack()->getCode(),
                    'labels' => [
                        ...(strlen($contactName) > 25
                            ? [$contactName, $contactFileNumber]
                            : ["$contactName - $contactFileNumber"]),
                        ...$cleanedContactAddress,
                        ...($temperatureRange ? [$temperatureRange] : []),
                        "$position/$total"
                    ],
                    'logo' => $logo
                ];
            })
            ->values();
    }

    /**
     * @param string $hour format H:i
     */
    public function hourToTimeSlot( EntityManagerInterface $entityManager, string $hour) : ?CollectTimeSlot{
        $timeSlotRepository = $entityManager->getRepository(CollectTimeSlot::class);
        $timeSlots = $timeSlotRepository->findAll();
        return Stream::from($timeSlots)->find(fn(CollectTimeSlot $timeSlot) => strtotime($timeSlot->getStart()) <= strtotime($hour) && strtotime($timeSlot->getEnd()) >= strtotime($hour));
    }

}
