<?php

namespace App\Repository;

use App\Entity\AverageRequestTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method AverageRequestTime|null find($id, $lockMode = null, $lockVersion = null)
 * @method AverageRequestTime|null findOneBy(array $criteria, array $orderBy = null)
 * @method AverageRequestTime[]    findAll()
 * @method AverageRequestTime[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AverageRequestTimeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AverageRequestTime::class);
    }

    // /**
    //  * @return AverageRequestTime[] Returns an array of AverageRequestTime objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?AverageRequestTime
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
