<?php

namespace App\Service\Transport;


use App\Entity\CategorieStatut;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorMessage;
use App\Entity\Statut;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
use App\Entity\Transport\TransportRequestLine;
use App\Entity\Transport\TransportRound;
use App\Entity\Transport\TransportRoundLine;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use App\Service\CSVExportService;
use App\Service\FormatService;
use App\Service\GeoService;
use App\Service\OperationHistoryService;
use App\Service\TranslationService;
use DateTime;
use App\Service\StatusHistoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;


class TransportRoundService
{

    #[Required]
    public TransportService $transportService;

    #[Required]
    public StatusHistoryService $statusHistoryService;

    #[Required]
    public OperationHistoryService $operationHistoryService;

    #[Required]
    public CSVExportService $CSVExportService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public GeoService $geoService;

    #[Required]
    public TranslationService $translation;

    private array $cacheStatuses = [];

    /**
     * For csv export on transport round list page
     */
    public function putLineRoundAndOrder($output, CSVExportService $csvService, TransportRound $round): void {
        $roundStatuses = $round->getLastStatusHistory([
            TransportRound::STATUS_AWAITING_DELIVERER,
            TransportRound::STATUS_ONGOING,
            TransportRound::STATUS_FINISHED,
        ]);

        $vehicle = $round->getVehicle() ?? $round->getDeliverer()?->getVehicle();

        $transportRoundLines = $round->getTransportRoundLines();

        $realTime = null;
        if ($round->getBeganAt() && $round->getEndedAt()) {
            $realTimeDiff = $round->getEndedAt()->diff($round->getBeganAt());
            $realTimeDays = (int) $realTimeDiff->format("%a");
            $realTimeHours = (int) $realTimeDiff->format("%h");
            $realTimeMinutes = (int) $realTimeDiff->format("%i");
            $realTime = ($realTimeHours + ($realTimeDays * 24)) . ":" . $realTimeMinutes;
        }

        $dataRounds = [
            TransportRound::NUMBER_PREFIX . $round->getNumber(),
            FormatHelper::status($round->getStatus()),
            FormatHelper::date($round->getExpectedAt()),
            isset($roundStatuses[TransportRound::STATUS_AWAITING_DELIVERER]) ? FormatHelper::datetime($roundStatuses[TransportRound::STATUS_AWAITING_DELIVERER]) : '',
            isset($roundStatuses[TransportRound::STATUS_ONGOING]) ? FormatHelper::datetime($roundStatuses[TransportRound::STATUS_ONGOING]) : '',
            isset($roundStatuses[TransportRound::STATUS_FINISHED]) ? FormatHelper::datetime($roundStatuses[TransportRound::STATUS_FINISHED]) : '',
            $round->getEstimatedTime() ?: '',
            $realTime ?:'',
            $round->getEstimatedDistance() ?: '',
            $round->getRealDistance() ?: '',
            FormatHelper::user($round->getDeliverer()),
            $vehicle?->getRegistrationNumber() ?: '',
        ];

        if(!$transportRoundLines->isEmpty()) {
            /** @var TransportRoundLine $transportRoundLine */
            foreach ($transportRoundLines as $transportRoundLine) {
                $order = $transportRoundLine->getOrder();
                $request = $order?->getRequest();
                $ordersInformation = array_merge($dataRounds, [
                    $request?->getContact()?->getName() ?: '',
                    TransportRequest::NUMBER_PREFIX . $request?->getNumber(),
                    str_replace("\n", " ", $request?->getContact()?->getAddress() ?: ''),
                    $transportRoundLine->getPriority() ?: '',
                    $this->formatService->status($order?->getStatus()) ?: '',
                    FormatHelper::bool($order?->isThresholdExceeded(), 'non'),
                ]);
                $csvService->putLine($output, $ordersInformation);
            }
        }
        else {
            $csvService->putLine($output, $dataRounds);
        }

    }

    public function calculateRoundRealDistance(TransportRound $transportRound): float
    {
        $vehicle = $transportRound->getVehicle() ?? $transportRound->getDeliverer()?->getVehicle();

        if(!$transportRound->getEndedAt()) {
            $end = clone ($transportRound->getBeganAt() ?? new DateTime("now"));
            $end->setTime(23, 59);
        } else {
            $end = min((clone ($transportRound->getBeganAt()))->setTime(23, 59), $transportRound->getEndedAt());
        }

        $messages = $vehicle->getSensorMessagesBetween($transportRound->getBeganAt(), $end, [
            "sensorType" => Sensor::GPS
        ]);
        if (!empty($messages)) {
            $coordinates = $transportRound->getCoordinates();
            $initialDistance = $this->geoService->directArcgisQuery(
                [
                    [
                        'index' => 0,
                        'keep' => true,
                        'geometry' => [
                            'x' => $coordinates['startPoint']['longitude'],
                            'y' => $coordinates['startPoint']['latitude']
                        ]
                    ],
                    [
                        'index' => 1,
                        'keep' => true,
                        'geometry' => [
                            'x' => $coordinates['startPointScheduleCalculation']['longitude'],
                            'y' => $coordinates['startPointScheduleCalculation']['latitude']
                        ]
                    ]
                ]
            );
            $sensorCoordinates = Stream::from($messages)
                ->map(function (SensorMessage $message) {
                    $content = $message->getContent();
                    if ($content && $content !== '-1,-1') {
                        $coordinates = Stream::explode(',', $content)
                            ->filter()
                            ->map(fn($coordinate) => floatval($coordinate))
                            ->toArray();

                        return [
                            'latitude' => $coordinates[0],
                            'longitude' => $coordinates[1],
                        ];
                    }
                    return null;
                })
                ->filter();

            return ($initialDistance['distance'] * 1000) + $this->geoService->getDistanceBetween($sensorCoordinates->toArray());
        }

        return 0.0;
    }

    public function putLineRoundAndRequest($output,
                                           TransportRound $round,
                                           callable $filter = null): void {
        $vehicle = $round->getVehicle() ?? $round->getDeliverer()?->getVehicle();
        $lines = $round->getTransportRoundLines();

        $roundExportable = (
            !$filter
            || Stream::from($lines)->some(fn(TransportRoundLine $line) => $filter($line))
        );

        if($roundExportable) {
            $dataRounds = [
                TransportRound::NUMBER_PREFIX . $round->getNumber(),
                FormatHelper::date($round->getExpectedAt()),
            ];

            if (!$lines->isEmpty()) {
                /** @var TransportRoundLine $line */
                foreach ($lines as $line) {
                    $order = $line->getOrder() ?: null;

                    if (!$filter || $filter($line)) {
                        $request = $order->getRequest() ?: null;
                        $statusRequest = $request->getLastStatusHistory([TransportRequest::STATUS_FINISHED]);

                        $naturesStr = Stream::from($request->getLines() ?: [])
                            ->filterMap(fn(TransportRequestLine $line) => $this->formatService->nature($line->getNature()))
                            ->unique()
                            ->join(', ');

                        $ordersInformation = array_merge($dataRounds, [
                            $request instanceof TransportDeliveryRequest ? ($request->getCollect() ? $this->translation->translate("Demande", "Livraison", "Livraison", false) . " - Collecte" : $this->translation->translate("Demande", "Livraison", "Livraison", false)) : "Collecte",
                            FormatHelper::user($round->getDeliverer()),
                            $vehicle?->getRegistrationNumber() ?: '',
                            $round->getRealDistance() ?: '',
                            $request->getContact()?->getFileNumber() ?: '',
                            TransportRequest::NUMBER_PREFIX . $request->getNumber(),
                            str_replace("\n", " ", $line->getOrder()?->getRequest()?->getContact()?->getAddress() ?: ''),
                            $request->getContact()->getAddress() ? FormatHelper::bool($this->transportService->isMetropolis($request->getContact()->getAddress())) : '',
                            $line->getPriority() ?: '',
                            $request instanceof TransportDeliveryRequest ? FormatHelper::bool(!empty($request->getEmergency())) : '',
                            FormatHelper::datetime($request->getCreatedAt()),
                            FormatHelper::user($request->getCreatedBy()),
                            $request instanceof TransportDeliveryRequest ? FormatHelper::datetime($request->getExpectedAt()) : FormatHelper::date($request->getExpectedAt()),
                            isset($statusRequest[TransportRequest::STATUS_FINISHED]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_FINISHED]) : '',
                            $naturesStr,
                            FormatHelper::bool($order->isThresholdExceeded(), 'non'),
                        ]);
                        $this->CSVExportService->putLine($output, $ordersInformation);
                    }
                }
            } else if (!$filter) {
                $this->CSVExportService->putLine($output, $dataRounds);
            }
        }
    }
    public function getHeaderRoundAndRequestExport(): array {
        return [
            'N° Tournée',
            'Date tournée',
            'Transport',
            'Livreur',
            'Immatriculation',
            'Kilomètres',
            'N° dossier patient',
            'N° Demande',
            'Adresse transport',
            'Métropole',
            'Numéro dans la tournée',
            'Urgence',
            'Date de création',
            'Demandeur',
            'Date demandée',
            'Date demande terminée',
            'Objets',
            'Anomalie température',
        ];
    }

    public function rejectTransportRoundDeliveryLine(EntityManagerInterface $entityManager,
                                                     TransportRoundLine     $line,
                                                     Utilisateur            $user): void {
        $order = $line->getOrder();
        $request = $order->getRequest();

        if ($request instanceof TransportCollectRequest) {
            return;
        }

        if($request->getStatus()->getCode() !== TransportRequest::STATUS_TO_PREPARE) {
            $this->reprepareTransportRoundDeliveryLine($entityManager, $line, $user);
        }

        $this->operationHistoryService->persistTransportHistory($entityManager, $request, OperationHistoryService::TYPE_REJECTED_DELIVERY, [
            "user" => $user,
        ]);

        $this->operationHistoryService->persistTransportHistory($entityManager, $order, OperationHistoryService::TYPE_REJECTED_DELIVERY, [
            "user" => $user,
        ]);

        $round = $line->getTransportRound();
        $round->removeTransportRoundLine($line);
        $entityManager->remove($line);

        $line->setRejectedAt(new DateTime());

        $round->setRejectedOrderCount($round->getRejectedOrderCount() + 1);
    }

    public function reprepareTransportRoundDeliveryLine(EntityManagerInterface $entityManager, TransportRoundLine $line, Utilisateur $user): void {
        $order = $line->getOrder();
        $request = $order->getRequest();

        if ($request instanceof TransportCollectRequest) {
            return;
        }

        $statusRepository = $entityManager->getRepository(Statut::class);

        $deliveryRequestToPrepare = $this->cacheStatuses['deliveryRequestToPrepare']
            ?? $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_REQUEST_DELIVERY, TransportRequest::STATUS_TO_PREPARE);

        $deliveryOrderToAssign = $this->cacheStatuses['deliveryOrderToAssign']
            ?? $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSPORT_ORDER_DELIVERY, TransportOrder::STATUS_TO_ASSIGN);

        $this->cacheStatuses['deliveryRequestToPrepare'] = $deliveryRequestToPrepare;
        $this->cacheStatuses['deliveryOrderToAssign'] = $deliveryOrderToAssign;

        $this->statusHistoryService->updateStatus($entityManager, $request, $deliveryRequestToPrepare, [
            'forceCreation' => false,
            'initiatedBy' => $user,
        ]);
        $this->statusHistoryService->updateStatus($entityManager, $order, $deliveryOrderToAssign, [
            'initiatedBy' => $user,
        ]);

        if($request instanceof TransportDeliveryRequest && $request->getCollect()) {
            $collect = $request->getCollect();
            $collect->setStatus($deliveryRequestToPrepare);
        }
    }

    public function updateTransportRoundLinePriority(TransportRound $round): void {
        $priority = 1;
        foreach ($round->getTransportRoundLines() as $line) {
            $line->setPriority($priority);
            $priority++;
        }
    }

}
