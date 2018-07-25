<?php

namespace App\Repository;

use App\Entity\Inventaires;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Inventaires|null find($id, $lockMode = null, $lockVersion = null)
 * @method Inventaires|null findOneBy(array $criteria, array $orderBy = null)
 * @method Inventaires[]    findAll()
 * @method Inventaires[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventairesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Inventaires::class);
    }

//    /**
//     * @return Inventaires[] Returns an array of Inventaires objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('i.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Inventaires
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
