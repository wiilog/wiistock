<?php

namespace App\Repository\PreparationOrder;

use App\Entity\Pack;
use App\Entity\PreparationOrder\Preparation;
use Doctrine\ORM\EntityRepository;

class PreparationOrderArticleLineRepository extends EntityRepository {

    public function isOngoingAndUsingPack(Pack $pack): bool {
        return $this->createQueryBuilder('line')
            ->select("COUNT(line)")
            ->leftJoin('line.pack', 'pack')
            ->leftJoin('line.preparation', 'preparation')
            ->leftJoin('preparation.statut', 'status')
            ->andWhere('pack.id = :packId')
            ->andWhere('status.code IN (:statuses)')
            ->setParameters([
                'packId' => $pack->getId(),
                'statuses' => [Preparation::STATUT_A_TRAITER, Preparation::STATUT_EN_COURS_DE_PREPARATION]
            ])
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
