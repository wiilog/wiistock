<?php

namespace App\Repository\IOT;

use App\Entity\IOT\DeliveryRequestTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DeliveryRequestTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method DeliveryRequestTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method DeliveryRequestTemplate[]    findAll()
 * @method DeliveryRequestTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DeliveryRequestTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeliveryRequestTemplate::class);
    }

    // /**
    //  * @return DeliveryRequestTemplate[] Returns an array of DeliveryRequestTemplate objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?DeliveryRequestTemplate
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
