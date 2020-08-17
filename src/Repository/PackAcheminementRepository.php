<?php

namespace App\Repository;

use App\Entity\PackAcheminement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method PackAcheminement|null find($id, $lockMode = null, $lockVersion = null)
 * @method PackAcheminement|null findOneBy(array $criteria, array $orderBy = null)
 * @method PackAcheminement[]    findAll()
 * @method PackAcheminement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PackAcheminementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PackAcheminement::class);
    }

    // /**
    //  * @return PackAcheminement[] Returns an array of PackAcheminement objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?PackAcheminement
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
