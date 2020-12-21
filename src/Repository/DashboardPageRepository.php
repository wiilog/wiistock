<?php

namespace App\Repository;

use App\Entity\DashboardPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DashboardPage|null find($id, $lockMode = null, $lockVersion = null)
 * @method DashboardPage|null findOneBy(array $criteria, array $orderBy = null)
 * @method DashboardPage[]    findAll()
 * @method DashboardPage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DashboardPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DashboardPage::class);
    }

    // /**
    //  * @return DashboardPage[] Returns an array of DashboardPage objects
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
    public function findOneBySomeField($value): ?DashboardPage
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
