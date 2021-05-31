<?php

namespace App\Repository\IOT;

use App\Entity\IOT\Pairing;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Pairing|null find($id, $lockMode = null, $lockVersion = null)
 * @method Pairing|null findOneBy(array $criteria, array $orderBy = null)
 * @method Pairing[]    findAll()
 * @method Pairing[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PairingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pairing::class);
    }

    // /**
    //  * @return Pairing[] Returns an array of Pairing objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Pairing
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
