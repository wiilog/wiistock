<?php


namespace App\Service\IOT;

use App\Entity\IOT\Pairing;
use App\Entity\IOT\Sensor;
use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;

class PairingService
{
    /** @Required */
    public EntityManagerInterface $em;

    public function getDataForDatatable(Sensor $sensor, $params = null)
    {
        $queryResult = $this->em->getRepository(Pairing::class)->findByParams($params, $sensor);

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
            'id' => $pairing->getId(),
            'element' => (string) $element,
            'start' => FormatHelper::datetime($pairing->getStart()),
            'end' => FormatHelper::datetime($pairing->getEnd()),
        ];
    }
}
