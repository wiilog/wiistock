<?php

namespace App\Repository;

use App\Entity\HistoryCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method HistoryCategory|null find($id, $lockMode = null, $lockVersion = null)
 * @method HistoryCategory|null findOneBy(array $criteria, array $orderBy = null)
 * @method HistoryCategory[]    findAll()
 * @method HistoryCategory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HistoryCategoryRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, HistoryCategory::class);
    }

    // /**
    //  * @return HistoryCategory[] Returns an array of HistoryCategory objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('h.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?HistoryCategory
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
