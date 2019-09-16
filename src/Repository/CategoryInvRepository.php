<?php

namespace App\Repository;

use App\Entity\CategoryInv;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method CategoryInv|null find($id, $lockMode = null, $lockVersion = null)
 * @method CategoryInv|null findOneBy(array $criteria, array $orderBy = null)
 * @method CategoryInv[]    findAll()
 * @method CategoryInv[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoryInvRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, CategoryInv::class);
    }

    // /**
    //  * @return CategoryInv[] Returns an array of CategoryInv objects
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
    public function findOneBySomeField($value): ?CategoryInv
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
