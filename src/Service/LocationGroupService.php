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
        $locationLastMessage = $group->getLocations()->filter(fn(Emplacement $location) => $location->getLastMessage())->first();

        $sensorCode = $groupLastMessage
            ? $groupLastMessage->getSensor()->getCode()
            : ($locationLastMessage
                ? $locationLastMessage->getSensor()->getCode()
                : null);

        return [
            "actions" => $this->template->render('location_group/actions.html.twig', [
                "group" => $group,
                "locationLastMessage" => $locationLastMessage
            ]),
            'pairing' => $this->template->render('pairing-icon.html.twig', [
                'sensorCode' => $sensorCode
            ]),
            "name" => $group->getName(),
            "description" => $group->getDescription(),
            "active" => $group->isActive() ? "Actif" : "Inactif",
            "locations" => $group->getLocations()->count(),
        ];
    }

}
