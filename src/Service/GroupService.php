<?php

namespace App\Service;

use App\Entity\Emplacement;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;

class GroupService {

    #[Required]
    public EntityManagerInterface $manager;

    #[Required]
    public PackService $packService;

    #[Required]
    public Twig_Environment $template;

    #[Required]
    public Security $security;

    #[Required]
    public TrackingMovementService $trackingMovementService;

    #[Required]
    public FormatService $formatService;

    public function createParentPack(array $options = []): Pack {
        $group = $this->packService->createPackWithCode($options['parent']);
        $group
            ->setComment($options['comment'] ?? null)
            ->setGroupIteration(1)
            ->setNature($options['nature'] ?? null)
            ->setVolume($options['volume'] ?? 0)
            ->setWeight($options['weight'] ?? 0);
        return $group;
    }

    public function getDataForDatatable($params = null) {
        $filtreSupRepository = $this->manager->getRepository(FiltreSup::class);
        $packRepository = $this->manager->getRepository(Pack::class);

        $currentUser = $this->security->getUser();

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PACK, $currentUser);
        $queryResult = $packRepository->findByParamsAndFilters($params, $filters, PackRepository::GROUPS_MODE, [
            'fields' => $this->packService->getPackListColumnVisibleConfig($currentUser),
        ]);

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
                "last_movement" => $pack->getLastAction(),
                "formatter" => $this->formatService
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

            $ungroup = $this->trackingMovementService->createTrackingMovement(
                $pack,
                $destination,
                $user ?? $this->security->getUser(),
                $date ?? new DateTime("now"),
                false,
                null,
                TrackingMovement::TYPE_UNGROUP,
                [
                    'parent' => $parent,
                ]
            );

            $deposit = $this->trackingMovementService->createTrackingMovement(
                $pack,
                $destination,
                $user ?? $this->security->getUser(),
                $date ?? new DateTime("now"),
                false,
                null,
                TrackingMovement::TYPE_DEPOSE
            );
            $manager->persist($deposit);
            $manager->persist($ungroup);
        }
    }

}
