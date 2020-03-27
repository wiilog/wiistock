<?php

namespace App\Repository;

use App\Entity\CategoryType;
use Doctrine\ORM\EntityRepository;

/**
 * @method CategoryType|null find($id, $lockMode = null, $lockVersion = null)
 * @method CategoryType|null findOneBy(array $criteria, array $orderBy = null)
 * @method CategoryType[]    findAll()
 * @method CategoryType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoryTypeRepository extends EntityRepository
{
    public function getNoOne($category)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT c
            FROM App\Entity\CategoryType c
            WHERE c.id <> :category"
             )->setParameter('category', $category);
        ;
        return $query->execute();
    }

}
