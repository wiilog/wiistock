<?php

namespace App\Repository;

use App\Entity\ReferencesArticles;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ReferencesArticles|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReferencesArticles|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReferencesArticles[]    findAll()
 * @method ReferencesArticles[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReferencesArticlesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ReferencesArticles::class);
    }

    // récupération de ID REfERENCE QUANTITE pour la preparation des commandes 
    public function findRefArtByQte()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT r.id, r.quantiteDisponible, r.reference, r.libelle 
            FROM App\Entity\ReferencesArticles r
            WHERE r.quantiteDisponible <> 0 "
        )
        ;
        return $query->execute(); 
    }

    public function findQteBy()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT r.id, r.quantity 
            FROM App\Entity\ReferencesArticles r
           "
        )
        ;
        return $query->execute(); 
    }

    public function findRefArticleGetIdLibelle()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT r.id, r.libelle 
            FROM App\Entity\ReferencesArticles r
            "
             );
        ;
        return $query->execute(); 
    }

//    /**
//     * @return ReferencesArticles[] Returns an array of ReferencesArticles objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('r.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
     */

    /*
    public function findOneBySomeField($value): ?ReferencesArticles
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
     */
}
