<?php

namespace App\Repository;

use App\Entity\Litige;
use App\Entity\LitigeHistoric;
use App\Helper\QueryCounter;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Exception;

/**
 * @method Litige|null find($id, $lockMode = null, $lockVersion = null)
 * @method Litige|null findOneBy(array $criteria, array $orderBy = null)
 * @method Litige[]    findAll()
 * @method Litige[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LitigeRepository extends EntityRepository
{
	private const DtToDbLabels = [
		'type' => 'type',
		'arrivalNumber' => 'numeroArrivage',
		'receptionNumber' => 'numeroReception',
		'provider' => 'provider',
		'numCommandeBl' => 'numCommandeBl',
        'buyers' => 'acheteurs',
        'declarant' => 'declarant',
		'lastHistoric' => 'lastHistoric',
		'creationDate' => 'creationDate',
		'updateDate' => 'updateDate',
        'status' => 'status',
        'urgence' => 'emergencyTriggered',
        'disputeNumber' => 'disputeNumber',
	];

    public function findByStatutSendNotifToBuyer()
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT litige
			FROM App\Entity\Litige litige
			JOIN litige.status s
			WHERE s.sendNotifToBuyer = 1"
		);

		return $query->execute();
	}

	public function getAcheteursArrivageByLitigeId(int $litigeId, string $field = 'email') {
        $em = $this->getEntityManager();

        $sql = "SELECT DISTINCT acheteur.$field
			FROM App\Entity\Litige litige
			JOIN litige.packs pack
			JOIN pack.arrivage arrivage
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
			"SELECT DISTINCT(litige.id) as id,
                         litige.creationDate,
                         litige.updateDate,
                         tr.label as carrier,
                         f.nom as provider,
                         a.numeroArrivage,
                         t.label as type,
                         a.id as arrivageId,
                         s.nom status
			FROM App\Entity\Litige litige
			LEFT JOIN litige.packs c
			JOIN litige.type t
			LEFT JOIN c.arrivage a
			LEFT JOIN a.fournisseur f
			LEFT JOIN a.chauffeur ch
			LEFT JOIN a.transporteur tr
			LEFT JOIN litige.status s
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
            ->join('litige.packs', 'pack')
            ->join('pack.arrivage', 'arrivage')
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
            'SELECT DISTINCT litige
            FROM App\Entity\Litige litige
            INNER JOIN litige.packs pack
            INNER JOIN pack.arrivage a
            WHERE a.id = :arrivage'
        )->setParameter('arrivage', $arrivage);

        return $query->execute();
    }

    public function findByReception($reception)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            'SELECT DISTINCT litige
            FROM App\Entity\Litige litige
            INNER JOIN litige.articles a
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
			->select('distinct(litige.id) as id')
            ->addSelect('litige.numeroLitige AS disputeNumber')
            ->addSelect('litige.creationDate')
            ->addSelect('litige.emergencyTriggered')
            ->addSelect('litige.updateDate')
            ->addSelect('t.label as type')
            ->addSelect('s.nom as status')
			->from('App\Entity\Litige', 'litige')
			->leftJoin('litige.type', 't')
			->leftJoin('litige.status', 's')
			->leftJoin('litige.litigeHistorics', 'lh')
			// litiges sur arrivage
            ->addSelect('declarant.username as declarantUsername')
            ->addSelect('buyers.username as achUsername')
            ->addSelect('a.numeroArrivage')
            ->addSelect('a.id as arrivageId')
            ->leftJoin('litige.packs', 'c')
            ->leftJoin('c.arrivage', 'a')
			->leftJoin('a.chauffeur', 'ch')
            ->leftJoin('a.acheteurs', 'ach')
            ->leftJoin('litige.buyers', 'buyers')
            ->leftJoin('litige.declarant', 'declarant')
			->leftJoin('a.fournisseur', 'aFourn')
			// litiges sur réceptions
            ->addSelect('r.numeroReception')
            ->addSelect('r.orderNumber')
            ->addSelect('r.id as receptionId')
            ->addSelect('(CASE WHEN aFourn.nom IS NOT NULL THEN aFourn.nom ELSE rFourn.nom END) as provider')
            ->addSelect('(CASE WHEN a.numeroCommandeList IS NOT NULL THEN a.numeroCommandeList ELSE r.orderNumber END) as numCommandeBl')
            ->leftJoin('litige.articles', 'art')
			->leftJoin('art.receptionReferenceArticle', 'rra')
			->leftJoin('rra.referenceArticle', 'ra')
			->leftJoin('rra.reception', 'r')
			->leftJoin('r.fournisseur', 'rFourn');

        $countTotal = QueryCounter::count($qb, 'litige');

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
						->leftJoin('litige.buyers', 'b')
						->andWhere("ach.id in (:userId) OR b.id in (:userId)")
						->setParameter('userId', $value);
					break;
                case 'declarants':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->leftJoin('litige.declarant', 'd')
                        ->andWhere("d.id in (:userIdDeclarant)")
                        ->setParameter('userIdDeclarant', $value);
                    break;
				case 'dateMin':
					$qb
						->andWhere('litige.creationDate >= :dateMin')
						->setParameter('dateMin', $filter['value']. " 00:00:00");
					break;
				case 'dateMax':
					$qb
						->andWhere('litige.creationDate <= :dateMax')
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
						->andWhere('litige.emergencyTriggered = :isUrgent')
						->setParameter('isUrgent', $filter['value']);
					break;
                case 'disputeNumber':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->andWhere('litige.id in (:disputeNumber)')
                        ->setParameter('disputeNumber', $value);
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
						declarant.username LIKE :value OR
						declarant.email LIKE :value OR
						a.numeroArrivage LIKE :value OR
						r.numeroReception LIKE :value OR
						r.orderNumber LIKE :value OR
                        rra.commande LIKE :value OR
						ach.username LIKE :value OR
						ach.email LIKE :value OR
						buyers.email LIKE :value OR
						s.nom LIKE :value OR
						lh.comment LIKE :value OR
						aFourn.nom LIKE :value OR
						rFourn.nom LIKE :value OR
						ra.reference LIKE :value OR
						a.numeroCommandeList LIKE :value OR
						litige.numeroLitige LIKE :value
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
                        } else if ($column === 'declarant') {
                            $qb
                                ->addOrderBy('declarant.username', $order);
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
                                ->addOrderBy('numCommandeBl', $order);
                        } else if ($column === 'disputeNumber') {
                            $qb
                                ->addOrderBy('litige.numeroLitige', $order);
                        } else {
                            $qb
                                ->addOrderBy('litige.' . $column, $order);
                        }
                    }
                }
			}
		}

        // compte éléments filtrés
        $countFiltered = QueryCounter::count($qb, 'litige');

        $litiges = $this->distinctLitige($qb->getQuery()->getResult());
        $length = $params && !empty($params->get('length'))
            ? $params->get('length')
            : -1;
        $start = $params && !empty($params->get('start'))
            ? $params->get('start')
            : 0;
        $litiges = array_slice($litiges, $start, $length);
		return [
            'data' => $litiges ,
			'count' => $countFiltered,
			'total' => $countTotal
		];
	}

	private function distinctLitige(array $litiges, $maxLength = null) {
        $alreadySavedLitigeId = [];
        return array_reduce(
            $litiges,
            function (array $carry, $litige) use (&$alreadySavedLitigeId, $maxLength) {
                $litigeId = $litige['id'];
                if ((empty($maxLength) || count($carry) < $maxLength)
                    && !in_array($litigeId, $alreadySavedLitigeId)) {
                    $alreadySavedLitigeId[] = $litigeId;
                    $carry[] = $litige;
                }
                return $carry;
            },
            []
        );
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
			JOIN a.litiges litige
            WHERE litige.id = :litigeId")
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
			JOIN a.litiges litige
            WHERE litige.id = :litigeId")
			->setParameter('litigeId', $litigeId);

		$result = $query->execute();
		return array_column($result, 'reference');
	}

    public function getLastNumeroLitigeByPrefixeAndDate($prefixe, $date)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            'SELECT litige.numeroLitige as numeroLitige
			FROM App\Entity\Litige litige
			WHERE litige.numeroLitige LIKE :value
			ORDER BY litige.creationDate DESC'
        )->setParameter('value', $prefixe . $date . '%');

        $result = $query->execute();
        return $result ? $result[0]['numeroLitige'] : null;
    }

    public function getIdAndDisputeNumberBySearch($search)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT litige.id, litige.numeroLitige as text
          FROM App\Entity\Litige litige
          WHERE litige.numeroLitige LIKE :search"
        )->setParameter('search', '%' . $search . '%');

        return $query->execute();
    }
}
