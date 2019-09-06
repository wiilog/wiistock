<?php

namespace App\Repository;

use App\Entity\AlerteStock;
use App\Entity\Article;
use App\Entity\ReferenceArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method AlerteStock|null find($id, $lockMode = null, $lockVersion = null)
 * @method AlerteStock|null findOneBy(array $criteria, array $orderBy = null)
 * @method AlerteStock[]    findAll()
 * @method AlerteStock[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AlerteStockRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, AlerteStock::class);
    }

	/**
	 * @return int
	 * @throws \Doctrine\ORM\NonUniqueResultException
	 */
    public function countAlertsSecurityActive()
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			"SELECT COUNT(a)
			FROM App\Entity\AlerteStock a
			JOIN a.refArticle ra
			WHERE (
				(ra.typeQuantite = :qte_ref AND ra.quantiteStock <= a.limitSecurity)
				OR
				(ra.typeQuantite = :qte_art AND (
					SELECT SUM(art.quantite)
					FROM App\Entity\Article art
					JOIN art.articleFournisseur af
					JOIN af.referenceArticle ra2
					JOIN art.statut s
					WHERE s.nom =:active AND ra2 = ra
				) <= a.limitSecurity)
			)"
		)->setParameters([
			'qte_ref' => ReferenceArticle::TYPE_QUANTITE_REFERENCE,
			'qte_art' => ReferenceArticle::TYPE_QUANTITE_ARTICLE,
			'active' => Article::STATUT_ACTIF
		]);

		return $query->getSingleScalarResult();
	}

	/**
	 * @return int
	 * @throws \Doctrine\ORM\NonUniqueResultException
	 */
	public function countAlertsWarningActive()
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
		/** @lang DQL */
			"SELECT COUNT(a)
			FROM App\Entity\AlerteStock a
			JOIN a.refArticle ra
			WHERE (
					(ra.typeQuantite = :qte_ref AND (ra.quantiteStock <= a.limitWarning OR ra.quantiteStock <= a.limitSecurity))
					OR
					(ra.typeQuantite = :qte_art AND 
						(
							(SELECT SUM(art1.quantite)
							FROM App\Entity\Article art1
							JOIN art1.articleFournisseur af1
							JOIN af1.referenceArticle refart1
							JOIN art1.statut s1
							WHERE s1.nom =:active AND refart1 = ra)
							<= a.limitWarning
						OR
							(SELECT SUM(art2.quantite)
							FROM App\Entity\Article art2
							JOIN art2.articleFournisseur af2
							JOIN af2.referenceArticle refart2
							JOIN art2.statut s2
							WHERE s2.nom =:active AND refart2 = ra)
							<= a.limitSecurity
						)
					)
				)"
		)->setParameters([
			'qte_ref' => ReferenceArticle::TYPE_QUANTITE_REFERENCE,
			'qte_art' => ReferenceArticle::TYPE_QUANTITE_ARTICLE,
			'active' => Article::STATUT_ACTIF
		]);

		return $query->getSingleScalarResult();
	}

	/**
	 * @param ReferenceArticle $refArticle
	 * @return int
	 * @throws \Doctrine\ORM\NonUniqueResultException
	 */
	public function countByRef($refArticle)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			"SELECT count(a) 
			FROM App\Entity\AlerteStock a
			WHERE a.refArticle = :refArticle"
		)->setParameter('refArticle', $refArticle);

		return $query->getSingleScalarResult();
	}
}
