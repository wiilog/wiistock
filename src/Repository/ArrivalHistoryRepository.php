<?php

namespace App\Repository;

use App\Entity\ArrivalHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ArrivalHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method ArrivalHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method ArrivalHistory[]    findAll()
 * @method ArrivalHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArrivalHistoryRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ArrivalHistory::class);
    }

    // /**
    //  * @return ArrivalHistory[] Returns an array of ArrivalHistory objects
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
    public function findOneBySomeField($value): ?ArrivalHistory
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
