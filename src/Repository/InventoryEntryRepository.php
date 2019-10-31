<?php

namespace App\Repository;

use App\Entity\InventoryEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method InventoryEntry|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryEntry|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryEntry[]    findAll()
 * @method InventoryEntry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryEntryRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, InventoryEntry::class);
    }

    public function countByMission($mission)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(ie)
            FROM App\Entity\InventoryEntry ie
            WHERE ie.mission = :mission"
        )->setParameter('mission', $mission);

        return $query->getSingleScalarResult();
    }

    public function getAnomaliesOnRef()
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT ie.id, ra.reference, ra.libelle as label, e.label as location, ra.quantiteStock as quantity, 1 as is_ref, 0 as treated, ra.barCode as barCode
			FROM App\Entity\InventoryEntry ie
			JOIN ie.refArticle ra
			LEFT JOIN ra.emplacement e
			WHERE ie.anomaly = 1
			"
		);

		return $query->execute();
	}

    public function getAnomaliesOnArt()
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT ie.id, a.reference, a.label, e.label as location, a.quantite as quantity, 0 as is_ref, 0 as treated, a.barCode as barCode
			FROM App\Entity\InventoryEntry ie
			JOIN ie.article a
			LEFT JOIN a.emplacement e
			WHERE ie.anomaly = 1
			"
		);

		return $query->execute();
	}

}
