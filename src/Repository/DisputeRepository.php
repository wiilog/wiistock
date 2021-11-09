<?php

namespace App\Repository;

use App\Entity\Dispute;
use App\Entity\Utilisateur;
use App\Helper\QueryCounter;
use App\Service\VisibleColumnService;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method Dispute|null find($id, $lockMode = null, $lockVersion = null)
 * @method Dispute|null findOneBy(array $criteria, array $orderBy = null)
 * @method Dispute[]    findAll()
 * @method Dispute[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DisputeRepository extends EntityRepository
{
	private const DtToDbLabels = [
		'type' => 'type',
		'arrivalNumber' => 'numeroArrivage',
		'provider' => 'provider',
        'receptionNumber' => 'numeroReception',
		'numCommandeBl' => 'numCommandeBl',
        'buyers' => 'acheteurs',
        'reporter' => 'reporter',
		'lastHistoryRecord' => 'lastHistoryRecord',
		'creationDate' => 'creationDate',
		'updateDate' => 'updateDate',
        'status' => 'status',
        'urgence' => 'emergencyTriggered',
        'disputeNumber' => 'disputeNumber'
	];

    public function findByStatutSendNotifToBuyer()
	{
        return $this->createQueryBuilder('dispute')
            ->join('dispute.status', 'status')
            ->andWhere('status.sendNotifToBuyer = true')
            ->getQuery()
            ->execute();
	}

	public function getAcheteursArrivageByDisputeId(int $disputeId, string $field = 'email') {
        $em = $this->getEntityManager();

        $sql = "SELECT DISTINCT acheteur.$field
			FROM App\Entity\Dispute dispute
			JOIN dispute.packs pack
			JOIN pack.arrivage arrivage
            JOIN arrivage.acheteurs acheteur
            WHERE dispute.id = :disputeId";

        $query = $em
            ->createQuery($sql)
            ->setParameter('disputeId', $disputeId);
        return array_map(function($utilisateur) use ($field) {
            return $utilisateur[$field];
        }, $query->execute());
    }

    public function getAcheteursReceptionByDisputeId(int $disputeId, string $field = 'email') {
        $em = $this->getEntityManager();

        $sql = "SELECT DISTINCT acheteur.$field
			FROM App\Entity\Dispute dispute
			JOIN dispute.buyers acheteur
            WHERE dispute.id = :disputeId";

        $query = $em
            ->createQuery($sql)
            ->setParameter('disputeId', $disputeId);

        return array_map(function($utilisateur) use ($field) {
            return $utilisateur[$field];
        }, $query->execute());
    }

	public function iterateArrivalDisputesByDates(DateTime $dateMin, DateTime $dateMax): iterable {
        return $this
            ->createQueryBuilderByDates($dateMin, $dateMax)
            ->join('dispute.packs', 'pack')
            ->join('pack.arrivage', 'arrivage')
            ->getQuery()
            ->toIterable();
	}

	public function iterateReceptionDisputesByDates(DateTime $dateMin, DateTime $dateMax): iterable
	{
        return $this
            ->createQueryBuilderByDates($dateMin, $dateMax)
            ->join('dispute.articles', 'article')
            ->join('article.receptionReferenceArticle', 'receptionReferenceArticle')
            ->join('receptionReferenceArticle.reception', 'reception')
            ->getQuery()
            ->toIterable();
	}

	public function createQueryBuilderByDates(DateTime $dateMin, DateTime $dateMax): QueryBuilder {
        $queryBuilder = $this->createQueryBuilder('dispute');
        $exprBuilder = $queryBuilder->expr();

        return $queryBuilder
            ->distinct()
            ->where($exprBuilder->between('dispute.creationDate', ':dateMin', ':dateMax'))
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
            'SELECT DISTINCT dispute
            FROM App\Entity\Dispute dispute
            INNER JOIN dispute.packs pack
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
            'SELECT DISTINCT dispute
            FROM App\Entity\Dispute dispute
            INNER JOIN dispute.articles a
            INNER JOIN a.receptionReferenceArticle rra
            INNER JOIN rra.reception r
            WHERE r.id = :reception'
        )->setParameter('reception', $reception);

        return $query->execute();
    }

	public function findByParamsAndFilters(InputBag $params, array $filters, Utilisateur $user): array
	{
        $qb = $this->createQueryBuilder('dispute');

		$qb
			->select('distinct(dispute.id) as id')
            ->addSelect('dispute.number AS disputeNumber')
            ->addSelect('dispute.creationDate')
            ->addSelect('dispute.emergencyTriggered')
            ->addSelect('dispute.updateDate')
            ->addSelect('t.label as type')
            ->addSelect('s.nom as status')
            ->addSelect('join_lastHistoryRecord.date AS lastHistoryRecord_date')
            ->addSelect('join_lastHistoryRecord.comment AS lastHistoryRecord_comment')
			->leftJoin('dispute.type', 't')
			->leftJoin('dispute.status', 's')
			->leftJoin('dispute.disputeHistory', 'join_dispute_history_record')
			->leftJoin('dispute.lastHistoryRecord', 'join_lastHistoryRecord')
			// litiges sur arrivage
            ->addSelect('reporter.username as reporterUsername')
            ->addSelect('buyers.username as achUsername')
            ->addSelect('a.numeroArrivage AS arrivalNumber')
            ->addSelect('a.id as arrivageId')
            ->leftJoin('dispute.packs', 'c')
            ->leftJoin('c.arrivage', 'a')
			->leftJoin('a.chauffeur', 'ch')
            ->leftJoin('a.acheteurs', 'ach')
            ->leftJoin('dispute.buyers', 'buyers')
            ->leftJoin('dispute.reporter', 'reporter')
			->leftJoin('a.fournisseur', 'aFourn')
			// litiges sur réceptions
            ->addSelect('r.number AS receptionNumber')
            ->addSelect('r.orderNumber')
            ->addSelect('r.id as receptionId')
            ->addSelect('(CASE WHEN aFourn.nom IS NOT NULL THEN aFourn.nom ELSE rFourn.nom END) as provider')
            ->addSelect('(CASE WHEN a.numeroCommandeList IS NOT NULL THEN a.numeroCommandeList ELSE r.orderNumber END) as numCommandeBl')
            ->leftJoin('dispute.articles', 'art')
			->leftJoin('art.receptionReferenceArticle', 'rra')
			->leftJoin('rra.referenceArticle', 'ra')
			->leftJoin('rra.reception', 'r')
			->leftJoin('r.fournisseur', 'rFourn');

        $countTotal = QueryCounter::count($qb, 'dispute');

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
						->leftJoin('dispute.buyers', 'b')
						->andWhere("ach.id in (:userId) OR b.id in (:userId)")
						->setParameter('userId', $value);
					break;
                case 'declarants':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->leftJoin('dispute.reporter', 'd')
                        ->andWhere("d.id in (:userIdDeclarant)")
                        ->setParameter('userIdDeclarant', $value);
                    break;
				case 'dateMin':
					$qb
						->andWhere('dispute.creationDate >= :dateMin')
						->setParameter('dateMin', $filter['value']. " 00:00:00");
					break;
				case 'dateMax':
					$qb
						->andWhere('dispute.creationDate <= :dateMax')
						->setParameter('dateMax', $filter['value'] . " 23:59:59");
					break;
				case 'litigeOrigin':
					if ($filter['value'] == Dispute::ORIGIN_RECEPTION) {
						$qb->andWhere('r.id is not null');
					} else if ($filter['value'] == Dispute::ORIGIN_ARRIVAGE) {
						$qb->andWhere('a.id is not null');
					}
					break;
				case 'emergency':
					$qb
						->andWhere('dispute.emergencyTriggered = :isUrgent')
						->setParameter('isUrgent', $filter['value']);
					break;
                case 'disputeNumber':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->andWhere('dispute.id in (:disputeNumber)')
                        ->setParameter('disputeNumber', $value);
                    break;
			}
		}

		//Filter search
		if (!empty($params)) {
			if (!empty($params->get('search'))) {
				$search = $params->get('search')['value'];
				if (!empty($search)) {
                    $conditions = [
                        'disputeNumber' => "dispute.number LIKE :search_value",
                        'type' => "t.label LIKE :search_value",
                        'arrivalNumber' => "a.numeroArrivage LIKE :search_value",
                        'receptionNumber' => "r.number LIKE :search_value",
                        'buyers' => "ach.username LIKE :search_value OR ach.email LIKE :search_value",
                        'numCommandeBl' => "a.numeroCommandeList LIKE :search_value",
                        'reporter' => "reporter.username LIKE :search_value",
                        'command' => "r.orderNumber LIKE :search_value",
                        'provider' => "aFourn.nom LIKE :search_value OR rFourn.nom LIKE :search_value",
                        'references' => "ra.reference LIKE :search_value",
                        'lastHistoryRecord' => "join_dispute_history_record.comment LIKE :search_value",
                        'creationDate' => "DATE_FORMAT(dispute.creationDate, '%e/%m/%Y') LIKE :search_value",
                        'updateDate' => "DATE_FORMAT(dispute.updateDate, '%e/%m/%Y') LIKE :search_value",
                        'status' => "s.nom LIKE :search_value"
                    ];

                    $condition = VisibleColumnService::getSearchableColumns($conditions, 'dispute', $qb, $user);

					$qb
						->andWhere($condition)
						->setParameter('search_value', '%' . $search . '%');
				}
			}

			if (!empty($params->get('order'))) {
                foreach ($params->get('order') as $sort) {
                    $order = $sort['dir'];
                    if (!empty($order)) {
                        $column = self::DtToDbLabels[$params->get('columns')[$sort['column']]['data']];

                        if ($column === 'type') {
                            $qb->addOrderBy('t.label', $order);
                        } else if ($column === 'status') {
                            $qb->addOrderBy('s.nom', $order);
                        } else if ($column === 'reporter') {
                            $qb->addOrderBy('reporter.username', $order);
                        } else if ($column === 'numeroArrivage') {
                            $qb->addOrderBy('a.numeroArrivage', $order);
                        } else if ($column === 'numeroReception') {
                            $qb->addOrderBy('r.number', $order);
                        } else if ($column === 'provider') {
                            $qb->addOrderBy('provider', $order);
                        } else if ($column === 'numCommandeBl') {
                            $qb->addOrderBy('numCommandeBl', $order);
                        } else if ($column === 'disputeNumber') {
                            $qb->addOrderBy('dispute.number', $order);
                        } else {
                            $qb->addOrderBy("dispute.$column", $order);
                        }
                    }
                }
			}
		}

        // compte éléments filtrés
        $countFiltered = QueryCounter::count($qb, 'dispute');

        $disputes = $this->distinctDisputes($qb->getQuery()->getResult());
        $length = $params && !empty($params->get('length'))
            ? $params->get('length')
            : -1;
        $start = $params && !empty($params->get('start'))
            ? $params->get('start')
            : 0;
        $disputes = array_slice($disputes, $start, $length);
		return [
            'data' => $disputes ,
			'count' => $countFiltered,
			'total' => $countTotal
		];
	}

	private function distinctDisputes(array $disputes, $maxLength = null) {
        $alreadySavedDisputeIds = [];
        return array_reduce(
            $disputes,
            function (array $carry, $dispute) use (&$alreadySavedDisputeIds, $maxLength) {
                $disputeId = $dispute['id'];
                if ((empty($maxLength) || count($carry) < $maxLength)
                    && !in_array($disputeId, $alreadySavedDisputeIds)) {
                    $alreadySavedDisputeIds[] = $disputeId;
                    $carry[] = $dispute;
                }
                return $carry;
            },
            []
        );
    }

	public function getCommandesByDisputeId(int $disputeId): array {
		$em = $this->getEntityManager();

		$query = $em->createQuery(
			"SELECT rra.commande
			FROM App\Entity\ReceptionReferenceArticle rra
			JOIN rra.articles a
			JOIN a.disputes dispute
            WHERE dispute.id = :disputeId")
			->setParameter('disputeId', $disputeId);

		$result = $query->execute();
		return array_column($result, 'commande');
	}

	public function getReferencesByDisputeId(int $disputeId): array {
		$em = $this->getEntityManager();

		$query = $em->createQuery(
			"SELECT ra.reference
			FROM App\Entity\ReceptionReferenceArticle rra
			JOIN rra.articles a
			JOIN rra.referenceArticle ra
			JOIN a.disputes dispute
            WHERE dispute.id = :disputeId")
			->setParameter('disputeId', $disputeId);

		$result = $query->execute();
		return array_column($result, 'reference');
	}

    public function getLastNumberByDate(string $date, string $prefix): ?string {
        $result = $this->createQueryBuilder('dispute')
            ->select('dispute.number AS number')
            ->where('dispute.number LIKE :value')
            ->orderBy('dispute.creationDate', 'DESC')
            ->addOrderBy('dispute.number', 'DESC')
            ->setParameter('value', $prefix . '-' . $date . '%')
            ->getQuery()
            ->execute();
        return $result ? $result[0]['number'] : null;
    }

    public function getIdAndDisputeNumberBySearch($search)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT dispute.id, dispute.number AS text
          FROM App\Entity\Dispute dispute
          WHERE dispute.number LIKE :search"
        )->setParameter('search', '%' . $search . '%');

        return $query->execute();
    }
}
