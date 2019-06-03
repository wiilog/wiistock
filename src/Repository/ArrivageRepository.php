<?php

namespace App\Repository;

use App\Entity\Arrivage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Arrivage|null find($id, $lockMode = null, $lockVersion = null)
 * @method Arrivage|null findOneBy(array $criteria, array $orderBy = null)
 * @method Arrivage[]    findAll()
 * @method Arrivage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArrivageRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Arrivage::class);
    }

    // /**
    //  * @return Arrivage[] Returns an array of Arrivage objects
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
    public function findOneBySomeField($value): ?Arrivage
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
