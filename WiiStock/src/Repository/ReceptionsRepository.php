<?php

namespace App\Repository;

use App\Entity\Receptions;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Receptions|null find($id, $lockMode = null, $lockVersion = null)
 * @method Receptions|null findOneBy(array $criteria, array $orderBy = null)
 * @method Receptions[]    findAll()
 * @method Receptions[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceptionsRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Receptions::class);
    }

//    /**
//     * @return Receptions[] Returns an array of Receptions objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('r.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Receptions
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
