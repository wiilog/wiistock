<?php


namespace App\Service;

use App\Entity\IOT\SensorMessage;
use App\Entity\IOT\SensorWrapper;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;

class SensorWrapperService
{
    /** @Required */
    public Twig_Environment $templating;

    /** @Required */
    public EntityManagerInterface $entityManager;

    public function getDataForDatatable($params = null)
    {
        $sensorWrapperRepository = $this->entityManager->getRepository(SensorWrapper::class);
        $queryResult = $sensorWrapperRepository->findByParams($params);

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
            'type' => $sensor ? $sensor->getType() : '',
            'profile' => $sensor && $sensor->getProfile() ? $sensor->getProfile()->getName() : '',
            'name' => $sensorWrapper->getName() ?? '',
            'code' => $sensor ? $sensor->getCode() : '',
            'lastLift' => $lastLift ? FormatHelper::datetime($lastLift->getDate()) : '',
            'batteryLevel' => $sensor ? ($sensor->getBatteryLevel() . '%') : '',
            'manager' => FormatHelper::user($sensorWrapper->getManager()),
            'actions' => $this->templating->render('sensor_wrapper/actions.html.twig', [
                'sensor_wrapper' => $sensorWrapper,
            ]),
        ];
    }
}
