<?php

namespace App\Repository;

use App\Entity\Transport\TransportRoundStartingHour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method TransportRoundStartingHour|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportRoundStartingHour|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportRoundStartingHour[]    findAll()
 * @method TransportRoundStartingHour[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportRoundStartingHourRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TransportRoundStartingHour::class);
    }

    // /**
    //  * @return TransportRoundStartingHour[] Returns an array of TransportRoundStartingHour objects
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
    public function findOneBySomeField($value): ?TransportRoundStartingHour
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
