<?php

namespace App\Repository;

use App\Entity\DashboardPageRow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DashboardPageRow|null find($id, $lockMode = null, $lockVersion = null)
 * @method DashboardPageRow|null findOneBy(array $criteria, array $orderBy = null)
 * @method DashboardPageRow[]    findAll()
 * @method DashboardPageRow[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DashboardPageRowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DashboardPageRow::class);
    }

    // /**
    //  * @return DashboardPageRow[] Returns an array of DashboardPageRow objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?DashboardPageRow
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
