<?php

namespace App\Repository;

use App\Entity\InventoryMission;
use App\Entity\ReferenceArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method InventoryMission|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryMission|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryMission[]    findAll()
 * @method InventoryMission[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryMissionRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, InventoryMission::class);
    }

    public function getCurrentMissionRefNotTreated()
	{
		$now = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
		$now = $now->format('Y-m-d');

		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
		"SELECT im.id as id_mission, ra.reference, e.label as location, 1 as is_ref, ie.id as ieid
		FROM App\Entity\InventoryMission im
		JOIN im.refArticles ra
		LEFT JOIN ra.inventoryEntries ie
		LEFT JOIN ra.emplacement e
		WHERE ra.typeQuantite = '" . ReferenceArticle::TYPE_QUANTITE_REFERENCE . "'
		AND im.startPrevDate <= '" . $now . "'
		AND im.endPrevDate >= '" . $now . "'
		AND ie.id is null"
		);

		return $query->execute();
	}

	public function getCurrentMissionArticlesNotTreated()
	{
		$now = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
		$now = $now->format('Y-m-d');

		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
		"SELECT im.id as id_mission, a.reference, e.label as location, 0 as is_ref, ie.id as ieid
		FROM App\Entity\InventoryMission im
		JOIN im.refArticles ra
		JOIN ra.articlesFournisseur af
		JOIN af.articles a
		LEFT JOIN a.inventoryEntries ie
		LEFT JOIN a.emplacement e
		WHERE ra.typeQuantite = '" . ReferenceArticle::TYPE_QUANTITE_ARTICLE . "'
		AND im.startPrevDate <= '" . $now . "'
		AND im.endPrevDate >= '" . $now . "'
		AND ie.id is null"
		);

		return $query->execute();
	}

	public function countByMissionAnomaly($mission)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(a)
            FROM App\Entity\InventoryMission m
            JOIN m.articles a
            JOIN m.refArticles ra
            WHERE m = :mission AND (a.hasInventoryAnomaly = true OR ra.hasInventoryAnomaly = true)"
        )->setParameter('mission', $mission);

        return $query->getSingleScalarResult();
    }

	public function getInventoryRefAnomalies()
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT ra.reference, ra.libelle as label, e.label as location, ra.quantiteStock as quantity, 1 as is_ref
			FROM App\Entity\ReferenceArticle ra
			JOIN ra.emplacement e
			WHERE ra.hasInventoryAnomaly = 1"
		);

		return $query->execute();
	}

	public function getInventoryArtAnomalies()
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT a.reference, a.label, e.label as location, a.quantite as quantity, 0 as is_ref
			FROM App\Entity\Article a
			JOIN a.emplacement e
			WHERE a.hasInventoryAnomaly = 1"
		);

		return $query->execute();
	}

}