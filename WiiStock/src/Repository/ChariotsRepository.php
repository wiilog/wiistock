<?php

namespace App\Repository;

use App\Entity\Chariots;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Chariots|null find($id, $lockMode = null, $lockVersion = null)
 * @method Chariots|null findOneBy(array $criteria, array $orderBy = null)
 * @method Chariots[]    findAll()
 * @method Chariots[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ChariotsRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Chariots::class);
    }

//    /**
//     * @return Chariots[] Returns an array of Chariots objects
//     */
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
    public function findOneBySomeField($value): ?Chariots
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
