<?php

namespace App\Service;

use App\Entity\FiltreSup;
use App\Entity\Group;
use App\Entity\Pack;
use App\Entity\TrackingMovement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Twig\Environment;

class GroupService {

    /** @Required */
    public EntityManagerInterface $manager;

    /** @Required */
    public Security $security;

    /** @Required */
    public Environment $template;

    public function getDataForDatatable($params = null) {
        $filtreSupRepository = $this->manager->getRepository(FiltreSup::class);
        $groupRepository = $this->manager->getRepository(Group::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PACK, $this->security->getUser());
        $queryResult = $groupRepository->findByParamsAndFilters($params, $filters);

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

    public function dataRowGroup(Group $group) {
        return [
            "actions" => $this->template->render('group/table/actions.html.twig', [
                "group" => $group
            ]),
            "details" => $this->template->render("group/table/details.html.twig", [
                "group" => $group,
                "last_movement" => $group->getLastTracking(),
            ]),
        ];
    }

}
