<?php

namespace App\Service;

use App\Entity\Emplacement;
use App\Entity\LocationGroup;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;

class LocationGroupService {

    /** @Required */
    public EntityManagerInterface $manager;

    /** @Required */
    public Environment $template;

    public function getDataForDatatable($params = null) {
        $packRepository = $this->manager->getRepository(LocationGroup::class);

        $queryResult = $packRepository->findByParamsAndFilters($params);

        $packs = $queryResult['data'];

        $rows = [];
        foreach ($packs as $pack) {
            $rows[] = $this->dataRowGroup($pack);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    public function dataRowGroup(LocationGroup $group) {

        $groupLastMessage = $group->getLastMessage();

        $locationLastMessage = null;
        foreach ($group->getLocations() as $location) {
            if ($location->getLastMessage()) {
                $locationLastMessage = $location->getLastMessage();
                break;
            }
        }

        $sensorCode = $groupLastMessage
            ? $groupLastMessage->getSensor()->getCode()
            : ($locationLastMessage
                ? $locationLastMessage->getSensor()->getCode()
                : null);

        $hasPairing = !$group->getPairings()->isEmpty();
        if (!$hasPairing) {
            foreach ($group->getLocations() as $location) {
                if (!$location->getPairings()->isEmpty()) {
                    $hasPairing = true;
                    break;
                }
            }
        }

        return [
            "actions" => $this->template->render('location_group/actions.html.twig', [
                "group" => $group,
                "hasPairing" => $hasPairing
            ]),
            'pairing' => $this->template->render('pairing-icon.html.twig', [
                'sensorCode' => $sensorCode,
                'hasPairing' => $hasPairing
            ]),
            "name" => $group->getName(),
            "description" => $group->getDescription(),
            "active" => $group->isActive() ? "Actif" : "Inactif",
            "locations" => $group->getLocations()->count(),
        ];
    }

}
