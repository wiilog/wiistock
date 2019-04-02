<?php

namespace App\Repository;

use App\Entity\CollecteReference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method CollecteReference|null find($id, $lockMode = null, $lockVersion = null)
 * @method CollecteReference|null findOneBy(array $criteria, array $orderBy = null)
 * @method CollecteReference[]    findAll()
 * @method CollecteReference[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CollecteReferenceRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, CollecteReference::class);
    }

    // /**
    //  * @return CollecteReference[] Returns an array of CollecteReference objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?CollecteReference
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
