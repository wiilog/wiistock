<?php

namespace App\Service\Transport;


use App\Entity\Transport\TransportRound;
use App\Entity\Transport\Vehicle;
use App\Helper\FormatHelper;
use App\Service\CSVExportService;


class TransportRoundService
{
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
}
