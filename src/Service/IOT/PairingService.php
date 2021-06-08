<?php


namespace App\Service\IOT;

use App\Entity\IOT\Pairing;
use App\Entity\IOT\Sensor;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;

class PairingService
{
    /** @Required */
    public EntityManagerInterface $entityManager;

    /** @Required */
    public Twig_Environment $twigEnvironment;

    public function getDataForDatatable(Sensor $sensor, $params = null)
    {
        $pairingRepository = $this->entityManager->getRepository(Pairing::class);
        $queryResult = $pairingRepository->findByParams($params, $sensor);

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
                'pairingId' => $pairing->getId()
            ]),
            'id' => $pairing->getId(),
            'element' => (string) $element,
            'start' => FormatHelper::datetime($pairing->getStart()),
            'end' => FormatHelper::datetime($pairing->getEnd()),
        ];
    }
}
