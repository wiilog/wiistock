<?php

namespace App\Repository;

use App\Entity\InventoryEntry;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method InventoryEntry|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryEntry|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryEntry[]    findAll()
 * @method InventoryEntry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryEntryRepository extends ServiceEntityRepository
{
	private const DtToDbLabels = [
		'Ref' => 'reference',
		'Label' => 'label',
		'Date' => 'date',
		'Location' => 'location',
		'Operator' => 'operator',
		'Quantity' => 'quantity',
	];

	public function __construct(ManagerRegistry $registry)
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

	/**
	 * @param array|null $params
	 * @param array|null $filters
	 * @return array
	 * @throws \Exception
	 */
	public function findByParamsAndFilters($params, $filters)
	{
		$em = $this->getEntityManager();
		$qb = $em->createQueryBuilder();

		$qb
			->select('ie')
			->from('App\Entity\InventoryEntry', 'ie');

		$countTotal = count($qb->getQuery()->getResult());

		// filtres sup
		foreach ($filters as $filter) {
			switch($filter['field']) {
				case 'emplacement':
                    $value = explode(':', $filter['value']);
					$qb
						->join('ie.location', 'l')
						->andWhere('l.label = :location')
						->setParameter('location', $value[1] ?? $filter['value']);
					break;
				case 'utilisateurs':
					$value = explode(',', $filter['value']);
					$qb
						->join('ie.operator', 'u')
						->andWhere("u.id in (:userId)")
						->setParameter('userId', $value);
					break;
				case 'dateMin':
					$qb->andWhere('ie.date >= :dateMin')
						->setParameter('dateMin', $filter['value']. " 00:00:00");
					break;
				case 'dateMax':
					$qb->andWhere('ie.date <= :dateMax')
						->setParameter('dateMax', $filter['value'] . " 23:59:59");
					break;
				case 'reference':
					$value = explode(':', $filter['value']);
					$qb
						->leftJoin('ie.refArticle', 'ra')
						->leftJoin('ie.article', 'a')
						->andWhere('ra.reference = :reference OR a.reference = :reference')
						->setParameter('reference', $value[1]);
			}
		}

		//Filter search
		if (!empty($params)) {
			if (!empty($params->get('search'))) {
				$search = $params->get('search')['value'];
				if (!empty($search)) {
					$qb
						->leftJoin('ie.refArticle', 'ra2')
						->leftJoin('ie.location', 'l2')
						->leftJoin('ie.utilisateur', 'u2')
						->andWhere('
						ra2.reference LIKE :value OR
						ra2.libelle LIKE :value OR
						l2.label LIKE :value OR
						u2.username LIKE :value')
						->setParameter('value', '%' . $search . '%');
				}
			}

			if (!empty($params->get('order')))
			{
				$order = $params->get('order')[0]['dir'];
				if (!empty($order))
				{
					$column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];

					if ($column === 'reference') {
						$qb
							->leftJoin('ie.refArticle', 'ra3')
							->orderBy('ra3.reference', $order);
					} else if ($column === 'label') {
						$qb
							->leftJoin('ie.refArticle', 'ra3')
							->orderBy('ra3.libelle', $order);
					} else if ($column === 'location') {
						$qb
							->leftJoin('ie.location', 'l3')
							->orderBy('l3.label', $order);
					} else if ($column === 'operator') {
						$qb
							->leftJoin('ie.operator', 'u3')
							->orderBy('u3.username', $order);
					} else {
						$qb
							->orderBy('ie.' . $column, $order);
					}
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
	 * @param DateTime $dateMin
	 * @param DateTime $dateMax
	 * @return InventoryEntry[]
	 * @throws Exception
	 */
	public function findByDates($dateMin, $dateMax)
	{
		$dateMax = $dateMax->format('Y-m-d H:i:s');
		$dateMin = $dateMin->format('Y-m-d H:i:s');

		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			'SELECT ie
            FROM App\Entity\InventoryEntry ie
            WHERE ie.date BETWEEN :dateMin AND :dateMax'
		)->setParameters([
			'dateMin' => $dateMin,
			'dateMax' => $dateMax
		]);
		return $query->execute();
	}


}
