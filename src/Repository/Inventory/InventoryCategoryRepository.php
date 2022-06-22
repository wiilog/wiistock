<?php

namespace App\Repository\Inventory;

use App\Entity\Inventory\InventoryCategory;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * @method InventoryCategory|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryCategory|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryCategory[]    findAll()
 * @method InventoryCategory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryCategoryRepository extends EntityRepository
{
    /**
     * @param string $label
     * @return mixed
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function countByLabel($label)
    {
        return $this->createQueryBuilder('category')
            ->select('COUNT(category)')
            ->andWhere('category.label = :label')
            ->setParameter('label', $label)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
