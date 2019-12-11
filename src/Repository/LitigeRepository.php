<?php

namespace App\Repository;

use App\Entity\Litige;
use App\Entity\LitigeHistoric;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Litige|null find($id, $lockMode = null, $lockVersion = null)
 * @method Litige|null findOneBy(array $criteria, array $orderBy = null)
 * @method Litige[]    findAll()
 * @method Litige[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LitigeRepository extends ServiceEntityRepository
{
	private const DtToDbLabels = [
		'type' => 'type',
		'arrivalNumber' => 'numeroArrivage',
		'buyers' => 'acheteurs',
		'lastHistoric' => 'lastHistoric',
		'creationDate' => 'creationDate',
		'updateDate' => 'updateDate',
		'status' => 'status',
	];

    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Litige::class);
    }

    public function findByStatutSendNotifToBuyer()
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT l
			FROM App\Entity\Litige l
			JOIN l.status s
			WHERE s.sendNotifToBuyer = 1"
		);

		return $query->execute();
	}

	public function getAcheteursByLitigeId(int $litigeId, string $field = 'email') {
        $em = $this->getEntityManager();

        $sql = "SELECT DISTINCT acheteur.$field
			FROM App\Entity\Litige litige
			JOIN litige.colis colis
			JOIN colis.arrivage arrivage
            JOIN arrivage.acheteurs acheteur
            WHERE litige.id = :litigeId";

        $query = $em
            ->createQuery($sql)
            ->setParameter('litigeId', $litigeId);

        return array_map(function($utilisateur) use ($field) {
            return $utilisateur[$field];
        }, $query->execute());
    }

	public function getAllWithArrivageData()
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT DISTINCT(l.id) as id,
                         l.creationDate,
                         l.updateDate,
                         tr.label as carrier,
                         f.nom as provider,
                         a.numeroArrivage,
                         t.label as type,
                         a.id as arrivageId,
                         s.nom status
			FROM App\Entity\Litige l
			LEFT JOIN l.colis c
			JOIN l.type t
			LEFT JOIN c.arrivage a
			LEFT JOIN a.fournisseur f
			LEFT JOIN a.chauffeur ch
			LEFT JOIN a.transporteur tr
			LEFT JOIN l.status s
			");

		return $query->execute();
	}

	/**
	 * @param int $litigeId
	 * @return LitigeHistoric
	 */
	public function getLastHistoricByLitigeId($litigeId)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT lh.date, lh.comment
			FROM App\Entity\LitigeHistoric lh
			WHERE lh.litige = :litige
			ORDER BY lh.date DESC
			")
		->setParameter('litige', $litigeId);

		$result = $query->execute();

		return $result ? $result[0] : null;
	}

	/**
	 * @param string $dateMin
	 * @param string $dateMax
	 * @return Litige[]|null
	 */
	public function findByDates($dateMin, $dateMax)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			'SELECT l
            FROM App\Entity\Litige l
            WHERE l.creationDate BETWEEN :dateMin AND :dateMax'
		)->setParameters([
			'dateMin' => $dateMin,
			'dateMax' => $dateMax
		]);
		return $query->execute();
	}

    public function findByArrivage($arrivage)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            'SELECT DISTINCT l
            FROM App\Entity\Litige l
            INNER JOIN l.colis c
            INNER JOIN c.arrivage a
            WHERE a.id = :arrivage'
        )->setParameter('arrivage', $arrivage);

        return $query->execute();
    }

    public function findByReception($reception)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            'SELECT DISTINCT l
            FROM App\Entity\Litige l
            INNER JOIN l.articles a
            INNER JOIN a.reception r
            WHERE r.id = :reception'
        )->setParameter('reception', $reception);

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
			->select('distinct(l.id) as id')
			->addSelect('l.creationDate')
			->addSelect('l.updateDate')
			->addSelect('a.numeroArrivage')
			->addSelect('t.label as type')
			->addSelect('a.id as arrivageId')
			->addSelect('s.nom as status')
			->addSelect('lh.date as dateHisto')
			->addSelect('ach.username as achUsername')
			->from('App\Entity\Litige', 'l')
			->leftJoin('l.colis', 'c')
			->leftJoin('l.type', 't')
			->leftJoin('c.arrivage', 'a')
			->leftJoin('a.chauffeur', 'ch')
			->leftJoin('l.litigeHistorics', 'lh')
			->leftJoin('a.acheteurs', 'ach')
			->leftJoin('l.status', 's');

		$countTotal = count($qb->getQuery()->getResult());

		// filtres sup
		foreach ($filters as $filter) {
			switch($filter['field']) {
				case 'providers':
					$value = explode(',', $filter['value']);
					$qb
						->join('a.fournisseur', 'f')
						->andWhere("f.id in (:fournisseur)")
						->setParameter('fournisseur', $value);
					break;
				case 'carriers':
					$qb
						->join('a.transporteur', 't2')
						->andWhere('t2.id = :transporteur')
						->setParameter('transporteur', $filter['value']);
					break;
				case 'statut':
					$qb
						->andWhere('s.nom = :status')
						->setParameter('status', $filter['value']);
					break;
				case 'type':
					$qb
						->andWhere('t.label = :type')
						->setParameter('type', $filter['value']);
					break;
				case 'utilisateurs':
					$value = explode(',', $filter['value']);
					$qb
						->andWhere("ach.id in (:userId)")
						->setParameter('userId', $value);
					break;
				case 'dateMin':
					$qb
						->andWhere('l.creationDate >= :dateMin')
						->setParameter('dateMin', $filter['value']. " 00:00:00");
					break;
				case 'dateMax':
					$qb
						->andWhere('l.creationDate <= :dateMax')
						->setParameter('dateMax', $filter['value'] . " 23:59:59");
					break;
			}
		}

		//Filter search
		if (!empty($params)) {
			if (!empty($params->get('search'))) {
				$search = $params->get('search')['value'];
				if (!empty($search)) {
					$qb
						->andWhere('
						t.label LIKE :value OR
						a.numeroArrivage LIKE :value OR
						ach.username LIKE :value OR
						s.nom LIKE :value OR
						lh.comment LIKE :value	
						')
						->setParameter('value', '%' . $search . '%');
				}
			}

			if (!empty($params->get('order')))
			{
				$order = $params->get('order')[0]['dir'];
				if (!empty($order))
				{
					$column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];

					if ($column === 'type') {
						$qb
							->orderBy('t.label', $order);
					} else if ($column === 'status') {
						$qb
							->orderBy('s.nom', $order);
					} else if ($column === 'lastHistoric') {
						$qb
							->orderBy('dateHisto', $order);
					} else if ($column === 'acheteurs') {
						$qb
							->orderBy('achUsername', $order);
					} else if ($column === 'numeroArrivage') {
						$qb
							->orderBy('a.numeroArrivage', $order);
					} else {
						$qb
							->orderBy('l.' . $column, $order);
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
}
