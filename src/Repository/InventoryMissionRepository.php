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
		WHERE im.startPrevDate <= '" . $now . "'
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
		JOIN im.articles a
		LEFT JOIN a.inventoryEntries ie
		LEFT JOIN a.emplacement e
		WHERE im.startPrevDate <= '" . $now . "'
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
			"SELECT ra.reference, ra.libelle as label, e.label as location, ra.quantiteStock as quantity, 1 as is_ref, 0 as treated
			FROM App\Entity\ReferenceArticle ra
			LEFT JOIN ra.emplacement e
			WHERE ra.hasInventoryAnomaly = 1
			AND ra.typeQuantite = :typeQteRef"
		)->setParameter('typeQteRef', ReferenceArticle::TYPE_QUANTITE_REFERENCE);

		return $query->execute();
	}

	public function getInventoryArtAnomalies()
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT a.reference, a.label, e.label as location, a.quantite as quantity, 0 as is_ref, 0 as treated
			FROM App\Entity\Article a
			LEFT JOIN a.emplacement e
			WHERE a.hasInventoryAnomaly = 1"
		);

		return $query->execute();
	}

	public function countArtByMission($mission)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(a)
            FROM App\Entity\Article a
            JOIN a.inventoryMissions m
            WHERE m.id = :mission"
        )->setParameter('mission', $mission);

        return $query->getSingleScalarResult();
    }

    public function countRefArtByMission($mission)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(ra)
            FROM App\Entity\ReferenceArticle ra
            JOIN ra.inventoryMissions m
            WHERE m.id = :mission"
        )->setParameter('mission', $mission);

        return $query->getSingleScalarResult();
    }

    public function findRefByParamsAndMission($mission, $params = null)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('ra')
            ->from('App\Entity\ReferenceArticle', 'ra')
            ->join('ra.inventoryMissions', 'm')
            ->where('m = :mission')
            ->setParameter('mission', $mission);

        $countQuery = $countTotal = count($qb->getQuery()->getResult());

        $allArticleDataTable = null;
        //Filter VALUE
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('ra.libelle LIKE :value OR ra.reference LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
                $countQuery = count($qb->getQuery()->getResult());
            }
            $allArticleDataTable = $qb->getQuery();
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }
        //Filter by date
        if (!empty($params->get('dateMin')) && !empty($params->get('dateMax'))) {
            $qb
                ->leftJoin('ra.inventoryEntries', 'ie')
                ->andWhere('ie.date BETWEEN :dateMin AND :dateMax')
                ->setParameter('dateMin', $params->get('dateMin'))
                ->setParameter('dateMax',$params->get('dateMax'));
            $countQuery = count($qb->getQuery()->getResult());
            $allArticleDataTable = $qb->getQuery();
        } else if (!empty($params->get('dateMin')) && empty($params->get('dateMax'))) {
            $qb
                ->leftJoin('ra.inventoryEntries', 'ie')
                ->andWhere('ie.date >= :dateMin')
                ->setParameter('dateMin', $params->get('dateMin'));
            $countQuery = count($qb->getQuery()->getResult());
            $allArticleDataTable = $qb->getQuery();
        } else if (empty($params->get('dateMin')) && !empty($params->get('dateMax'))) {
            $qb
                ->leftJoin('ra.inventoryEntries', 'ie')
                ->andWhere('ie.date <= :dateMax')
                ->setParameter('dateMax', $params->get('dateMax'));
            $countQuery = count($qb->getQuery()->getResult());
            $allArticleDataTable = $qb->getQuery();
        }
        //Filter by anomaly
        if (!empty($params->get('anomaly'))) {
            if ($params->get('anomaly') == "false")
                $anomaly = false;
            else
                $anomaly = true;
            $qb
                ->andWhere('ra.hasInventoryAnomaly = :anomaly')
                ->setParameter('anomaly', $anomaly);
            $countQuery = count($qb->getQuery()->getResult());
            $allArticleDataTable = $qb->getQuery();
        }
        $query = $qb->getQuery();
        return ['data' => $query ? $query->getResult() : null ,
            'allArticleDataTable' => $allArticleDataTable ? $allArticleDataTable->getResult() : null,
            'count' => $countQuery,
            'total' => $countTotal
        ];
    }

    public function findArtByParamsAndMission($mission, $params = null)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('a')
            ->from('App\Entity\Article', 'a')
            ->join('a.inventoryMissions', 'm')
            ->where('m = :mission')
            ->setParameter('mission', $mission);

        $countQuery = $countTotal = count($qb->getQuery()->getResult());

        $allArticleDataTable = null;
        // Filter VALUE
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->leftJoin('a.inventoryEntries', 'ie')
                        ->andWhere('a.label LIKE :value OR a.reference LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
                $countQuery = count($qb->getQuery()->getResult());
            }
            $allArticleDataTable = $qb->getQuery();
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }
        // Filter by date
        if (!empty($params->get('dateMin')) && !empty($params->get('dateMax'))) {
            $qb
                ->leftJoin('a.inventoryEntries', 'ie')
                ->andWhere('ie.date BETWEEN :dateMin AND :dateMax')
                ->setParameter('dateMin', $params->get('dateMin'))
                ->setParameter('dateMax',$params->get('dateMax'));
            $countQuery = count($qb->getQuery()->getResult());
            $allArticleDataTable = $qb->getQuery();
        } else if (!empty($params->get('dateMin')) && empty($params->get('dateMax'))) {
            $qb
                ->leftJoin('a.inventoryEntries', 'ie')
                ->andWhere('ie.date >= :dateMin')
                ->setParameter('dateMin', $params->get('dateMin'));
            $countQuery = count($qb->getQuery()->getResult());
            $allArticleDataTable = $qb->getQuery();
        } else if (empty($params->get('dateMin')) && !empty($params->get('dateMax'))) {
            $qb
                ->leftJoin('a.inventoryEntries', 'ie')
                ->andWhere('ie.date <= :dateMax')
                ->setParameter('dateMax', $params->get('dateMax'));
            $countQuery = count($qb->getQuery()->getResult());
            $allArticleDataTable = $qb->getQuery();
        }
        // Filter by anomaly
        if (!empty($params->get('anomaly'))) {
            if ($params->get('anomaly') == "false")
                $anomaly = false;
            else
                $anomaly = true;
            $qb
                ->andWhere('a.hasInventoryAnomaly = :anomaly')
                ->setParameter('anomaly', $anomaly);
            $countQuery = count($qb->getQuery()->getResult());
            $allArticleDataTable = $qb->getQuery();
        }
        $query = $qb->getQuery();
        return ['data' => $query ? $query->getResult() : null , 'allArticleDataTable' => $allArticleDataTable ? $allArticleDataTable->getResult() : null,
            'count' => $countQuery, 'total' => $countTotal];
    }
}