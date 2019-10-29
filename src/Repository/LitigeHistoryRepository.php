<?php

namespace App\Repository;

use App\Entity\LitigeHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method LitigeHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method LitigeHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method LitigeHistory[]    findAll()
 * @method LitigeHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LitigeHistoryRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, LitigeHistory::class);
    }

    // /**
    //  * @return LitigeHistory[] Returns an array of LitigeHistory objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('l.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?LitigeHistory
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
