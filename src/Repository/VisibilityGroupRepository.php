<?php

namespace App\Repository;

use App\Entity\VisibilityGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method VisibilityGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method VisibilityGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method VisibilityGroup[]    findAll()
 * @method VisibilityGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VisibilityGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VisibilityGroup::class);
    }

    // /**
    //  * @return VisibilityGroup[] Returns an array of VisibilityGroup objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('v.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?VisibilityGroup
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
