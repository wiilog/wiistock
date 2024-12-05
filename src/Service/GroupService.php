<?php

namespace App\Service;

use App\Entity\Emplacement;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class GroupService {
    public function __construct(
        private TrackingMovementService $trackingMovementService,
        private Security                $security,
        private PackService             $packService,
    ) {}

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

    public function ungroup(EntityManagerInterface $manager,
                            Pack $parent,
                            Emplacement $destination,
                            ?Utilisateur $user = null,
                            ?DateTime $date = null) {
        /** @var Pack[] $children */
        $children = $parent->getContent()->toArray();
        foreach ($children as $pack) {
            $pack->setGroup(null);

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
