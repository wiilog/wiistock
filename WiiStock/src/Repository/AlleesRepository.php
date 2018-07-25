<?php

namespace App\Repository;

use App\Entity\Allees;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Allees|null find($id, $lockMode = null, $lockVersion = null)
 * @method Allees|null findOneBy(array $criteria, array $orderBy = null)
 * @method Allees[]    findAll()
 * @method Allees[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AlleesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Allees::class);
    }

//    /**
//     * @return Allees[] Returns an array of Allees objects
//     */
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
    public function findOneBySomeField($value): ?Allees
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
