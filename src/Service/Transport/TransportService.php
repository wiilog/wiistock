<?php

namespace App\Service\Transport;

use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Nature;
use App\Entity\Statut;
use App\Entity\Transport\TemperatureRange;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportCollectRequestNature;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportDeliveryRequestNature;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
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
use JetBrains\PhpStorm\ArrayShape;
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
                                            Request $request): TransportRequest {

        $typeRepository = $entityManager->getRepository(Type::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $temperatureRangeRepository = $entityManager->getRepository(TemperatureRange::class);

        $transportRequestType = $request->request->get('requestType');
        if (!in_array($transportRequestType, [TransportRequest::DISCR_COLLECT, TransportRequest::DISCR_DELIVERY])) {
            throw new FormException("Veuillez sélectionner un type de demande de transport");
        }

        $typeStr = $request->request->get('type');
        $expectedAtStr = $request->request->get('expectedAt');

        if ($transportRequestType === TransportRequest::DISCR_DELIVERY) {
            $categoryType = CategoryType::DELIVERY_TRANSPORT_REQUEST;
            $transportRequest = new TransportDeliveryRequest();
            $transportRequest
                ->setEmergency($request->request->get('emergency') ?: null);
        }
        else if ($transportRequestType === TransportRequest::DISCR_COLLECT) {
            $categoryType = CategoryType::COLLECT_TRANSPORT_REQUEST;
            $transportRequest = new TransportCollectRequest();
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

        $expectedAt = FormatHelper::parseDatetime($expectedAtStr);

        if (!$expectedAt) {
            throw new FormException("Le format de date est invalide");
        }

        ['status' => $status, 'subcontracted' => $subcontracted] = $this->getStatusRequest($entityManager, $transportRequestType, $expectedAt);
        $statusHistory = $this->statusHistoryService->updateStatus($entityManager, $transportRequest, $status);
        $this->transportHistoryService->persistTransportHistory($entityManager, $transportRequest, TransportHistoryService::TYPE_REQUEST_CREATION, [
            'history' => $statusHistory
        ]);

        $transportRequest
            ->setType($type)
            ->setNumber($number)
            ->setExpectedAt($expectedAt)
            ->setCreatedAt(new DateTime())
            ->setCreatedBy($user);

        $this->freeFieldService->manageFreeFields($transportRequest, $request->request->all(), $entityManager);

        $contact = $transportRequest->getContact();
        $contact
            ->setName($request->request->get('contactName'))
            ->setFileNumber($request->request->get('contactFileNumber'))
            ->setContact($request->request->get('contactContact'))
            ->setAddress($request->request->get('contactAddress'))
            ->setPersonToContact($request->request->get('contactPersonToContact'))
            ->setObservation($request->request->get('contactObservation'));

        if ($status->getCode() !== TransportRequest::STATUS_AWAITING_VALIDATION) {
            $this->persistTransportOrder($entityManager, $transportRequest, $subcontracted);
        }

        $lines = json_decode($request->request->get('lines', '[]'), true) ?: [];
        foreach ($lines as $line) {
            $selected = $line['selected'] ?? false;
            $natureId = $line['natureId'] ?? null;
            $quantity = $line['quantity'] ?? null;
            $temperatureId = $line['temperature'] ?? null;
            $nature = $natureId ? $natureRepository->find($natureId) : null;
            if ($selected && $nature) {
                if ($transportRequestType === TransportRequest::DISCR_DELIVERY) {
                    $temperature = $temperatureId ? $temperatureRangeRepository->find($temperatureId) : null;
                    $line = new TransportDeliveryRequestNature();
                    $line->setTemperatureRange($temperature);
                    $transportRequest->addTransportDeliveryRequestNature($line);
                }
                else if ($transportRequestType === TransportRequest::DISCR_COLLECT) {
                    $line = new TransportCollectRequestNature();
                    $line->setQuantityToCollect($quantity);
                    $transportRequest->addTransportCollectRequestNature($line);
                }
                else {
                    throw new \RuntimeException('Unknown request type');
                }

                $line->setNature($nature);

                $entityManager->persist($line);
            }
        }

        $entityManager->persist($transportRequest);

        return $transportRequest;
    }

    public function persistTransportOrder(EntityManagerInterface $entityManager,
                                          TransportRequest $transportRequest,
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
            'history' => $statusHistory
        ]);

        $transportOrder
            ->setCreatedAt(new DateTime())
            ->setSubcontracted($subcontracted);

        $entityManager->persist($transportOrder);

        return $transportOrder;
    }

    #[ArrayShape(["status" => Statut::class, "subcontracted" => "bool"])]
    private function getStatusRequest(EntityManagerInterface $entityManager,
                                      string $transportRequestType,
                                      DateTime $expectedAt): array {
        $statusRepository = $entityManager->getRepository(Statut::class);
        $now = new DateTime();

        $diff = $now->diff($expectedAt);
        if ($transportRequestType === TransportRequest::DISCR_DELIVERY) {
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
        else if ($transportRequestType === TransportRequest::DISCR_COLLECT) {
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
}
