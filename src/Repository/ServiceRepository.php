<?php

namespace App\Repository;

use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Service|null find($id, $lockMode = null, $lockVersion = null)
 * @method Service|null findOneBy(array $criteria, array $orderBy = null)
 * @method Service[]    findAll()
 * @method Service[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Service::class);
    }

    public function findByUser($user){
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT u
            FROM App\Entity\Service u
            WHERE u.demandeur = $user
           "
            );
        return $query->execute(); 
    }

    public function countByStatut($statut){
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(u)
            FROM App\Entity\Service u
            WHERE u.statut = :statut 
           "
            )->setParameter('statut', $statut);
        return $query->getSingleScalarResult(); 
    }


    
    // public function findByDate($dateMin){
    //     $entityManager = $this->getEntityManager();
    //     $query = $entityManager->createQuery(
    //         "SELECT u
    //         FROM App\Entity\Service u
    //         WHERE u.date > $dateMin 
    //         -- and u.date < $dateMax 
    //         -- and u.statut = $statut 
    //         -- and u.demandeur = $demandeur
    //        "
    //         );
    //     return $query->execute(); 
    // }

    // /**
    //  * @return Service[] Returns an array of Service objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Service
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
