<?php

namespace App\Repository;

use App\Entity\Wiilock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Wiilock|null find($id, $lockMode = null, $lockVersion = null)
 * @method Wiilock|null findOneBy(array $criteria, array $orderBy = null)
 * @method Wiilock[]    findAll()
 * @method Wiilock[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WiilockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Wiilock::class);
    }

    // /**
    //  * @return Wiilock[] Returns an array of Wiilock objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('w.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Wiilock
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
