<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Statut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Article|null find($id, $lockMode = null, $lockVersion = null)
 * @method Article|null findOneBy(array $criteria, array $orderBy = null)
 * @method Article[]    findAll()
 * @method Article[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Article::class);
    }

    public function findByReception($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\Article a
            WHERE a.reception = :id'
        )->setParameter('id', $id);
        ;
        return $query->execute(); 
    }

    public function findByEmplacement($empl)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\Article a
            WHERE a.emplacement = :empl'
        )->setParameter('empl', $empl);
        ;
        return $query->execute(); 
    }
    
    public function findByStatut($statut)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
            FROM App\Entity\Article a
            WHERE a.Statut = :Statut "
        )->setParameter('Statut', $statut);
        ;
        return $query->execute(); 
    }

//    // Creation des preparations
//    public function findByRefAndConfAndStock($refArticle)
//    {
//        $entityManager = $this->getEntityManager();
//        $query = $entityManager->createQuery(
//            "SELECT a
//            FROM App\Entity\Article a
//            WHERE a.refArticle = :ref AND a.etat = TRUE AND a.Statut = 3
//            "
//        )->setParameter('ref', $refArticle);
//        ;
//        return $query->execute();
//    }

//    public function findByRefAndConf($refArticle)
//    {
//        $entityManager = $this->getEntityManager();
//        $query = $entityManager->createQuery(
//            "SELECT a
//            FROM App\Entity\Article a
//            WHERE a.refArticle = :ref AND a.etat = TRUE "
//        )->setParameter('ref', $refArticle);
//        ;
//        return $query->execute();
//    }

    public function findByEtat($etat)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\Article a
            WHERE a.etat = :etat'
        )->setParameter('etat', $etat);
        ;
        return $query->execute(); 
    }
//
// attention si on doit utiliser cette méthode, ne pas metre d'id en dur
//    public function findByStatutAndEmpl($emplacement)
//    {
//        $entityManager = $this->getEntityManager();
//        $query = $entityManager->createQuery(
//            "SELECT a
//            FROM App\Entity\Article a
//            WHERE a.Statut = 4 AND a.position = :empl"
//        )->setParameter('empl', $emplacement);
//        ;
//        return $query->execute();
//    }

// attention si on doit utiliser cette méthode, ne pas metre d'id en dur
//    public function countByStatutAndCollecte($collecte)
//    {
//        $entityManager = $this->getEntityManager();
//        $query = $entityManager->createQuery(
//            "SELECT COUNT (a)
//            FROM App\Entity\Article a
//            JOIN a.collectes c
//            WHERE a.Statut <> 3 AND c = :collecte"
//        )->setParameter('collecte', $collecte);
//
//        $result = $query->execute();
//
//        return $result ? $result[0] : null;
//    }

//    public function countByStatutAndDemande($demande)
//{
//        $entityManager = $this->getEntityManager();
//        $query = $entityManager->createQuery(
//            "SELECT COUNT(a)
//            FROM App\Entity\Article a
//            JOIN a.demandes d
//            WHERE a.Statut = 13 AND d = :demande" // demande sortie
//        )->setParameter('demande', $demande);
//
//        $result = $query->execute();
//
//        return $result ? $result[0] : null;
//    }

    public function countByRefArticleAndStatut($refArticle, $statut)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(a)
            FROM App\Entity\Article a
            JOIN a.Statut s
            WHERE a.refArticle = :refArticle AND a.etat = TRUE AND s.nom = :statut"
        )->setParameters(['refArticle' => $refArticle, 'statut' => $statut]);

        return $query->getSingleScalarResult();
    }

//    public function CountByStatut($statut)
//    {
//        $entityManager = $this->getEntityManager();
//        return $query = $entityManager->createQuery(
//            "SELECT COUNT (a) FROM App\Entity\Article a WHERE a.Statut = :statut")
//            ->setParameter('statut', $statut)
//            ->execute()
//            ;
//    }

//    public function findLast()
//    {
//        $entityManager = $this->getEntityManager();
//        $query = $entityManager->createQuery(
//            "SELECT MAX(a.id)
//            FROM App\Entity\Article a
//           "
//        )
//        ;
//        return $query->execute();
//    }

    public function findAllSortedByName()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a.nom, a.id, a.quantite
          FROM App\Entity\Article a
          ORDER BY a.nom
          "
        );
        return $query->execute();
    }

}
