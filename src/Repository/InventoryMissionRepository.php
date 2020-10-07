<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\InventoryMission;
use App\Entity\ReferenceArticle;
use App\Helper\QueryCounter;
use DateTime;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

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

    /**
     * @return int|mixed|string
     * @throws Exception
     */
    public function getCurrentMissionRefNotTreated()
	{
		$now = new DateTime('now', new \DateTimeZone('Europe/Paris'));
		$queryBuilder = $this->createQueryBuilder('inventoryMission');
		$exprBuilder = $queryBuilder->expr();

		$queryBuilder
            ->select('inventoryMission.id AS id_mission')
            ->addSelect('refArticle.reference AS reference')
            ->addSelect('emplacement.label AS location')
            ->addSelect('1 AS is_ref')
            ->addSelect('inventoryEntry.id AS ieid')
            ->addSelect('refArticle.barCode AS barCode')
            ->join('inventoryMission.refArticles', 'refArticle')
            ->join('refArticle.emplacement', 'emplacement')
            ->leftJoin('inventoryMission.entries', 'inventoryEntry', Join::WITH, 'inventoryEntry.refArticle = refArticle')
            ->where($exprBuilder->andX(
                'inventoryMission.startPrevDate <= :now',
                'inventoryMission.endPrevDate >= :now',
                'inventoryEntry.id IS NULL'
            ))
            ->setParameter('now', $now->format('Y-m-d'));

		return $queryBuilder
            ->getQuery()
            ->execute();
	}

    /**
     * @return int|mixed|string
     * @throws Exception
     */
	public function getCurrentMissionArticlesNotTreated()
	{
		$now = new DateTime('now', new \DateTimeZone('Europe/Paris'));

        $queryBuilder = $this->createQueryBuilder('inventoryMission');
        $exprBuilder = $queryBuilder->expr();

        $queryBuilder
            ->select('inventoryMission.id AS id_mission')
            ->addSelect('referenceArticle.reference AS reference')
            ->addSelect('article.barCode AS barCode')
            ->addSelect('emplacement.label AS location')
            ->addSelect('0 AS is_ref')
            ->addSelect('inventoryMission.id AS ied')
            ->join('inventoryMission.articles', 'article')
            ->join('article.emplacement', 'emplacement')
            ->join('article.articleFournisseur', 'articleFournisseur')
            ->join('articleFournisseur.referenceArticle', 'referenceArticle')
            ->leftJoin('inventoryMission.entries', 'inventoryEntry', Join::WITH, 'inventoryEntry.article = article')
            ->where($exprBuilder->andX(
                'inventoryMission.startPrevDate <= :now',
                'inventoryMission.endPrevDate >= :now',
                'inventoryEntry.id IS NULL'
            ))
            ->setParameter('now', $now->format('Y-m-d'));

		return $queryBuilder
            ->getQuery()
            ->execute();
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

	/**
	 * @param InventoryMission $mission
	 * @return int
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
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

	/**
	 * @param InventoryMission $mission
	 * @return int
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
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

        $countQuery = $countTotal = QueryCounter::count($qb);

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
                        ->andWhere('ra.libelle LIKE :value OR ra.reference LIKE :value OR ra.barCode LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
                $countQuery = QueryCounter::count($qb);
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

        $countQuery = $countTotal = QueryCounter::count($qb);

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
                $countQuery = QueryCounter::count($qb);
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
		$qb = $this->createQueryBuilder("im");

		$countTotal = QueryCounter::count($qb);

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
		$countFiltered = QueryCounter::count($qb);

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

    /**
     * @param ReferenceArticle $ref
     * @param DateTimeInterface $startDate
     * @param DateTimeInterface $endDate
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countByRefAndDates($ref, $startDate, $endDate): int {
        return $this->createQueryBuilderMissionInBracket($startDate, $endDate)
            ->join('mission.refArticles', 'refArticle')
            ->andWhere('refArticle = :refArt')
            ->setParameter('refArt', $ref)
            ->select('COUNT(mission)')
            ->getQuery()
            ->getSingleScalarResult();
	}

    /**
     * @param Article $art
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countByArtAndDates($art, $startDate, $endDate): int {
		return $this->createQueryBuilderMissionInBracket($startDate, $endDate)
            ->join('mission.articles', 'article')
            ->andWhere('article = :art')
            ->setParameter('art', $art)
            ->select('COUNT(mission)')
            ->getQuery()
            ->getSingleScalarResult();
	}

	private function createQueryBuilderMissionInBracket(DateTime $startDate, DateTime $endDate): QueryBuilder {
        $queryBuilder = $this->createQueryBuilder('mission');
        $exprBuilder = $queryBuilder->expr();

        // On teste si les dates ne se chevauchent pas
        return $queryBuilder
            ->where($exprBuilder->orX(
                $exprBuilder->between('mission.startPrevDate', ':startDate', ':endDate'),
                $exprBuilder->between('mission.endPrevDate', ':startDate', ':endDate'),
                $exprBuilder->between(':startDate', 'mission.startPrevDate', 'mission.endPrevDate'),
                $exprBuilder->between(':endDate', 'mission.startPrevDate', 'mission.endPrevDate')
            ))
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);
    }
}
