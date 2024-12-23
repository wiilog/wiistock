<?php

namespace App\Repository;

use App\Entity\Dispute;
use App\Entity\FiltreSup;
use App\Entity\Utilisateur;
use App\Helper\QueryBuilderHelper;
use App\Service\FieldModesService;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Generator;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;

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

    public function createQueryBuilderByDates(DateTime $dateMin, DateTime $dateMax, $statuses = []): QueryBuilder {
        $queryBuilder = $this->createQueryBuilder('dispute');
        $exprBuilder = $queryBuilder->expr();

        $queryBuilder
            ->distinct()
            ->select("dispute.id AS id")
            ->addSelect("dispute.number AS number")
            ->addSelect("join_type.label AS type")
            ->addSelect("join_status.nom AS status")
            ->addSelect("dispute.creationDate AS creationDate")
            ->addSelect("dispute.updateDate AS updateDate")
            ->addSelect("join_reporter.username AS reporter")
            ->addSelect("join_last_history_record_user.username AS lastHistoryUser")
            ->addSelect("join_last_history_record.date AS lastHistoryDate")
            ->addSelect("join_last_history_record.comment AS lastHistoryComment")
            ->leftJoin("dispute.lastHistoryRecord", "join_last_history_record")
            ->leftJoin("join_last_history_record.user", "join_last_history_record_user")
            ->leftJoin("dispute.reporter", "join_reporter")
            ->leftJoin("dispute.type", "join_type")
            ->leftJoin("dispute.status", "join_status")
            ->where($exprBuilder->between('dispute.creationDate', ':dateMin', ':dateMax'))
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);

        if (!empty($statuses)) {
            $queryBuilder
                ->andWhere('join_status in (:statuses)')
                ->setParameter('statuses', $statuses);
        }

        return $queryBuilder;
    }

    /**
     * @param int[] $statusIds
     * @return Generator<Dispute>
     */
    public function iterateBetween(DateTime $dateMin,
                                   DateTime $dateMax,
                                   array    $statusIds = []): iterable {
        $queryBuilder = $this->createQueryBuilder('dispute');
        $exprBuilder = $queryBuilder->expr();

        $queryBuilder = $queryBuilder
            ->andWhere($exprBuilder->between('dispute.creationDate', ':dateMin', ':dateMax'))
            ->setParameter('dateMin', $dateMin)
            ->setParameter('dateMax', $dateMax);

        if (!empty($statusIds)) {
            $queryBuilder
                ->andWhere('join_status in (:statuses)')
                ->setParameter('statuses', $statusIds);
        }

        return $queryBuilder
            ->getQuery()
            ->toIterable();
    }

	public function findByParamsAndFilters(InputBag $params, array $filters, Utilisateur $user, FieldModesService $fieldModesService): array
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
			->leftJoin('rra.receptionLine', 'rl')
			->leftJoin('rl.reception', 'r')
			->leftJoin('r.fournisseur', 'rFourn');

        $countTotal = QueryBuilderHelper::count($qb, 'dispute');

        // filtres sup
		foreach ($filters as $filter) {
			switch($filter['field']) {
				case 'statut':
					$value = explode(',', $filter['value']);
					$qb
						->andWhere('s.id in (:statut)')
						->setParameter('statut', $value);
					break;
                case FiltreSup::FIELD_MULTIPLE_TYPES:
                    if(!empty($filter['value'])){
                        $value = Stream::explode(',', $filter['value'])
                            ->filter()
                            ->map(static fn($type) => explode(':', $type)[0])
                            ->toArray();
                        $qb
                            ->andWhere('t.id in (:filter_type_value)')
                            ->setParameter('filter_type_value', $value);
                    }
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
			if (!empty($params->all('search'))) {
				$search = $params->all('search')['value'];
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

                    $fieldModesService->bindSearchableColumns($conditions, 'dispute', $qb, $user, $search);
				}
			}

			if (!empty($params->all('order'))) {
                foreach ($params->all('order') as $sort) {
                    $order = $sort['dir'];
                    if (!empty($order)) {
                        $column = self::DtToDbLabels[$params->all('columns')[$sort['column']]['data']];

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
        $countFiltered = QueryBuilderHelper::count($qb, 'dispute');

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

    public function getLastNumberByDate(string $date, ?string $prefix): ?string {
        $result = $this->createQueryBuilder('dispute')
            ->select('dispute.number AS number')
            ->where('dispute.number LIKE :value')
            ->orderBy('dispute.creationDate', 'DESC')
            ->addOrderBy('dispute.number', 'DESC')
            ->setParameter('value', ($prefix ? ($prefix . '-') : '') . $date . '%')
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

    public function countByFilters(array $filters = []): int {
        $qb = $this->createQueryBuilder('dispute')
            ->select('COUNT(dispute)')
            ->innerJoin('dispute.status', 'status', JOIN::WITH, 'status.id IN (:statuses)')
            ->innerJoin('dispute.type', 'type', JOIN::WITH, 'type.id IN (:types)')
            ->setParameter('types', $filters['types'])
            ->setParameter('statuses', $filters['statuses']);

        if($filters["disputeEmergency"]){
            $qb->andWhere("dispute.emergencyTriggered = 1");
        }

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }
}
