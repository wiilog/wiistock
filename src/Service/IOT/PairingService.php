<?php


namespace App\Service\IOT;

use App\Entity\IOT\Pairing;
use App\Entity\IOT\SensorWrapper;
use App\Entity\IOT\SensorMessage;
use App\Helper\FormatHelper;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;

class PairingService
{
    /** @Required */
    public EntityManagerInterface $entityManager;

    /** @Required */
    public Twig_Environment $twigEnvironment;

    public function getDataForDatatable(SensorWrapper $wrapper, $params = null)
    {
        $pairingRepository = $this->entityManager->getRepository(Pairing::class);
        $queryResult = $pairingRepository->findByParams($params, $wrapper);

        $pairings = $queryResult['data'];

        $rows = [];
        foreach ($pairings as $pairing) {
            $rows[] = $this->dataRowPairing($pairing);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowPairing(Pairing $pairing) {
        $element = $pairing->getEntity();

        return [
            'actions' => $this->twigEnvironment->render('IOT/sensors_pairing/actions.html.twig', [
                "entity_info" => [
                    "id" => $pairing->getEntity()->getId(),
                    "type" => IOTService::getEntityCodeFromEntity($pairing->getEntity()),
                ],
            ]),
            'id' => $pairing->getId(),
            'element' => (string) $element,
            'start' => FormatHelper::datetime($pairing->getStart()),
            'end' => FormatHelper::datetime($pairing->getEnd()),
        ];
    }

    public function buildChartDataFromMessages(array $associatedMessages) {
        $data = ["colors" => []];
        /** @var SensorMessage $message */
        foreach ($associatedMessages as $message) {
            $date = $message->getDate();
            $sensor = $message->getSensor();

            $wrapper = $sensor->getAvailableSensorWrapper();
            $sensorCode = ($wrapper ? $wrapper->getName() . ' : ' : '') . $sensor->getCode();
            if(!isset($data['colors'][$sensorCode])) {
                srand($sensor->getId());
                $data['colors'][$sensorCode] = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
            }

            $dateStr = $date->format('d/m/Y H:i:s');
            if (!isset($data[$dateStr])) {
                $data[$dateStr] = [];
            }
            $data[$dateStr][$sensorCode] = floatval($message->getContent());
        }
        srand();
        return $data;
    }

    public function createPairing($end, SensorWrapper $sensorWrapper,  $article,  $location, $locationGroup, $pack){
        $pairing = new Pairing();
        if (!empty($end)){
            $endPairing = new DateTime($end);
            $pairing->setEnd($endPairing);
        }
        $start =  new DateTime("now");
        $pairing
            ->setStart($start)
            ->setSensorWrapper($sensorWrapper)
            ->setArticle($article)
            ->setLocationGroup($locationGroup)
            ->setLocation($location)
            ->setPack($pack)
            ->setActive(true);

        return $pairing;
    }
}
