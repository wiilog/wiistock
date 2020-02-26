<?php

namespace App\Repository;

use App\Entity\ColumnVisible;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method ColumnVisible|null find($id, $lockMode = null, $lockVersion = null)
 * @method ColumnVisible|null findOneBy(array $criteria, array $orderBy = null)
 * @method ColumnVisible[]    findAll()
 * @method ColumnVisible[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ColumnVisibleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ColumnVisible::class);
    }

    // /**
    //  * @return ColumnVisible[] Returns an array of ColumnVisible objects
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
    public function findOneBySomeField($value): ?ColumnVisible
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
