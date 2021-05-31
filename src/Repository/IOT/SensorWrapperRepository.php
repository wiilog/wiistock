<?php

namespace App\Repository\IOT;

use App\Entity\IOT\SensorWrapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method SensorWrapper|null find($id, $lockMode = null, $lockVersion = null)
 * @method SensorWrapper|null findOneBy(array $criteria, array $orderBy = null)
 * @method SensorWrapper[]    findAll()
 * @method SensorWrapper[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SensorWrapperRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SensorWrapper::class);
    }

    // /**
    //  * @return SensorWrapper[] Returns an array of SensorWrapper objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?SensorWrapper
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
