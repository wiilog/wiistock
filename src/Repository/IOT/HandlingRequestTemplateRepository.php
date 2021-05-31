<?php

namespace App\Repository\IOT;

use App\Entity\IOT\HandlingRequestTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method HandlingRequestTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method HandlingRequestTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method HandlingRequestTemplate[]    findAll()
 * @method HandlingRequestTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HandlingRequestTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HandlingRequestTemplate::class);
    }

    // /**
    //  * @return HandlingRequestTemplate[] Returns an array of HandlingRequestTemplate objects
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
    public function findOneBySomeField($value): ?HandlingRequestTemplate
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
