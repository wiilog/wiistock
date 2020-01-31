<?php

namespace App\Repository;

use App\Entity\InventoryMission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method InventoryMission|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryMission|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryMission[]    findAll()
 * @method InventoryMission[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryMissionRepository extends ServiceEntityRepository
{
	const DtToDbLabels = [
		'StartDate' => 'startPrevDate',
		'EndDate' => 'endPrevDate',
	];

    public function __construct(ManagerRegistry $registry)
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
		"SELECT im.id as id_mission, ra.reference, e.label as location, 1 as is_ref, ie.id as ieid, ra.barCode
		FROM App\Entity\InventoryMission im
		JOIN im.refArticles ra
		LEFT JOIN ra.inventoryEntries ie
		JOIN ra.emplacement e
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
		"SELECT im.id as id_mission, a.reference, e.label as location, 0 as is_ref, ie.id as ieid, a.barCode
		FROM App\Entity\InventoryMission im
		JOIN im.articles a
		LEFT JOIN a.inventoryEntries ie
		JOIN a.emplacement e
		WHERE im.startPrevDate <= '" . $now . "'
		AND im.endPrevDate >= '" . $now . "'
		AND ie.id is null"
		);

		return $query->execute();
	}

	public function countAnomaliesByMission($mission)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(e)
            FROM App\Entity\InventoryMission m
            JOIN m.entries e
            WHERE m = :mission AND e.anomaly = 1"
        )->setParameter('mission', $mission);

        return $query->getSingleScalarResult();
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

	/**
	 * @param InventoryMission $mission
	 * @param array|null $params
	 * @param array $filters
	 * @return array
	 */
    public function findRefByMissionAndParamsAndFilters($mission, $params = null, $filters = [])
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('ra')
            ->from('App\Entity\ReferenceArticle', 'ra')
            ->join('ra.inventoryMissions', 'm')
			->leftJoin('ra.inventoryEntries', 'ie')
            ->where('m = :mission')
            ->setParameter('mission', $mission);

        $countQuery = $countTotal = count($qb->getQuery()->getResult());

		// filtres sup
		foreach ($filters as $filter) {
			switch($filter['field']) {
				case 'anomaly':
					if ($filter['value'] == 'true') {
						$qb->andWhere('ie.anomaly = 1');
					} else if ($filter['value'] == 'false') {
						$qb->andWhere('ie.anomaly = 0');
					}
					break;
				case 'dateMin':
					$qb
						->andWhere('ie.date >= :dateMin')
						->setParameter('dateMin', $filter['value']. " 00:00:00");
					break;
				case 'dateMax':
					$qb
						->andWhere('ie.date <= :dateMax')
						->setParameter('dateMax', $filter['value'] . " 23:59:59");
					break;
			}
		}

        // filtre recherche
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

            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

        $query = $qb->getQuery();

        return [
        	'data' => $query ? $query->getResult() : null,
            'count' => $countQuery,
            'total' => $countTotal
        ];
    }

	/**
	 * @param InventoryMission $mission
	 * @param array|null $params
	 * @param array $filters
	 * @return array
	 */
    public function findArtByMissionAndParamsAndFilters($mission, $params = null, $filters = [])
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('a')
            ->from('App\Entity\Article', 'a')
            ->join('a.inventoryMissions', 'm')
			->leftJoin('a.inventoryEntries', 'ie')
            ->where('m = :mission')
            ->setParameter('mission', $mission);

        $countQuery = $countTotal = count($qb->getQuery()->getResult());

		// filtres sup
		foreach ($filters as $filter) {
			switch($filter['field']) {
				case 'anomaly':
					if ($filter['value'] == 'true') {
						$qb->andWhere('ie.anomaly = 1');
					} else if ($filter['value'] == 'false') {
						$qb->andWhere('ie.anomaly = 0');
					}
					break;
				case 'dateMin':
					$qb
						->andWhere('ie.date >= :dateMin')
						->setParameter('dateMin', $filter['value']. " 00:00:00");
					break;
				case 'dateMax':
					$qb
						->andWhere('ie.date <= :dateMax')
						->setParameter('dateMax', $filter['value'] . " 23:59:59");
					break;
			}
		}

		// filtre recherche
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('a.label LIKE :value OR a.reference LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
                $countQuery = count($qb->getQuery()->getResult());
            }

            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

        $query = $qb->getQuery();

        return [
        	'data' => $query ? $query->getResult() : null ,
            'count' => $countQuery,
			'total' => $countTotal
		];
    }

	/**
	 * @param array $params
	 * @param array $filters
	 * @return array
	 */
    public function findMissionsByParamsAndFilters($params, $filters)
	{
		$em = $this->getEntityManager();
		$qb = $em->createQueryBuilder();

		$qb
			->select('im')
			->from('App\Entity\InventoryMission', 'im');

		$countTotal = count($qb->getQuery()->getResult());

		// filtres sup
		foreach ($filters as $filter) {
			switch($filter['field']) {
				case 'anomaly':
					if ($filter['value'] == 'true') {
						$qb
							->andWhere('
							(SELECT COUNT(ie.id)
							FROM App\Entity\InventoryEntry ie
							WHERE ie.mission = im AND ie.anomaly = 1)
							 > 0');
					} else if ($filter['value'] == 'false') {
						$qb
							->andWhere('
							(SELECT COUNT(ie.id)
							FROM App\Entity\InventoryEntry ie
							WHERE ie.mission = im AND ie.anomaly = 1)
							 = 0');
					}
					break;
				case 'dateMin':
					$qb
						->andWhere('im.endPrevDate >= :dateMin')
						->setParameter('dateMin', $filter['value']. " 00:00:00");
					break;
				case 'dateMax':
					$qb
						->andWhere('im.startPrevDate <= :dateMax')
						->setParameter('dateMax', $filter['value'] . " 23:59:59");
					break;
			}
		}

		if (!empty($params)) {
			if (!empty($params->get('order')))
			{
				$order = $params->get('order')[0]['dir'];
				if (!empty($order))
				{
					$column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];
					$qb->orderBy('im.' . $column, $order);
				}
			}
		}

		// compte éléments filtrés
		$countFiltered = count($qb->getQuery()->getResult());

		if ($params) {
			if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
			if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
		}

		$query = $qb->getQuery();

		return [
			'data' => $query ? $query->getResult() : null ,
			'count' => $countFiltered,
			'total' => $countTotal
		];
	}

	/**
	 * @param string $date
	 * @return InventoryMission|null
	 */
    public function findFirstByStartDate($date)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT m
            FROM App\Entity\InventoryMission m
            WHERE m.startPrevDate = :date"
        )->setParameter('date', $date);

        $result = $query->execute();
        return $result ? $result[0] : null;
    }
}
