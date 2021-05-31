<?php

namespace App\Repository\IOT;

use App\Entity\IOT\SensorProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method SensorProfile|null find($id, $lockMode = null, $lockVersion = null)
 * @method SensorProfile|null findOneBy(array $criteria, array $orderBy = null)
 * @method SensorProfile[]    findAll()
 * @method SensorProfile[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SensorProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SensorProfile::class);
    }

    // /**
    //  * @return SensorProfile[] Returns an array of SensorProfile objects
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
    public function findOneBySomeField($value): ?SensorProfile
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
