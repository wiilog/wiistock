<?php


namespace App\Service\IOT;

use App\Entity\IOT\SensorMessage;
use App\Entity\IOT\SensorWrapper;
use App\Service\FormatService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment;

class SensorWrapperService
{
    #[Required]
    public Environment $templating;

    #[Required]
    public EntityManagerInterface $em;

    #[Required]
    public FormatService $formatService;

    public function getDataForDatatable($params = null): array {
        $sensorWrapperRepository = $this->em->getRepository(SensorWrapper::class);
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

    public function dataRowSensorWrapper(SensorWrapper $sensorWrapper): array {
        /** @var SensorMessage $lastLift */
        $lastLift = $sensorWrapper->getSensor() ? $sensorWrapper->getSensor()->getLastMessage() : null;

        $sensor = $sensorWrapper->getSensor();
        return [
            'id' => $sensorWrapper->getId(),
            'type' => $sensor ? $this->formatService->type($sensor->getType()) : '',
            'profile' => $sensor && $sensor->getProfile() ? $sensor->getProfile()->getName() : '',
            'name' => $sensorWrapper->getName() ?? '',
            'code' => $sensor ? $sensor->getCode() : '',
            'cloverMac' => $sensor?->getCloverMac() ?? '',
            'lastLift' => $lastLift ? $this->formatService->datetime($lastLift->getDate()) : '',
            'battery' => $sensor ? ($sensor->getBattery() === -1 ? 'Inconnu (regarder sur l\'objet)' : $sensor->getBattery() . '%') : '',
            'manager' => $this->formatService->user($sensorWrapper->getManager()),
            'actions' => $this->templating->render('IOT/sensor_wrapper/actions.html.twig', [
                'sensor_wrapper' => $sensorWrapper,
            ]),
        ];
    }
}
