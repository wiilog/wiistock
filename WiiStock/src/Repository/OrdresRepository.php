<?php

namespace App\Repository;

use App\Entity\Ordres;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Ordres|null find($id, $lockMode = null, $lockVersion = null)
 * @method Ordres|null findOneBy(array $criteria, array $orderBy = null)
 * @method Ordres[]    findAll()
 * @method Ordres[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrdresRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Ordres::class);
    }

//    /**
//     * @return Ordres[] Returns an array of Ordres objects
//     */
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
    public function findOneBySomeField($value): ?Ordres
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
