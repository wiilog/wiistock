<?php

namespace App\Repository;

use App\Entity\InventoryMission;
use App\Entity\ReferenceArticle;
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
		"SELECT im.id as id_mission, a.reference, e.label as location, 0 as is_ref, ie.id as ieid, a.barCode
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
        //Filter search
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
		$qb->leftJoin('ra.inventoryEntries', 'ie');

        if (!empty($params->get('dateMin'))) {
			$qb
				->andWhere('ie.date >= :dateMin')
				->setParameter('dateMin', $params->get('dateMin'));
			$countQuery = count($qb->getQuery()->getResult());
			$allArticleDataTable = $qb->getQuery();
		}
        if (!empty($params->get('dateMax'))) {
            $qb
                ->andWhere('ie.date <= :dateMax')
                ->setParameter('dateMax', $params->get('dateMax'));
            $countQuery = count($qb->getQuery()->getResult());
            $allArticleDataTable = $qb->getQuery();
        }
        //Filter by anomaly
        if (!empty($params->get('anomaly'))) {
            if ($params->get('anomaly') == "false") {
				$anomaly = false;
			} else {
				$anomaly = true;
			}
            $qb
                ->andWhere('ie.anomaly = :anomaly')
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
        // Filter search
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
        //TODO HM à optimiser
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
            if ($params->get('anomaly') == "false") {
				$anomaly = false;
			} else {
				$anomaly = true;
			}
            $qb
                ->andWhere('ie.anomaly = :anomaly')
                ->setParameter('anomaly', $anomaly);
            $countQuery = count($qb->getQuery()->getResult());
            $allArticleDataTable = $qb->getQuery();
        }
        $query = $qb->getQuery();
        return ['data' => $query ? $query->getResult() : null , 'allArticleDataTable' => $allArticleDataTable ? $allArticleDataTable->getResult() : null,
            'count' => $countQuery, 'total' => $countTotal];
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
