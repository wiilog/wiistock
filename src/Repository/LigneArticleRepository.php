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
    /**
     * @param $demandes
     * @return LigneArticle[]
     */
    public function findByDemandes($demandes, $needAssoc = false)
    {
        $queryBuilder = $this->createQueryBuilder('ligne_article')
            ->select('ligne_article');

        if ($needAssoc) {
            $queryBuilder->addSelect('demande.id AS demandeId');

        }
        $queryBuilder
            ->join('ligne_article.demande' , 'demande')
            ->where('ligne_article.demande IN (:demandes)')
            ->setParameter('demandes', $demandes );

        $result = $queryBuilder
            ->getQuery()
            ->execute();

        if ($needAssoc) {
            $result = array_reduce($result, function(array $carry, $current) {
                $ligneArticle =  $current[0];
                $demandeId = $current['demandeId'];

                if (!isset($carry[$demandeId])) {
                    $carry[$demandeId] = [];
                }

                $carry[$demandeId][] = $ligneArticle;
                return $carry;
            }, []);
        }
        return $result;
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
}
