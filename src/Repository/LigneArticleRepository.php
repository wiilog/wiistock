<?php

namespace App\Repository;

use App\Entity\LigneArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method LigneArticle|null find($id, $lockMode = null, $lockVersion = null)
 * @method LigneArticle|null findOneBy(array $criteria, array $orderBy = null)
 * @method LigneArticle[]    findAll()
 * @method LigneArticle[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LigneArticleRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, LigneArticle::class);
    }

    public function getQuantity($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT l
            FROM App\Entity\LigneArticle l
            WHERE l.id = :id
            "
        )->setParameter('id', $id);;
        return $query->getSingleResult();
    }

    public function findOneByRefArticleAndDemande($referenceArticle, $demande)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT l
            FROM App\Entity\LigneArticle l
            WHERE l.reference = :referenceArticle AND l.demande = :demande
            "
        )->setParameters([
            'referenceArticle' => $referenceArticle,
            'demande' => $demande
        ]);

        return $query->getOneOrNullResult();
    }

    public function getByDemande($demande)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT l 
            FROM App\Entity\LigneArticle l
            JOIN l.demande d
            WHERE d.id = :demande
            "
        )->setParameter('demande', $demande);;
        return $query->getResult();
    }

    public function countByRefArticleDemande($referenceArticle, $demande)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(l)
            FROM App\Entity\LigneArticle l
            WHERE l.reference = :referenceArticle AND l.demande = :demande
            "
        )->setParameters([
            'referenceArticle' => $referenceArticle,
            'demande' => $demande
        ]);;
        return $query->getSingleScalarResult();
    }

    public function findOneByRefArticle($refArticle)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT la
            FROM App\Entity\LigneArticle la
            WHERE la.reference = :refArticle"
        )->setParameter('refArticle', $refArticle);

        return $query->getResult();
    }

    //    public function countByArticle($referenceArticle)
    //    {
    //        $entityManager = $this->getEntityManager();
    //        $query = $entityManager->createQuery(
    //            "SELECT COUNT(l)
    //            FROM App\Entity\LigneArticle l
    //            WHERE l.reference = :referenceArticle
    //            "
    //        )->setParameters([
    //            'referenceArticle'=> $referenceArticle,
    //            ]);
    //        ;
    //        return $query->getSingleScalarResult();
    //    }

    // /**
    //  * @return LigneArticle[] Returns an array of LigneArticle objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('l.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?LigneArticle
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
