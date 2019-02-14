<?php

namespace App\Repository;

use App\Entity\Quais;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Quais|null find($id, $lockMode = null, $lockVersion = null)
 * @method Quais|null findOneBy(array $criteria, array $orderBy = null)
 * @method Quais[]    findAll()
 * @method Quais[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class QuaisRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Quais::class);
    }

//    /**
//     * @return Quais[] Returns an array of Quais objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('q.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Quais
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
