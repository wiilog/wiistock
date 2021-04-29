<?php

namespace App\Service;

use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Group;
use App\Entity\TrackingMovement;
use App\Entity\Utilisateur;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Twig\Environment as Twig_Environment;

class GroupService {

    /** @Required */
    public EntityManagerInterface $manager;

    /** @Required */
    public Twig_Environment $template;

    /** @Required */
    public Security $security;

    /** @Required */
    public TrackingMovementService $trackingMovementService;

    public function createGroup(array $options = []): Group {
        $group = $this->createGroupWithCode($options['group']);
        $group
            ->setComment($options['comment'] ?? '')
            ->setIteration(1)
            ->setNature($options['nature'] ?? null)
            ->setVolume($options['volume'] ?? 0)
            ->setWeight($options['weight'] ?? 0);
        return $group;
    }

    public function createGroupWithCode(string $code): Group {
        $group = new Group();
        $group->setCode(str_replace("    ", " ", $code));
        return $group;
    }

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

    public function ungroup(EntityManagerInterface $manager, Group $group, Emplacement $destination, ?Utilisateur $user = null, ?DateTime $date = null) {
        foreach ($group->getPacks() as $pack) {
            $pack->setGroup(null);

            $deposit = $this->trackingMovementService->createTrackingMovement(
                $pack,
                $destination,
                $user ?? $this->security->getUser(),
                $date ?? new DateTime("now", new DateTimeZone("Europe/Paris")),
                false,
                null,
                TrackingMovement::TYPE_DEPOSE,
                ["group" => $group]
            );

            $ungroup = $this->trackingMovementService->createTrackingMovement(
                $pack,
                $destination,
                $user ?? $this->security->getUser(),
                $date ?? new DateTime("now", new DateTimeZone("Europe/Paris")),
                false,
                null,
                TrackingMovement::TYPE_UNGROUP,
                ["group" => $group]
            );

            $manager->persist($deposit);
            $manager->persist($ungroup);
        }
    }

}
