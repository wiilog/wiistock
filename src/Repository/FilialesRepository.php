<?php

namespace App\Repository;

use App\Entity\Filiales;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Filiales|null find($id, $lockMode = null, $lockVersion = null)
 * @method Filiales|null findOneBy(array $criteria, array $orderBy = null)
 * @method Filiales[]    findAll()
 * @method Filiales[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FilialesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Filiales::class);
    }

//    /**
//     * @return Filiales[] Returns an array of Filiales objects
//     */
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
    public function findOneBySomeField($value): ?Filiales
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
