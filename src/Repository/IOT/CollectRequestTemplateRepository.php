<?php

namespace App\Repository\IOT;

use App\Entity\IOT\CollectRequestTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CollectRequestTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method CollectRequestTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method CollectRequestTemplate[]    findAll()
 * @method CollectRequestTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CollectRequestTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CollectRequestTemplate::class);
    }

    // /**
    //  * @return CollectRequestTemplate[] Returns an array of CollectRequestTemplate objects
    //  */
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
    public function findOneBySomeField($value): ?CollectRequestTemplate
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
