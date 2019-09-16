<?php

namespace App\Repository;

use App\Entity\FrequencyInv;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method FrequencyInv|null find($id, $lockMode = null, $lockVersion = null)
 * @method FrequencyInv|null findOneBy(array $criteria, array $orderBy = null)
 * @method FrequencyInv[]    findAll()
 * @method FrequencyInv[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FrenquencyInvRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, FrequencyInv::class);
    }

    // /**
    //  * @return FrenquencyInv[] Returns an array of FrenquencyInv objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('f.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?FrenquencyInv
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
