<?php

namespace App\Repository;

use App\Entity\OrdreCollecteReference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method OrdreCollecteReference|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrdreCollecteReference|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrdreCollecteReference[]    findAll()
 * @method OrdreCollecteReference[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrdreCollecteReferenceRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, OrdreCollecteReference::class);
    }

    // /**
    //  * @return OrdreCollecteReference[] Returns an array of OrdreCollecteReference objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('o.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?OrdreCollecteReference
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
