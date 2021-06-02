<?php


namespace App\Service\IOT;

use App\Entity\IOT\SensorMessage;
use App\Entity\IOT\SensorWrapper;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;

class SensorWrapperService
{
    /** @Required */
    public EntityManagerInterface $em;

    public function getDataForDatatable($params = null)
    {
        $queryResult = $this->em->getRepository(SensorWrapper::class)->findByParams($params);

        $sensorWrappers = $queryResult['data'];

        $rows = [];
        foreach ($sensorWrappers as $sensorWrapper) {
            $rows[] = $this->dataRowSensorWrapper($sensorWrapper);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowSensorWrapper(SensorWrapper $sensorWrapper) {
        /** @var SensorMessage $lastLift */
        $lastLift = $sensorWrapper->getSensor() ? $sensorWrapper->getSensor()->getLastMessage() : null;

        $sensor = $sensorWrapper->getSensor();

        return [
            'id' => $sensorWrapper->getId(),
            'type' => $sensor ? FormatHelper::type($sensorWrapper->getSensor()->getType()) : '',
            'profile' => $sensor && $sensor->getProfile() ? $sensor->getProfile()->getName() : '',
            'name' => $sensorWrapper->getName() ?? '',
            'code' => $sensor ? $sensor->getCode() : '',
            'lastLift' => $lastLift ? FormatHelper::datetime($lastLift->getDate()) : '',
            'batteryLevel' => $sensor ? ($sensor->getBatteryLevel() . '%') : '',
            'manager' => FormatHelper::user($sensorWrapper->getManager()),
            'actions' => $this->templating->render('iot/sensor_wrapper/actions.html.twig', [
                'sensor_wrapper' => $sensorWrapper,
            ]),
        ];
    }
}
