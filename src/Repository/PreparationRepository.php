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

    public function findPrepaByStatut($Statut)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT p
            FROM App\Entity\Preparation p
            WHERE p.Statut = :Statut "
        )->setParameter('Statut', $Statut);
        ;
        return $query->execute(); 
    }

    public function findByNoStatut($Statut)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT p
            FROM App\Entity\Preparation p
            WHERE p.Statut <> :Statut "
        )->setParameter('Statut', $Statut);
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
    
    public function findByPrepa($preparation)
    {
        $entityManager = $this->getEntityManager();

        return $query = $entityManager->createQuery(
            "SELECT p FROM App\Entity\Preparation p WHERE p.id = :preparation")
            ->setParameter("preparation", $preparation->getId())
            ->execute()
            ;
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
