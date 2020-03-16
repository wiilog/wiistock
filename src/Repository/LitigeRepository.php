<?php

namespace App\Repository;

use App\Entity\Litige;
use App\Entity\LitigeHistoric;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

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
		'receptionNumber' => 'numeroReception',
		'provider' => 'provider',
		'numCommandeBl' => 'numCommandeBl',
		'buyers' => 'acheteurs',
		'lastHistoric' => 'lastHistoric',
		'creationDate' => 'creationDate',
		'updateDate' => 'updateDate',
        'status' => 'status',
        'urgence' => 'emergencyTriggered',
	];

    public function __construct(ManagerRegistry $registry)
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

	public function getAcheteursArrivageByLitigeId(int $litigeId, string $field = 'email') {
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

    public function getAcheteursReceptionByLitigeId(int $litigeId, string $field = 'email') {
        $em = $this->getEntityManager();

        $sql = "SELECT DISTINCT acheteur.$field
			FROM App\Entity\Litige litige
			JOIN litige.buyers acheteur
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
	 * @param DateTime $dateMin
	 * @param DateTime $dateMax
	 * @return Litige[]|null
	 */
	public function findArrivalsLitigeByDates(DateTime $dateMin, DateTime $dateMax)
	{
		$query = $this
            ->createQueryBuilderByDates($dateMin, $dateMax)
            ->join('litige.colis', 'colis')
            ->join('colis.arrivage', 'arrivage')
            ->getQuery();
		return $query->execute();
	}

	/**
	 * @param DateTime $dateMin
	 * @param DateTime $dateMax
	 * @return Litige[]|null
	 */
	public function findReceptionLitigeByDates(DateTime $dateMin, DateTime $dateMax)
	{
		$query = $this
            ->createQueryBuilderByDates($dateMin, $dateMax)
            ->join('litige.articles', 'article')
            ->join('article.receptionReferenceArticle', 'receptionReferenceArticle')
            ->join('receptionReferenceArticle.reception', 'reception')
            ->getQuery();
		return $query->execute();
	}

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return QueryBuilder
     */
	public function createQueryBuilderByDates(DateTime $dateMin, DateTime $dateMax): QueryBuilder {
        $queryBuilder = $this->createQueryBuilder('litige');
        $exprBuilder = $queryBuilder->expr();

        return $queryBuilder
            ->distinct()
            ->where($exprBuilder->between('litige.creationDate', ':dateMin', ':dateMax'))
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);
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
            INNER JOIN a.receptionReferenceArticle rra
            INNER JOIN rra.reception r
            WHERE r.id = :reception'
        )->setParameter('reception', $reception);

        return $query->execute();
    }

	/**
	 * @param array|null $params
	 * @param array|null $filters
	 * @return array
	 * @throws Exception
	 */
	public function findByParamsAndFilters($params, $filters)
	{
		$em = $this->getEntityManager();
		$qb = $em->createQueryBuilder();

		$qb
			->select('distinct(l.id) as id')
			->from('App\Entity\Litige', 'l')
			->addSelect('l.creationDate')
            ->addSelect('l.emergencyTriggered')
			->addSelect('l.updateDate')
			->leftJoin('l.type', 't')
			->addSelect('t.label as type')
			->leftJoin('l.status', 's')
			->addSelect('s.nom as status')
			->leftJoin('l.litigeHistorics', 'lh')
			// litiges sur arrivage
            ->leftJoin('l.colis', 'c')
            ->leftJoin('c.arrivage', 'a')
			->leftJoin('a.chauffeur', 'ch')
            ->leftJoin('a.acheteurs', 'ach')
            ->leftJoin('l.buyers', 'buyers')
			->addSelect('ach.username as achUsername')
			->addSelect('a.numeroArrivage')
			->addSelect('a.id as arrivageId')
			->leftJoin('a.fournisseur', 'aFourn')
			// litiges sur réceptions
            ->leftJoin('l.articles', 'art')
			->leftJoin('art.receptionReferenceArticle', 'rra')
			->leftJoin('rra.referenceArticle', 'ra')
			->leftJoin('rra.reception', 'r')
			->addSelect('r.numeroReception')
			->addSelect('r.reference')
			->addSelect('r.id as receptionId')
			->leftJoin('r.fournisseur', 'rFourn')
			->addSelect('(CASE WHEN aFourn.nom IS NOT NULL THEN aFourn.nom ELSE rFourn.nom END) as provider')
			->addSelect('(CASE WHEN a.numeroCommandeList IS NOT NULL THEN a.numeroCommandeList ELSE r.reference END) as numCommandeBl');

		$countTotal = count($qb->getQuery()->getResult());

		// filtres sup
		foreach ($filters as $filter) {
			switch($filter['field']) {
				//TODO à remettre en place en requêtant sur arrivages + réceptions
//				case 'providers':
//					$value = explode(',', $filter['value']);
//					$qb
//						->join('a.fournisseur', 'f2')
//						->andWhere("f2.id in (:fournisseurId)")
//						->setParameter('fournisseurId', $value);
//					break;
//				case 'carriers':
//					$qb
//						->join('a.transporteur', 't2')
//						->andWhere('t2.id = :transporteur')
//						->setParameter('transporteur', $filter['value']);
//					break;
				case 'statut':
					$value = explode(',', $filter['value']);
					$qb
						->andWhere('s.id in (:statut)')
						->setParameter('statut', $value);
					break;
				case 'type':
					$qb
						->andWhere('t.label = :type')
						->setParameter('type', $filter['value']);
					break;
				case 'utilisateurs':
					$value = explode(',', $filter['value']);
					$qb
						->leftJoin('l.buyers', 'b')
						->andWhere("ach.id in (:userId) OR b.id in (:userId)")
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
				case 'litigeOrigin':
					if ($filter['value'] == Litige::ORIGIN_RECEPTION) {
						$qb->andWhere('r.id is not null');
					} else if ($filter['value'] == Litige::ORIGIN_ARRIVAGE) {
						$qb->andWhere('a.id is not null');
					}
					break;
				case 'emergency':
					$qb
						->andWhere('l.emergencyTriggered = :isUrgent')
						->setParameter('isUrgent', $filter['value']);
					break;
			}
		}

		//Filter search
		if (!empty($params)) {
			if (!empty($params->get('search'))) {
				$search = $params->get('search')['value'];
				if (!empty($search)) {
					$qb
						->andWhere('(
						t.label LIKE :value OR
						a.numeroArrivage LIKE :value OR
						r.numeroReception LIKE :value OR
						ach.username LIKE :value OR
						ach.email LIKE :value OR
						buyers.email LIKE :value OR
						s.nom LIKE :value OR
						lh.comment LIKE :value OR
						aFourn.nom LIKE :value OR
						rFourn.nom LIKE :value OR
						ra.reference LIKE :value OR
						a.numeroCommandeList LIKE :value
						)')
						->setParameter('value', '%' . $search . '%');
				}
			}

			if (!empty($params->get('order')))
			{
                foreach ($params->get('order') as $sort) {
                    $order = $sort['dir'];
                    if (!empty($order))
                    {
                        $column = self::DtToDbLabels[$params->get('columns')[$sort['column']]['data']];

                        if ($column === 'type') {
                            $qb
                                ->addOrderBy('t.label', $order);
                        } else if ($column === 'status') {
                            $qb
                                ->addOrderBy('s.nom', $order);
                        } else if ($column === 'acheteurs') {
                            $qb
                                ->addOrderBy('achUsername', $order);
                        } else if ($column === 'numeroArrivage') {
                            $qb
                                ->addOrderBy('a.numeroArrivage', $order);
                        } else if ($column === 'numeroReception') {
                            $qb
                                ->addOrderBy('r.numeroReception', $order);
                        } else if ($column === 'provider') {
                            $qb
                                ->addOrderBy('provider', $order);
                        } else if ($column === 'numCommandeBl') {
                            $qb
                                ->addOrderBy('a.numeroCommandeList', $order);
                        } else {
                            $qb
                                ->addOrderBy('l.' . $column, $order);
                        }
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
	 * @param int $litigeId
	 * @return string[]
	 */
	public function getCommandesByLitigeId(int $litigeId) {
		$em = $this->getEntityManager();

		$query = $em->createQuery(
			"SELECT rra.commande
			FROM App\Entity\ReceptionReferenceArticle rra
			JOIN rra.articles a
			JOIN a.litiges l
            WHERE l.id = :litigeId")
			->setParameter('litigeId', $litigeId);

		$result = $query->execute();
		return array_column($result, 'commande');
	}

	/**
	 * @param int $litigeId
	 * @return string[]
	 */
	public function getReferencesByLitigeId(int $litigeId) {
		$em = $this->getEntityManager();

		$query = $em->createQuery(
			"SELECT ra.reference
			FROM App\Entity\ReceptionReferenceArticle rra
			JOIN rra.articles a
			JOIN rra.referenceArticle ra
			JOIN a.litiges l
            WHERE l.id = :litigeId")
			->setParameter('litigeId', $litigeId);

		$result = $query->execute();
		return array_column($result, 'reference');
	}
}
