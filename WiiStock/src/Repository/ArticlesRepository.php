<?php

namespace App\Repository;

use App\Entity\Articles;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Articles|null find($id, $lockMode = null, $lockVersion = null)
 * @method Articles|null findOneBy(array $criteria, array $orderBy = null)
 * @method Articles[]    findAll()
 * @method Articles[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticlesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Articles::class);
    }

    public function findByReception($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\Articles a
            WHERE a.reception = :id'
        )->setParameter('id', $id);
        ;
        return $query->execute(); 
    }

    public function findByStatut($statut)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
            FROM App\Entity\Articles a
            WHERE a.statu = :statut "
        )->setParameter('statut', $statut);
        ;
        return $query->execute(); 
    }
    
    public function findById($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\Articles a
            WHERE a.id = :id'
        )->setParameter('id', $id);
        ;
        return $query->execute(); 
    }

    public function findQteByRefAndConf($refArticle)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a.quantite
            FROM App\Entity\Articles a
            WHERE a.refArticle = :ref AND a.etat = TRUE AND (a.statu = 'en stock' OR a.statu = 'demande de mise en stock')"
        )->setParameter('ref', $refArticle);
        ;
        return $query->execute(); 
    }

    // Creation des preparations 
    public function findByRefAndConfAndStock($refArticle)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
            FROM App\Entity\Articles a
            WHERE a.refArticle = :ref AND a.etat = TRUE AND a.statu = 'en stock' 
            "
        )->setParameter('ref', $refArticle);
        ;
        return $query->execute(); 
    }

    public function findByRefAndConf($refArticle)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
            FROM App\Entity\Articles a
            WHERE a.refArticle = :ref AND a.etat = TRUE "
        )->setParameter('ref', $refArticle);
        ;
        return $query->execute(); 
    }

    public function findByEtat($etat)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\Articles a
            WHERE a.etat = :etat'
        )->setParameter('etat', $etat);
        ;
        return $query->execute(); 
    }


//    /**
//     * @return Articles[] Returns an array of Articles objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Articles
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
