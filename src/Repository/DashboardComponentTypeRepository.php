<?php

namespace App\Repository;

use App\Entity\DashboardComponentType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DashboardComponentType|null find($id, $lockMode = null, $lockVersion = null)
 * @method DashboardComponentType|null findOneBy(array $criteria, array $orderBy = null)
 * @method DashboardComponentType[]    findAll()
 * @method DashboardComponentType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DashboardComponentTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DashboardComponentType::class);
    }

    // /**
    //  * @return DashboardComponentType[] Returns an array of DashboardComponentType objects
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
    public function findOneBySomeField($value): ?DashboardComponentType
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
