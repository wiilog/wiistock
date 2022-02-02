<?php

namespace App\Repository;

use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * @method ReceptionReferenceArticle|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReceptionReferenceArticle|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReceptionReferenceArticle[]    findAll()
 * @method ReceptionReferenceArticle[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceptionReferenceArticleRepository extends EntityRepository
{

	/**
	 * @param Reception $reception
	 * @return ReceptionReferenceArticle[]|null
	 */
    public function findByReception($reception)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\ReceptionReferenceArticle a
            WHERE a.reception = :reception'
        )->setParameter('reception', $reception);;
        return $query->execute();
    }

    public function countNotConformByReception($reception)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT (a)
            FROM App\Entity\ReceptionReferenceArticle a
            WHERE a.anomalie = :conform AND a.reception = :reception"
        )->setParameters([
            'conform' => 1,
            'reception' => $reception
        ]);
        return $query->getSingleScalarResult();;
    }

	public function countByReceptionId($receptionId)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			'SELECT COUNT(rra)
            FROM App\Entity\ReceptionReferenceArticle rra
            WHERE rra.reception = :receptionId'
		)->setParameter('receptionId', $receptionId);;
		return $query->getSingleScalarResult();
	}

	/**
	 * @return ReceptionReferenceArticle[]
	 */
	public function findByReceptionAndCommandeAndRefArticleId(Reception $reception,
                                                              ?string $orderNumber,
                                                              ?int $refArticleId): array {
        return $this->createQueryBuilder('reception_reference_article')
            ->join('reception_reference_article.referenceArticle', 'referenceArticle')
            ->andWhere('reception_reference_article.reception = :reception')
            ->andWhere('reception_reference_article.commande = :orderNumber')
            ->andWhere('referenceArticle.id = :refArticleId')
            ->setParameters([
                'reception' => $reception,
                'orderNumber' => $orderNumber,
                'refArticleId' => $refArticleId
            ])
            ->getQuery()
            ->getResult();
	}

	/**
	 * @param int $receptionReferenceArticleId
	 * @return int
	 * @throws NonUniqueResultException
	 * @throws NoResultException
	 */
	public function countArticlesByRRA($receptionReferenceArticleId)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
		/* @lang DQL */
		'SELECT count(a)
		FROM App\Entity\Article a
		JOIN a.receptionReferenceArticle rra
		WHERE rra.id = :rraId'
		)->setParameter('rraId', $receptionReferenceArticleId);

		return $query->getSingleScalarResult();
	}

	public function findByReferenceArticleAndReceptionStatus(ReferenceArticle $referenceArticle, array $statuses, ?Reception $ignored = null) {
	    $queryBuilder = $this->createQueryBuilder('reception_reference_article');
	    $queryExpression = $queryBuilder->expr();
        $query = $queryBuilder
            ->join('reception_reference_article.referenceArticle', 'reference_article')
            ->join('reception_reference_article.reception', 'reception')
            ->join('reception.statut', 'status')
            ->where('reference_article = :ref')
            ->andWhere('status.code IN (:statuses)')
            ->andWhere(
                $queryExpression->orX(
                    'reception_reference_article.quantite != reception_reference_article.quantiteAR',
                    'status.code = :inProgress'
                )
            )
            ->setParameters([
                'ref' => $referenceArticle,
                'statuses' => $statuses,
                'inProgress' => Reception::STATUT_EN_ATTENTE
            ]);

	    if ($ignored) {
	        $query
                ->andWhere('reception != :recep')
                ->setParameter('recep', $ignored);
        }
	    return $query
            ->getQuery()
            ->getResult();
    }
}
