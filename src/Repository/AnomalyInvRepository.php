<?php

namespace App\Repository;

use App\Entity\AnomalyInv;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method AnomalyInv|null find($id, $lockMode = null, $lockVersion = null)
 * @method AnomalyInv|null findOneBy(array $criteria, array $orderBy = null)
 * @method AnomalyInv[]    findAll()
 * @method AnomalyInv[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AnomalyInvRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, AnomalyInv::class);
    }

    // /**
    //  * @return AnomalyInv[] Returns an array of AnomalyInv objects
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
    public function findOneBySomeField($value): ?AnomalyInv
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
