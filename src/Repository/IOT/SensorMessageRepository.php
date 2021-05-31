<?php

namespace App\Repository\IOT;

use App\Entity\IOT\SensorMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method SensorMessage|null find($id, $lockMode = null, $lockVersion = null)
 * @method SensorMessage|null findOneBy(array $criteria, array $orderBy = null)
 * @method SensorMessage[]    findAll()
 * @method SensorMessage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SensorMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SensorMessage::class);
    }

    // /**
    //  * @return SensorMessage[] Returns an array of SensorMessage objects
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
    public function findOneBySomeField($value): ?SensorMessage
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
