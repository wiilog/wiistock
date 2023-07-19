<?php


namespace App\Service;

use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;

class SensorMessageService
{
    #[Required]
    public FormatService $formatService;

    public function getDataForDatatable(EntityManagerInterface $entityManager, Sensor $sensor, $params = null):array {
        $sensorMessageRepository = $entityManager->getRepository(SensorMessage::class);
        $queryResult = $sensorMessageRepository->findByParamsAndFilters($params, $sensor);

        $sensorMessages = $queryResult['data'];

        $rows = [];
        foreach ($sensorMessages as $sensorMessage) {
            $rows[] = $this->dataRowSensorMessage($sensorMessage);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowSensorMessage(SensorMessage $sensorMessage):array {
        return [
            'id' => $sensorMessage->getId(),
            'date' => $this->formatService->datetime($sensorMessage->getDate()),
            'content' => $this->formatService->messageContent($sensorMessage),
            'contentType' => $this->formatService->messageContentType($sensorMessage),
            'event' => $sensorMessage->getEvent() ?? ""
        ];
    }
}
