<?php


namespace App\Service;

use App\Entity\IOT\SensorMessage;
use App\Entity\IOT\SensorWrapper;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as Twig_Environment;

class SensorWrapperService
{
    /** @Required */
    public Twig_Environment $templating;

    /** @Required */
    public RouterInterface $router;

    /** @Required */
    public EntityManagerInterface $em;

    /** @Required */
    public UniqueNumberService $uniqueNumberService;

    public MailerService $mailerService;

    public function __construct(MailerService $mailerService) {
        $this->mailerService = $mailerService;
    }

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
            'actions' => $this->templating->render('sensor_wrapper/actions.html.twig', [
                'sensor_wrapper' => $sensorWrapper,
            ]),
        ];
    }
}
