<?php

namespace App\Service;

use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\LocationGroup;
use App\Entity\Pack;
use App\Entity\TrackingMovement;
use App\Entity\Utilisateur;
use App\Repository\PackRepository;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
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
        return [
            "actions" => $this->template->render('location_group/actions.html.twig', [
                "group" => $group
            ]),
            "name" => $group->getName(),
            "description" => $group->getDescription(),
            "active" => "Actif",
            "locations" => $group->getLocations()->count(),
        ];
    }

}
