<?php

namespace App\Service;

use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Pack;
use App\Entity\TrackingMovement;
use App\Entity\Utilisateur;
use App\Repository\PackRepository;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Twig\Environment as Twig_Environment;

class GroupService {

    /** @Required */
    public EntityManagerInterface $manager;

    /** @Required */
    public PackService $packService;

    /** @Required */
    public Twig_Environment $template;

    /** @Required */
    public Security $security;

    /** @Required */
    public TrackingMovementService $trackingMovementService;

    public function createParentPack(array $options = []): Pack {
        $group = $this->packService->createPackWithCode($options['parent']);
        $group
            ->setComment($options['comment'] ?? '')
            ->setGroupIteration(1)
            ->setNature($options['nature'] ?? null)
            ->setVolume($options['volume'] ?? 0)
            ->setWeight($options['weight'] ?? 0);
        return $group;
    }

    public function getDataForDatatable($params = null) {
        $filtreSupRepository = $this->manager->getRepository(FiltreSup::class);
        $packRepository = $this->manager->getRepository(Pack::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PACK, $this->security->getUser());
        $queryResult = $packRepository->findByParamsAndFilters($params, $filters, PackRepository::GROUPS_MODE);

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

    public function dataRowGroup(Pack $pack) {
        return [
            "actions" => $this->template->render('group/table/actions.html.twig', [
                "group" => $pack
            ]),
            "details" => $this->template->render("group/table/details.html.twig", [
                "group" => $pack,
                "last_movement" => $pack->getLastTracking(),
            ]),
        ];
    }

    public function ungroup(EntityManagerInterface $manager,
                            Pack $parent,
                            Emplacement $destination,
                            ?Utilisateur $user = null,
                            ?DateTime $date = null) {
        /** @var Pack[] $children */
        $children = $parent->getChildren()->toArray();
        foreach ($children as $pack) {
            $pack->setParent(null);

            $deposit = $this->trackingMovementService->createTrackingMovement(
                $pack,
                $destination,
                $user ?? $this->security->getUser(),
                $date ?? new DateTime("now", new DateTimeZone("Europe/Paris")),
                false,
                null,
                TrackingMovement::TYPE_DEPOSE,
                ["parent" => $parent]
            );

            $ungroup = $this->trackingMovementService->createTrackingMovement(
                $pack,
                $destination,
                $user ?? $this->security->getUser(),
                $date ?? new DateTime("now", new DateTimeZone("Europe/Paris")),
                false,
                null,
                TrackingMovement::TYPE_UNGROUP,
                ["parent" => $parent]
            );
            $manager->persist($deposit);
            $manager->persist($ungroup);
        }
    }

}
