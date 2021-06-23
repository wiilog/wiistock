<?php


namespace App\Service;

use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorMessage;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;

class SensorMessageService
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getDataForDatatable(Sensor $sensor, $params = null):array {

        $queryResult = $this->entityManager->getRepository(SensorMessage::class)->findByParamsAndFilters($params, $sensor);

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
            'date' => FormatHelper::datetime($sensorMessage->getDate()),
            'content' => FormatHelper::messageContent($sensorMessage),
            'event' => $sensorMessage->getEvent() ?? ""
        ];
    }
}
