<?php

namespace App\Service\Transport;

use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\IOT\TriggerAction;
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
use App\Entity\Transport\TransportRound;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Exceptions\GeoException;
use App\Service\CSVExportService;
use App\Service\FormatService;
use App\Service\FreeFieldService;
use App\Service\GeoService;
use App\Service\IOT\IOTService;
use App\Service\OperationHistoryService;
use App\Service\PackService;
use App\Service\SettingsService;
use App\Service\StatusHistoryService;
use App\Service\TranslationService;
use App\Service\UniqueNumberService;
use App\Service\WorkPeriod\WorkPeriodService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use WiiCommon\Helper\Stream;

class TransportService {

    private const LYON_POSTAL_CODES = [
        69250, 69500, 69270, 69300, 69410,
        69260, 69390, 69680, 69660, 69960,
        69270, 69290, 69250, 69570, 69150,
        69130, 69320, 69250, 69270, 69270,
        69340, 69730, 69700, 69520, 69540,
        69330, 69760, 69380, 69000, 69280,
        69330, 69780, 69250, 69350, 69250,
        69600, 69310, 69250, 69650, 69140,
        69270, 69450, 69370, 69190, 69230,
        69290, 69650, 69800, 69270, 69110,
        69580, 69580, 69360, 69160, 69890,
        69120, 69200, 69390, 69100
    ];

    public function __construct(
        private UniqueNumberService     $uniqueNumberService,
        private StatusHistoryService    $statusHistoryService,
        private OperationHistoryService $operationHistoryService,
        private FreeFieldService        $freeFieldService,
        private PackService             $packService,
        private GeoService              $geoService,
        private FormatService           $formatService,
        private TranslationService      $translation,
        private RouterInterface         $router,
        private WorkPeriodService       $workPeriodService,
        private SettingsService         $settingsService,
    ) {
    }

    public function persistTransportRequest(EntityManagerInterface    $entityManager,
                                            Utilisateur               $user,
                                            InputBag                  $data,
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
            $expectedAt = $this->formatService->parseDatetime($expectedAtStr);

            if (!$expectedAt) {
                throw new FormException("Le format de date est invalide");
            }
        }

        $expectedAt = $expectedAt ?? $transportRequest->getExpectedAt();

        ['status' => $status, 'subcontracted' => $subcontracted] = $this->getStatusRequest($entityManager, $transportRequest, $expectedAt);
        if ($creation) { // transport creation
            $statusHistory = $this->statusHistoryService->updateStatus($entityManager, $transportRequest, $status, [
                'initiatedBy' => $loggedUser,
            ]);

            $historyType = $transportRequest instanceof TransportDeliveryRequest && $data->getBoolean('collectLinked')
                ? OperationHistoryService::TYPE_BOTH_REQUEST_CREATION
                : OperationHistoryService::TYPE_REQUEST_CREATION;

            $this->operationHistoryService->persistTransportHistory($entityManager, $transportRequest, $historyType, [
                'history' => $statusHistory,
                'user' => $loggedUser,
            ]);

            if ($subcontracted) {
                $settingRepository = $entityManager->getRepository(Setting::class);
                $this->operationHistoryService->persistTransportHistory($entityManager, $transportRequest, OperationHistoryService::TYPE_SUBCONTRACTED, [
                    'user' => $loggedUser,
                ]);
                $this->operationHistoryService->persistTransportHistory($entityManager, $transportRequest, OperationHistoryService::TYPE_NO_MONITORING, [
                    'message' => $this->settingsService->getValue($entityManager, Setting::NON_BUSINESS_HOURS_MESSAGE) ?: ''
                ]);
            }
            elseif ($status == TransportRequest::STATUS_AWAITING_VALIDATION) {
                $this->operationHistoryService->persistTransportHistory($entityManager, $transportRequest, OperationHistoryService::TYPE_AWAITING_VALIDATION, [
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
                $statusHistory = $this->statusHistoryService->updateStatus($entityManager, $transportRequest, $status, [
                    'initiatedBy' => $loggedUser,
                ]);
            }
            $this->operationHistoryService->persistTransportHistory($entityManager, $transportRequest, OperationHistoryService::TYPE_REQUEST_EDITED, [
                'user' => $loggedUser,
                'history' => $statusHistory ?? null
            ]);

            if ($transportOrder) {
                $this->operationHistoryService->persistTransportHistory(
                    $entityManager,
                    $transportOrder,
                    OperationHistoryService::TYPE_REQUEST_EDITED,
                    [ 'user' => $loggedUser,]
                );
            }

            if ($canChangeStatus) {
                if ($subcontracted) {
                    if ($oldStatus->getCode() !== TransportRequest::STATUS_SUBCONTRACTED) {
                        $this->operationHistoryService->persistTransportHistory($entityManager, $transportRequest, OperationHistoryService::TYPE_NO_MONITORING, [
                            'message' => $this->settingsService->getValue($entityManager, Setting::NON_BUSINESS_HOURS_MESSAGE) ?: ''
                        ]);

                        $this->operationHistoryService->persistTransportHistory($entityManager, $transportRequest, OperationHistoryService::TYPE_SUBCONTRACTED, [
                            'user' => $loggedUser,
                        ]);
                    }
                }
                else if ($oldStatus->getCode() !== TransportRequest::STATUS_AWAITING_VALIDATION
                    && $status->getCode() === TransportRequest::STATUS_AWAITING_VALIDATION) {
                    $settingRepository = $entityManager->getRepository(Setting::class);
                    $this->operationHistoryService->persistTransportHistory($entityManager, $transportRequest, OperationHistoryService::TYPE_AWAITING_VALIDATION, [
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
                throw new FormException('Vous devez saisir le n° du dossier');
            }
            if (!$data?->get('contactContact') && !$contact->getContact()) {
                throw new FormException('Vous devez saisir le nom du contact');
            }
            if (!$data?->get('contactAddress') && !$contact->getAddress()) {
                throw new FormException("Vous devez saisir l'adresse du contact");
            }
            if (!$data?->get('contactPersonToContact') && !$contact->getPersonToContact()) {
                throw new FormException('Vous devez saisir la personne à contacter');
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

        $linesResult = $this->updateTransportRequestLines($entityManager, $transportRequest, $data);

        if ($transportRequest->getLines()->isEmpty()) {
            throw new FormException('Vous devez sélectionner au moins une nature dans vote demande');
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
                default => $transportOrder->getStatus()?->getCode(),
            };
        }
        else {
            throw new \RuntimeException('Unknown request type');
        }

        if ($creation) {
            $transportHistoryType = OperationHistoryService::TYPE_REQUEST_CREATION;
        }
        else {
            $transportHistoryType = match($statusCode) {
                TransportOrder::STATUS_TO_ASSIGN => OperationHistoryService::TYPE_ACCEPTED,
                TransportOrder::STATUS_SUBCONTRACTED => OperationHistoryService::TYPE_SUBCONTRACTED,
                TransportOrder::STATUS_TO_CONTACT => OperationHistoryService::TYPE_AWAITING_PLANNING,
                TransportOrder::STATUS_AWAITING_VALIDATION => OperationHistoryService::TYPE_AWAITING_VALIDATION,
            };
        }

        $status = $statusRepository->findOneByCategorieNameAndStatutCode($categoryStatusName, $statusCode);
        if($transportOrder->getStatus()?->getId() !== $status->getId()) {
            $statusHistory = $this->statusHistoryService->updateStatus($entityManager, $transportOrder, $status, [
                'initiatedBy' => $user,
            ]);
            $this->operationHistoryService->persistTransportHistory($entityManager, $transportOrder, $transportHistoryType, [
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

            $isWorked = $this->workPeriodService->isOnWorkPeriod($entityManager, $expectedAt);
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


        $lines = Stream::from($lines)
            ->filter(static fn($line) => ($line['selected'] ?? false))
            ->toArray();

        foreach ($lines as $line) {
            $selected = (bool) ($line['selected'] ?? false);
            $natureId = $line['natureId'] ?? null;
            $quantity = ($line['quantity'] ?? null) ?: null;
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
                    $line->setProperty('temperatureRange', $temperature);
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
        $pack = $this->packService->createPack($entityManager, ['orderLine' => $orderPack, 'nature' => $nature]);
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
            $freeFields[] = $this->formatService->freeField($freeFieldValues[$freeFieldId] ?? '', $freeField);
        }
        $dataTransportRequest = [
            TransportRequest::NUMBER_PREFIX . $request->getNumber(),
            $request instanceof TransportDeliveryRequest ? ($request->getCollect() ? $this->translation->translate("Demande", "Livraison", "Livraison", false) . "-Collecte" : $this->translation->translate("Demande", "Livraison", "Livraison", false)) : "Collecte",
            $this->formatService->type($request->getType()),
            $this->formatService->status($request->getStatus()),
            ...($request instanceof TransportDeliveryRequest ? [$this->formatService->bool(!empty($request->getEmergency()))] : []),
            $this->formatService->user($request->getCreatedBy()),
            $request->getContact()->getName(),
            $request->getContact()->getFileNumber(),
            str_replace("\n", " / ", $request->getContact()->getAddress()),
            $request->getContact()->getAddress() ? $this->formatService->bool($this->isMetropolis($request->getContact()->getAddress())) : '',
            $this->formatService->datetime($request->getExpectedAt()),
        ];

        if($request instanceof TransportDeliveryRequest) {
            $dataTransportDeliveryRequest = array_merge($dataTransportRequest, [
                isset($statusRequest[TransportRequest::STATUS_AWAITING_VALIDATION]) ? $this->formatService->datetime($statusRequest[TransportRequest::STATUS_AWAITING_VALIDATION]) : '',
                isset($statusRequest[TransportRequest::STATUS_TO_PREPARE]) ? $this->formatService->datetime($statusRequest[TransportRequest::STATUS_TO_PREPARE]) : '',
                isset($statusRequest[TransportRequest::STATUS_TO_DELIVER]) ? $this->formatService->datetime($statusRequest[TransportRequest::STATUS_TO_DELIVER]) : '',
                isset($statusRequest[TransportRequest::STATUS_SUBCONTRACTED]) ? $this->formatService->datetime($statusRequest[TransportRequest::STATUS_SUBCONTRACTED]) : '',
                isset($statusRequest[TransportRequest::STATUS_ONGOING]) ? $this->formatService->datetime($statusRequest[TransportRequest::STATUS_ONGOING]) : '',
                isset($statusRequest[TransportRequest::STATUS_FINISHED]) ? $this->formatService->datetime($statusRequest[TransportRequest::STATUS_FINISHED]) : (isset($statusRequest[TransportRequest::STATUS_CANCELLED]) ? $this->formatService->datetime($statusRequest[TransportRequest::STATUS_CANCELLED]) : '' ),
                $request->getContact()->getObservation(),
            ]);

            $packs = $request->getOrder()?->getPacks();

            if ($packs && !$packs->isEmpty()) {
                foreach ($packs as $pack) {
                    $dataTransportDeliveryRequestPacks = array_merge($dataTransportDeliveryRequest, [
                        $this->formatService->nature($pack->getPack()?->getNature()),
                        $pack->getPack()?->getQuantity() ?: '0',
                        $pack->getPack()?->getNature() ? $pack->getPackTemperature($pack->getPack()->getNature()) ?: '' : '',
                        $pack->getPack()?->getCode() ?: '',
                        $pack->getState() === TransportDeliveryOrderPack::REJECTED_STATE
                            ? 'Oui'
                            : ($pack->getState() !== null
                                ? 'Non'
                                : '-'),
                        $pack->getRejectReason() ?: ($pack->getState() && $pack->getState() !== TransportDeliveryOrderPack::REJECTED_STATE ? '/' : '-'),
                        $pack->getReturnedAt() ? $this->formatService->datetime($pack->getReturnedAt()) : '-',
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
                $request->getValidatedDate() ? $this->formatService->datetime($request->getValidatedDate()) : '-',
                isset($statusRequest[TransportRequest::STATUS_AWAITING_VALIDATION]) ? $this->formatService->datetime($request->getCreatedAt()): '',
                isset($statusRequest[TransportRequest::STATUS_AWAITING_PLANNING]) && isset($statusRequest[TransportRequest::STATUS_AWAITING_VALIDATION]) ? $this->formatService->datetime($statusRequest[TransportRequest::STATUS_AWAITING_PLANNING]) : '',
                isset($statusRequest[TransportRequest::STATUS_TO_COLLECT]) ? $this->formatService->datetime($statusRequest[TransportRequest::STATUS_TO_COLLECT]) : '',
                isset($statusRequest[TransportRequest::STATUS_ONGOING]) ? $this->formatService->datetime($statusRequest[TransportRequest::STATUS_ONGOING]) : '',
                isset($statusRequest[TransportRequest::STATUS_FINISHED]) ? $this->formatService->datetime($statusRequest[TransportRequest::STATUS_FINISHED]) : (isset($statusRequest[TransportRequest::STATUS_CANCELLED]) ? $this->formatService->datetime($statusRequest[TransportRequest::STATUS_CANCELLED]) : '' ),
                isset($statusRequest[TransportRequest::STATUS_DEPOSITED]) ? $this->formatService->datetime($statusRequest[TransportRequest::STATUS_DEPOSITED]): '',
                $request->getContact()->getObservation(),
            ]);

            $lines = $request->getLines()?:null;
            if ($lines && !$lines->isEmpty()) {
                /** @var TransportCollectRequestLine $line */
                foreach ($lines as $line) {
                    $dataTransportCollectRequestPacks = array_merge($dataTransportCollectRequest, [
                        $this->formatService->nature($line->getNature()),
                        $line->getQuantityToCollect() !== null ? $line->getQuantityToCollect() : '/',
                        $line->getCollectedQuantity() !== null ? $line->getCollectedQuantity() : '-',
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
            $freeFields[] = $this->formatService->freeField($freeFieldValues[$freeFieldId] ?? '', $freeField);
        }

        $transportRoundNumber = null;
        $transportRoundDeliverer = null;
        $estimatedDate = null;
        if($round) {
            $transportRoundNumber = $round->getTransportRound()->getNumber();
            $transportRoundDeliverer = $round->getTransportRound()->getDeliverer();
            $estimatedDate = $round->getTransportRound()->getTransportRoundLine($order)->getEstimatedAt();
        }


        $dataTransportRequest = [
            $request->getNumber(),
            $request instanceof TransportDeliveryRequest ? ($request->getCollect() ? $this->translation->translate("Demande", "Livraison", "Livraison", false) . "-Collecte" : $this->translation->translate("Demande", "Livraison", "Livraison", false)) : "Collecte",
            $this->formatService->type($request->getType()),
            $this->formatService->status($order->getStatus()),
            ...($request instanceof TransportDeliveryRequest ? [$this->formatService->bool(!empty($request->getEmergency()))] : []),
            $this->formatService->user($request->getCreatedBy()),
            $request->getContact()->getName(),
            $request->getContact()->getFileNumber(),
            str_replace("\n", " ", $request->getContact()->getAddress()),
            $request->getContact()->getAddress() ? $this->formatService->bool($this->isMetropolis($request->getContact()->getAddress())) : '',
            $this->formatService->datetime($request->getExpectedAt()),
        ];


        if($request instanceof TransportDeliveryRequest) {
            $dataTransportDeliveryRequest = array_merge($dataTransportRequest, [
                isset($statusOrder[TransportOrder::STATUS_TO_ASSIGN]) ? $this->formatService->datetime($statusOrder[TransportOrder::STATUS_TO_ASSIGN]) : '',
                isset($statusOrder[TransportOrder::STATUS_ASSIGNED]) ? $this->formatService->datetime($statusOrder[TransportOrder::STATUS_ASSIGNED]) : '',
                isset($statusOrder[TransportOrder::STATUS_ONGOING]) ? $this->formatService->datetime($statusOrder[TransportOrder::STATUS_ONGOING]) : '',
                $estimatedDate ? $this->formatService->date($estimatedDate) : '/',
                isset($statusOrder[TransportOrder::STATUS_FINISHED]) ? $this->formatService->datetime($statusOrder[TransportOrder::STATUS_FINISHED]) : (isset($statusOrder[TransportOrder::STATUS_CANCELLED]) ? $this->formatService->datetime($statusOrder[TransportOrder::STATUS_CANCELLED]) : '' ),
                $transportRoundNumber,
                $this->formatService->user($transportRoundDeliverer),
                $request->getContact()->getObservation(),
            ]);

            $packs = $order->getPacks();

            if (!$packs->isEmpty()) {
                foreach ($packs as $pack) {
                    $dataTransportDeliveryRequestPacks = array_merge($dataTransportDeliveryRequest, [
                        $this->formatService->nature($pack->getPack()?->getNature()),
                        $pack->getPack()?->getQuantity() ?: '0',
                        $pack->getPack()?->getNature() ? $pack->getPackTemperature($pack->getPack()->getNature()) ?: '' : '',
                        $pack->getPack()?->getActivePairing()?->hasExceededThreshold()
                            ? $this->formatService->bool($pack->getPack()?->getActivePairing()?->hasExceededThreshold())
                            : "non",
                        $pack->getPack()?->getCode() ?: '',
                        $pack->getState() === TransportDeliveryOrderPack::REJECTED_STATE
                            ? 'Oui'
                            : ($pack->getState() !== null
                                ? 'Non'
                                : '-'),
                        $pack->getRejectReason() ?: ($pack->getState() && $pack->getState() !== TransportDeliveryOrderPack::REJECTED_STATE ? '/' : '-'),
                        $pack->getReturnedAt() ? $this->formatService->datetime($pack->getReturnedAt()) : '-',
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
            $timeSlot = $request->getTimeSlot()?->getName();
            $dataTransportCollectRequest = array_merge($dataTransportRequest, [
                $this->formatService->datetime($request->getCreatedAt()),
                $request->getValidatedDate() ? $this->formatService->datetime($request->getValidatedDate()) : '-',
                isset($statusOrder[TransportOrder::STATUS_TO_ASSIGN]) ? $this->formatService->datetime($statusOrder[TransportOrder::STATUS_TO_ASSIGN]) : '',
                isset($statusOrder[TransportOrder::STATUS_ASSIGNED]) ? $this->formatService->datetime($statusOrder[TransportOrder::STATUS_ASSIGNED]) : '',
                isset($statusOrder[TransportOrder::STATUS_ONGOING]) ? $this->formatService->datetime($statusOrder[TransportOrder::STATUS_ONGOING]) : '',
                $timeSlot ?? ($estimatedDate ? $this->formatService->date($estimatedDate) : '/'),
                isset($statusOrder[TransportOrder::STATUS_FINISHED]) ? $this->formatService->datetime($statusOrder[TransportOrder::STATUS_FINISHED]) : (isset($statusOrder[TransportOrder::STATUS_CANCELLED]) ? $this->formatService->datetime($statusOrder[TransportOrder::STATUS_CANCELLED]) : '' ),
                $this->formatService->datetime($order->getTreatedAt()),
                $transportRoundNumber,
                $this->formatService->user($transportRoundDeliverer),
                $request->getContact()->getObservation(),
            ]);

            $lines = $request->getLines()?:null;
            if ($lines && !$lines->isEmpty()) {
                /** @var TransportCollectRequestLine $line */
                foreach ($lines as $line) {
                    $dataTransportCollectRequestPacks = array_merge($dataTransportCollectRequest, [
                        $this->formatService->nature($line->getNature()),
                        $line->getQuantityToCollect() !== null ? $line->getQuantityToCollect() : '/',
                        $line->getCollectedQuantity() !== null ? $line->getCollectedQuantity() : '-',
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
            'setStatus' => $setStatus,
            'initiatedBy' => $loggedUser,
        ]);

        $this->operationHistoryService->persistTransportHistory($entityManager, $transport, OperationHistoryService::TYPE_SUBCONTRACT_UPDATE, [
            'history' => $statusHistory,
            'statusDate' => $date,
            'user' => $loggedUser
        ]);
    }

    public function createPrintPackConfig(TransportRequest $transportRequest, ?string          $logo, array            $deliveryPackIds = []): array {
        $packs = Stream::from($transportRequest->getOrder()?->getPacks() ?: []);
        $contact = $transportRequest->getContact();
        $contactName = $contact->getName();
        $contactFileNumber = $contact->getFileNumber();
        $contactAddress = $contact->getAddress();

        $contactAddress = preg_replace('/\s(\d{5})/', "\n$1", $contactAddress);

        $maxLineLength = 40;
        $cleanedContactAddress = Stream::explode("\n", $contactAddress)
            ->filter()
            //mettre à la ligne les éléments de l'adresse pour le svg
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
                $this->formatService->nature($line->getNature()),
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
            ->filter(fn(TransportDeliveryOrderPack $pack) => !$pack->getRejectReason() && ($filteredPacksEmpty || in_array($pack->getId(), $deliveryPackIds)))
            ->map(function(TransportDeliveryOrderPack $pack, int $position) use ($logo, $contactName, $contactFileNumber, $cleanedContactAddress, $total, $temperatureRanges) {
                $temperatureRange = $temperatureRanges[$this->formatService->nature($pack->getPack()?->getNature())] ?? null;
                $separated = strlen($contactName) > 25;

                return [
                    'code' => $pack->getPack()->getCode(),
                    'labels' => [
                        ...($separated
                            ? [$contactName, $contactFileNumber]
                            : ["$contactName - $contactFileNumber"]),
                        ...$cleanedContactAddress,
                        ...($temperatureRange ? [$temperatureRange] : []),
                        "$position/$total"
                    ],
                    'separated' => $separated,
                    'logo' => $logo
                ];
            })
            ->values();
    }

    /**
     * @param string $hour format H:i
     */
    public function hourToTimeSlot(EntityManagerInterface $entityManager, string $hour): ?CollectTimeSlot{
        $timeSlotRepository = $entityManager->getRepository(CollectTimeSlot::class);
        $timeSlots = $timeSlotRepository->findAll();
        return Stream::from($timeSlots)
            ->find(fn(CollectTimeSlot $timeSlot) => (
                strtotime($timeSlot->getStart()) <= strtotime($hour)
                && strtotime($timeSlot->getEnd()) >= strtotime($hour)
            ));
    }

    public function getTemperatureChartConfig(TransportRound $round): array {
        $urls = [];
        foreach ($round?->getLocations() ?? [] as $location) {
            if(!$round->getBeganAt()) {
                continue;
            }

            $triggerActions = $location->getActivePairing()?->getSensorWrapper()?->getTriggerActions();
            if($triggerActions) {
                $minTriggerActionThreshold = Stream::from($triggerActions)
                    ->filter(fn(TriggerAction $triggerAction) => $triggerAction->getConfig()['limit'] === 'lower')
                    ->last();
                $maxTriggerActionThreshold = Stream::from($triggerActions)
                    ->filter(fn(TriggerAction $triggerAction) => $triggerAction->getConfig()['limit'] === 'higher')
                    ->last();
                $minThreshold = $minTriggerActionThreshold?->getConfig()['temperature'];
                $maxThreshold = $maxTriggerActionThreshold?->getConfig()['temperature'];
            }
            if(!$round->getEndedAt()) {
                $end = clone ($round->getBeganAt() ?? new DateTime("now"));
                $end->setTime(23, 59);
            } else {
                $end = min((clone ($round->getBeganAt()))->setTime(23, 59), $round->getEndedAt());
            }

            $urls[] = [
                "fetch_url" => $this->router->generate("chart_data_history", [
                    "type" => IOTService::getEntityCodeFromEntity($location),
                    "id" => $location->getId(),
                    'start' => $round->getBeganAt()->format('Y-m-d\TH:i'),
                    'end' => $end->format('Y-m-d\TH:i'),
                    'messageContentType' => IOTService::DATA_TYPE_TEMPERATURE,
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                "minTemp" => $minThreshold ?? 0,
                "maxTemp" => $maxThreshold ?? 0,
            ];
        }
        if (empty($urls)) {
            $urls[] = [
                "fetch_url" => $this->router->generate("chart_data_history", [
                    "type" => null,
                    "id" => null,
                    'start' => new DateTime('now'),
                    'end' => new DateTime('tomorrow'),
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                "minTemp" => 0,
                "maxTemp" => 0,
            ];
        }

        return $urls;
    }
}
