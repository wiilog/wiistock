<?php

namespace App\Service;

use App\Entity\CategoryType;
use App\Entity\Nature;
use App\Entity\Transport\TemperatureRange;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportCollectRequestNature;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportDeliveryRequestNature;
use App\Entity\Transport\TransportRequest;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Helper\FormatHelper;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\Attribute\Required;

class TransportService {

    #[Required]
    public UniqueNumberService $uniqueNumberService;

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
        else { //if ($transportRequestType === TransportRequest::DISCR_COLLECT)
            $categoryType = CategoryType::COLLECT_TRANSPORT_REQUEST;
            $transportRequest = new TransportCollectRequest();
        }

        $type = $typeRepository->findOneByCategoryLabel($categoryType, $typeStr);
        if (!isset($type)) {
            throw new FormException("Veuillez sélectionner un type pour votre demande de transport");
        }

        $number = $this->uniqueNumberService->create(
            $entityManager,
            TransportRequest::NUMBER_PREFIX,
            TransportRequest::class,
            UniqueNumberService::DATE_COUNTER_FORMAT_TRANSPORT_REQUEST
        );

        $expectedAt = FormatHelper::parseDatetime($expectedAtStr);

        if (!$expectedAt) {
            throw new FormException("Le format de date est invalide");
        }

        $transportRequest
            ->setType($type)
            ->setNumber($number)
            ->setExpectedAt($expectedAt)
            ->setCreatedAt(new DateTime())
            ->setCreatedBy($user);

        $contact = $transportRequest->getContact();
        $contact
            ->setName($request->request->get('contactName'))
            ->setFileNumber($request->request->get('contactFileNumber'))
            ->setContact($request->request->get('contactContact'))
            ->setAddress($request->request->get('contactAddress'))
            ->setPersonToContact($request->request->get('contactPersonToContact'))
            ->setObservation($request->request->get('contactObservation'));

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
                else { //if ($transportRequestType === TransportRequest::DISCR_COLLECT)
                    $line = new TransportCollectRequestNature();
                    $line->setQuantityToCollect($quantity);
                    $transportRequest->addTransportCollectRequestNature($line);
                }

                $line->setNature($nature);

                $entityManager->persist($line);
            }
        }

        $entityManager->persist($transportRequest);

        return $transportRequest;
    }
}
