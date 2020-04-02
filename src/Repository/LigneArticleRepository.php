<?php

namespace App\Repository;

use App\Entity\Demande;
use App\Entity\LigneArticle;
use App\Entity\ReferenceArticle;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method LigneArticle|null find($id, $lockMode = null, $lockVersion = null)
 * @method LigneArticle|null findOneBy(array $criteria, array $orderBy = null)
 * @method LigneArticle[]    findAll()
 * @method LigneArticle[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LigneArticleRepository extends EntityRepository
{

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

    /**
     * @param $referenceArticle
     * @param $demande
     * @return LigneArticle
     * @throws NonUniqueResultException
     */
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

	/**
	 * @param ReferenceArticle $referenceArticle
	 * @param Demande $demande
	 * @return LigneArticle|null
	 * @throws NonUniqueResultException
	 */
    public function findOneByRefArticleAndDemandeAndToSplit($referenceArticle, $demande)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT l
            FROM App\Entity\LigneArticle l
            WHERE l.reference = :referenceArticle AND l.demande = :demande AND l.toSplit = 1
            "
        )->setParameters([
            'referenceArticle' => $referenceArticle,
            'demande' => $demande
        ]);

        return $query->getOneOrNullResult();
    }

	/**
	 * @param Demande|int $demande
	 * @return LigneArticle[]|null
	 */
    public function findByDemande($demande)
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

    public function countByRefArticleAndDemandeAndToSplit($referenceArticle, $demande)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(l)
            FROM App\Entity\LigneArticle l
            WHERE l.reference = :referenceArticle AND l.demande = :demande AND l.toSplit = 1
            "
        )->setParameters([
            'referenceArticle' => $referenceArticle,
            'demande' => $demande
        ]);;
        return $query->getSingleScalarResult();
    }

	/**
	 * @param ReferenceArticle $refArticle
	 * @return LigneArticle[]|null
	 */
    public function findByRefArticle($refArticle)
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
