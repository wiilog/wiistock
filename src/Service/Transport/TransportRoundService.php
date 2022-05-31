<?php

namespace App\Service\Transport;


use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportRequest;
use App\Entity\Transport\TransportRequestLine;
use App\Entity\Transport\TransportRound;
use App\Helper\FormatHelper;
use App\Service\CSVExportService;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;


class TransportRoundService
{

    #[Required]
    public TransportService $transportService;

    /**
     * For csv export on transport round list page
     */
    public function putLineRoundAndOrder($output, CSVExportService $csvService, TransportRound $round): void {
        $roundStatuses = $round->getLastStatusHistory([
            TransportRound::STATUS_AWAITING_DELIVERER,
            TransportRound::STATUS_ONGOING,
            TransportRound::STATUS_FINISHED
        ]);

        $vehicle = $round->getDeliverer()?->getVehicle();

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
            foreach ($transportRoundLines as $transportRoundLine) {
                $order = $transportRoundLine->getOrder();
                $request = $order?->getRequest();
                $ordersInformation = array_merge($dataRounds, [
                    $request?->getContact()?->getName() ?: '',
                    TransportRequest::NUMBER_PREFIX . $request?->getNumber(),
                    str_replace("\n", " ", $request?->getContact()?->getAddress() ?: ''),
                    $transportRoundLine->getPriority() ?: '',
                    $order?->getStatus()->getNom() ?: '',
                    $vehicle?->getActivePairing() ? FormatHelper::bool($vehicle->getActivePairing()->hasExceededThreshold()) : "Non",
                ]);
                $csvService->putLine($output, $ordersInformation);
            }
        }
        else {
            $csvService->putLine($output, $dataRounds);
        }

    }


    public function putLineRoundAndRequest($output, CSVExportService $csvService, TransportRound $round): void {
        $vehicle = $round->getDeliverer()?->getVehicle();

        $transportRoundLines = $round->getTransportRoundLines();

        $dataRounds = [
            TransportRound::NUMBER_PREFIX . $round->getNumber(),
            FormatHelper::date($round->getExpectedAt()),
        ];

        if (!$transportRoundLines->isEmpty()) {
            foreach ($transportRoundLines as $transportRoundLine) {
                $request = $transportRoundLine->getOrder()?->getRequest() ?: null;
                $statusRequest = $request->getLastStatusHistory([TransportRequest::STATUS_FINISHED]);

                $naturesStr = Stream::from($request->getLines() ?: [])
                    ->filterMap(fn(TransportRequestLine $line) => $line->getNature()?->getLabel())
                    ->unique()
                    ->join(', ');

                $ordersInformation = array_merge($dataRounds, [
                    $request instanceof TransportDeliveryRequest ? ($request->getCollect() ? "Livraison - Collecte" : "Livraison") : "Collecte",
                    FormatHelper::user($round->getDeliverer()),
                    $vehicle?->getRegistrationNumber() ?: '',
                    $round->getRealDistance() ?: '',
                    $request->getContact()?->getFileNumber() ?: '',
                    TransportRequest::NUMBER_PREFIX . $request->getNumber(),
                    str_replace("\n", " ", $transportRoundLine->getOrder()?->getRequest()?->getContact()?->getAddress() ?: ''),
                    $request->getContact()->getAddress() ? FormatHelper::bool($this->transportService->isMetropolis($request->getContact()->getAddress())) : '',
                    $transportRoundLine->getPriority() ?: '',
                    $request instanceof TransportDeliveryRequest ? FormatHelper::bool(!empty($request->getEmergency())) :'',
                    FormatHelper::datetime($request->getCreatedAt()),
                    FormatHelper::user($request->getCreatedBy()),
                    $request instanceof TransportDeliveryRequest ? FormatHelper::datetime($request->getExpectedAt()) : FormatHelper::date($request->getExpectedAt()),
                    isset($statusRequest[TransportRequest::STATUS_FINISHED]) ? FormatHelper::datetime($statusRequest[TransportRequest::STATUS_FINISHED]) : '',
                    $naturesStr,
                    $vehicle?->getActivePairing() ? FormatHelper::bool($vehicle->getActivePairing()->hasExceededThreshold()) : "Non",
                ]);
                $csvService->putLine($output, $ordersInformation);
            }
        }
        else {
            $csvService->putLine($output, $dataRounds);
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

}
