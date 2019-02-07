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
            WHERE a.Statut = :Statut "
        )->setParameter('Statut', $statut);
        ;
        return $query->execute(); 
    }

    //filtre de recherche par le nom
    public function findFiltreByNom($nom)
    {   $nomB = $nom . '%';
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
            FROM App\Entity\Articles a
            WHERE a.nom LIKE :nom"
        )->setParameter('nom', $nomB);
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

    // Creation des preparations 
    public function findByRefAndConfAndStock($refArticle)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
            FROM App\Entity\Articles a
            WHERE a.refArticle = :ref AND a.etat = TRUE AND a.Statut = 3 
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

    public function findByStatutAndEmpl($emplacement)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
            FROM App\Entity\Articles a
            WHERE a.Statut = 4 AND a.position = :empl"
        )->setParameter('empl', $emplacement);
        ;
        return $query->execute(); 
    }

    public function findCountByStatutAndCollecte($collecte)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT (a)
            FROM App\Entity\Articles a
            JOIN a.collectes c
            WHERE a.Statut <> 3 AND c = :collecte"
        )->setParameter('collecte', $collecte);
        ;
        return $query->execute(); 
    }

    public function findCountByStatutAndDemande($demande)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(a)
            FROM App\Entity\Articles a
            JOIN a.demandes d
            WHERE a.Statut = 13 AND d = :demande"
        )->setParameter('demande', $demande);
        ;
        return $query->execute(); 
    }

    public function findCountByRefArticle($refArticle)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(a)
            FROM App\Entity\Articles a
            WHERE a.refArticle = :refArticle AND a.etat = TRUE AND (a.Statut = 3)"
        )->setParameter('refArticle', $refArticle);
        ;
        return $query->execute(); 
    }

    public function CountByStatut($statut)
    {
        $entityManager = $this->getEntityManager();
        return $query = $entityManager->createQuery(
            "SELECT COUNT (a) FROM App\Entity\Articles a WHERE a.Statut = :statut")
            ->setParameter('statut', $statut)
            ->execute()
            ;
    }

    public function findLast()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT MAX(a.id)
            FROM App\Entity\Articles a
           "
        )
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
