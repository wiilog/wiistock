<?php

namespace App\Repository;

use App\Entity\VisibilityGroup;
use Doctrine\ORM\EntityRepository;

/**
 * @method VisibilityGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method VisibilityGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method VisibilityGroup[]    findAll()
 * @method VisibilityGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VisibilityGroupRepository extends EntityRepository {
    public function getForSelect(?string $term) {
        return $this->createQueryBuilder("visibility_group")
            ->select("visibility_group.id AS id, visibility_group.label AS text")
            ->where("visibility_group.label LIKE :term")
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getArrayResult();
    }
}
