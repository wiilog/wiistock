<?php

namespace App\Service\Transport;


use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportRequest;
use App\Entity\Transport\TransportRound;
use App\Entity\Transport\Vehicle;
use App\Helper\FormatHelper;
use App\Service\CSVExportService;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;
use function PHPUnit\Framework\isEmpty;


class TransportRoundService
{

    #[Required]
    public TransportService $transportService;


    public function putLineRounds($output, CSVExportService $csvService, TransportRound $round): void
    {
        $statusCode = [
            TransportRound::STATUS_AWAITING_DELIVERER,
            TransportRound::STATUS_ONGOING,
            TransportRound::STATUS_FINISHED
        ];
        $statusRound = $round->getLastStatusHistory($statusCode);

        /** @var Vehicle $vehicle */
        $vehicle = $round->getDeliverer()?->getVehicles()?->first();

        $transportRoundLines = $round->getTransportRoundLines();

        $realTime = null;
        if ( $round->getBeganAt() != null & $round->getEndedAt() != null  ) {
            $realTimeDif = $round->getEndedAt()->diff($round->getBeganAt());
            $realTimeJ = $realTimeDif->format("%a");
            $realTime = $realTimeDif->format("%h") + ($realTimeJ * 24) . ":" . $realTimeDif->format(" %i");
        }

        $dataRounds = [
            $round->getNumber(),
            $round->getStatus() ?: '',
            FormatHelper::date($round->getExpectedAt()),
            isset($statusRound[TransportRound::STATUS_AWAITING_DELIVERER]) ? FormatHelper::datetime($statusRound[TransportRound::STATUS_AWAITING_DELIVERER]) : '',
            isset($statusRound[TransportRound::STATUS_ONGOING]) ? FormatHelper::datetime($statusRound[TransportRound::STATUS_ONGOING]) : '',
            isset($statusRound[TransportRound::STATUS_FINISHED]) ? FormatHelper::datetime($statusRound[TransportRound::STATUS_FINISHED]) : '',
            $round->getEstimatedTime()?:'',
            $realTime ?:'',
            $round->getEstimatedDistance()?:'',
            $round->getRealDistance()?:'',
            $round->getDeliverer()?:'',
            $vehicle->getRegistrationNumber()?:'',
        ];

        if(!$transportRoundLines->isEmpty())
            foreach ($transportRoundLines as $transportRoundLine) {
                $ordersInformation = array_merge($dataRounds, [
                    $transportRoundLine->getOrder()?->getRequest()?->getContact()?->getName()?:'',
                    $transportRoundLine->getOrder()?->getRequest()?->getNumber()?:'',
                    str_replace("\n", " ", $transportRoundLine->getOrder()?->getRequest()?->getContact()?->getAddress()?:''),
                    $transportRoundLine->getPriority()?:'',
                    $transportRoundLine->getOrder()?->getStatus()?:'',
                    $vehicle->getActivePairing() ? FormatHelper::bool( $vehicle->getActivePairing()->hasExceededThreshold()): "Non",
                    ]);
            $csvService->putLine($output, $ordersInformation);
        }
        else {
            $csvService->putLine($output, $dataRounds);
        }

    }

    public function putRoundsLineParameters($output, CSVExportService $csvService, TransportRound $round): void
    {
        $statusCode = [
            TransportRequest::STATUS_FINISHED
        ];

        /** @var Vehicle $vehicle */
        $vehicle = $round->getDeliverer()?->getVehicles()?->first();

        $transportRoundLines = $round->getTransportRoundLines();

        $dataRounds = [
            $round->getNumber(),
            FormatHelper::date($round->getExpectedAt()),
        ];

        if (!$transportRoundLines->isEmpty()) {
            foreach ($transportRoundLines as $transportRoundLine) {
                $request = $transportRoundLine->getOrder()?->getRequest() ?: null;
                $statusRequest = $request->getLastStatusHistory($statusCode);
                $natures = [];
                $packs = $request->getOrder()?->getPacks();
                if ($packs && !$packs->isEmpty()) {
                    foreach ($packs as $pack) {
                        $nature = $pack->getPack()->getNature()->getLabel();
                        $natures[] = $nature;
                    }
                }
                dump($natures);
                $naturesStr = Stream::from($natures)->unique()->join(', ' );


                $ordersInformation = array_merge($dataRounds, [
                    $request instanceof TransportDeliveryRequest ? ($request->getCollect() ? "Livraison-Collecte" : "Livraison") : "Collecte",
                    FormatHelper::user($round->getDeliverer()),
                    $vehicle->getRegistrationNumber() ?: '',
                    $round->getRealDistance() ?: '',
                    $request->getContact()?->getFileNumber() ?: '',
                    $request->getNumber() ?: '',
                    str_replace("\n", " ", $transportRoundLine->getOrder()?->getRequest()?->getContact()?->getAddress() ?: ''),
                    $request->getContact()->getAddress() ? FormatHelper::bool($this->transportService->isMetropolis($request->getContact()->getAddress())) : '',
                    $transportRoundLine->getPriority() ?: '',
                    $request instanceof TransportDeliveryRequest ? FormatHelper::bool(!empty($request->getEmergency())) :'',
                    FormatHelper::datetime($request->getCreatedAt()),
                    FormatHelper::user($request->getCreatedBy()),
                    FormatHelper::datetime($request->getExpectedAt()),
                    isset($statusRequest[TransportRequest::STATUS_FINISHED]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_FINISHED]) : '',
                    $naturesStr,
                    $vehicle->getActivePairing() ? FormatHelper::bool($vehicle->getActivePairing()->hasExceededThreshold()) : "Non",


                ]);
                $csvService->putLine($output, $ordersInformation);
            }
        }
        else {
                $csvService->putLine($output, $dataRounds);
            }
        }

}
