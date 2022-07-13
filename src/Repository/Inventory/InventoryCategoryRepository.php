<?php

namespace App\Repository\Inventory;

use App\Entity\Inventory\InventoryCategory;
use Doctrine\ORM\EntityRepository;

/**
 * @method InventoryCategory|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryCategory|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryCategory[]    findAll()
 * @method InventoryCategory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryCategoryRepository extends EntityRepository {

    public function countByLabel(?string $label): int {
        return $this->createQueryBuilder("category")
            ->select("COUNT(category)")
            ->andWhere("category.label = :label")
            ->setParameter("label", $label)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getForSelect(?string $term) {
        return $this->createQueryBuilder("category")
            ->select("category.id AS id, category.label AS text")
            ->where("category.label LIKE :term")
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getArrayResult();
    }

}
