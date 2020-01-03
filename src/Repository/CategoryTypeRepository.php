<?php

namespace App\Repository;

use App\Entity\CategoryType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @method CategoryType|null find($id, $lockMode = null, $lockVersion = null)
 * @method CategoryType|null findOneBy(array $criteria, array $orderBy = null)
 * @method CategoryType[]    findAll()
 * @method CategoryType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoryTypeRepository extends ServiceEntityRepository
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

    // /**
    //  * @return CategoryType[] Returns an array of CategoryType objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?CategoryType
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
