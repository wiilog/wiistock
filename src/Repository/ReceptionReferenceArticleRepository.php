<?php

namespace App\Repository;

use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;
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

	public function countByFournisseurId($fournisseurId)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT COUNT(rra)
			FROM App\Entity\ReceptionReferenceArticle rra
			WHERE rra.fournisseur = :fournisseurId"
		)->setParameter('fournisseurId', $fournisseurId);

		return $query->getSingleScalarResult();
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
	 * @param Reception $reception
	 * @param string $noCommande
	 * @param int $refArticleId
	 * @return ReceptionReferenceArticle|null
	 * @throws NonUniqueResultException
	 */
	public function findOneByReceptionAndCommandeAndRefArticleId($reception, $noCommande, $refArticleId)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
		/** @lang DQL */
			'SELECT rra
            FROM App\Entity\ReceptionReferenceArticle rra
            JOIN rra.referenceArticle ra
            WHERE rra.reception = :reception
            AND ra.id = :refArticleId
            AND rra.commande = :noCommande
            '
		)->setParameters([
			'reception' => $reception,
			'noCommande' => $noCommande,
			'refArticleId' => $refArticleId
		]);
		return $query->getOneOrNullResult();
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
}
