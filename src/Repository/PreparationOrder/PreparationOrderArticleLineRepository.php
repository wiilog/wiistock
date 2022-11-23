<?php

namespace App\Repository\PreparationOrder;

use App\Entity\Pack;
use Doctrine\ORM\EntityRepository;

class PreparationOrderArticleLineRepository extends EntityRepository {

    public function getPreparationOrderArticleLine(Pack $pack, array $statuses = []){
        return $this->createQueryBuilder('preparation_order_article_line')
            ->leftJoin('preparation_order_article_line.pack', 'pack')
            ->leftJoin('preparation_order_article_line.preparation', 'preparation')
            ->leftJoin('preparation.statut', 'status')
            ->andWhere('pack.id = :packId')
            ->andWhere('status.code IN (:statuses)')
            ->setParameters([
                'packId' => $pack->getId(),
                'statuses' => $statuses
            ])
            ->getQuery()
            ->getResult();
    }
}
