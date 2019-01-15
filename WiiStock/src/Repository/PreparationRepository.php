<?php

namespace App\Repository;

use App\Entity\Preparation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Preparation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Preparation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Preparation[]    findAll()
 * @method Preparation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PreparationRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Preparation::class);
    }

    public function findPrepaByStatut($statut)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT p
            FROM App\Entity\Preparation p
            WHERE p.statut = :statut "
        )->setParameter('statut', $statut);
        ;
        return $query->execute(); 
    }

    public function findByNoStatut($statut)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT p
            FROM App\Entity\Preparation p
            WHERE p.statut <> :statut "
        )->setParameter('statut', $statut);
        ;
        return $query->execute(); 
    }

    public function findById($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT p
            FROM App\Entity\Preparation p
            WHERE p.id = :id "
        )->setParameter('id', $id);
        ;
        return $query->execute(); 
    }

    public function findAllByUser($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT p
            FROM App\Entity\Preparation p
            WHERE p.utilisateur = :id "
        )->setParameter('id', $id);
        ;
        return $query->execute(); 
    }
    

//    /**
//     * @return Preparation[] Returns an array of Preparation objects
//     */
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
    public function findOneBySomeField($value): ?Preparation
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
