<?php

namespace App\Repository;

use App\Entity\DashboardComponent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DashboardComponent|null find($id, $lockMode = null, $lockVersion = null)
 * @method DashboardComponent|null findOneBy(array $criteria, array $orderBy = null)
 * @method DashboardComponent[]    findAll()
 * @method DashboardComponent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DashboardComponentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DashboardComponent::class);
    }

    // /**
    //  * @return DashboardComponent[] Returns an array of DashboardComponent objects
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
    public function findOneBySomeField($value): ?DashboardComponent
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
