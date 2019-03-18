<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Utilisateur|null find($id, $lockMode = null, $lockVersion = null)
 * @method Utilisateur|null findOneBy(array $criteria, array $orderBy = null)
 * @method Utilisateur[]    findAll()
 * @method Utilisateur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UtilisateurRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    public function countByEmail($email)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(u)
            FROM App\Entity\Utilisateur u
            WHERE u.email = :email"
        )->setParameter('email', $email);
        ;
        return $query->getSingleScalarResult();
    }
    
    public function countApiKey($apiKey)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(u)
            FROM App\Entity\Utilisateur u
            WHERE u.apiKey = :apiKey"
        )->setParameter('apiKey', $apiKey);
        ;
        return $query->getSingleScalarResult();
    }

    public function getIdAndUsername(){
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT u.id, u.username
            FROM App\Entity\Utilisateur u
            "
        );
        return $query->execute(); 
    }

    public function getNoOne($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT u
            FROM App\Entity\Utilisateur u
            WHERE u.id <> :id"
        )->setParameter('id', $id);
        ;
        return $query->execute(); 
    }




//   /**
//     * @return Utilisateur[] Returns an array of Utilisateurs objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Utilisateur
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
