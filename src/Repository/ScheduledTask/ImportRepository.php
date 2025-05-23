<?php

namespace App\Repository\ScheduledTask;

use App\Entity\ScheduledTask\Import;
use App\Entity\Type\Type;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method Import|null find($id, $lockMode = null, $lockVersion = null)
 * @method Import|null findOneBy(array $criteria, array $orderBy = null)
 * @method Import[]    findAll()
 * @method Import[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImportRepository extends EntityRepository implements ScheduledTaskRepository
{
	public function findByParamsAndFilters(InputBag $params, $filters): array
	{
		$qb = $this->createQueryBuilder('import')
            ->innerJoin('import.status', 'join_status', Join::WITH, "join_status.code <> :draft")
            ->setParameter('draft', Import::STATUS_DRAFT)
            ->orderBy('import.createdAt', 'DESC');

		$countTotal = QueryBuilderHelper::count($qb, 'import');

		foreach ($filters as $filter) {
			switch ($filter['field']) {
				case 'statut':
					$value = explode(',', $filter['value']);
					$qb
						->innerJoin('import.status', 'join_status_filter')
						->andWhere('join_status_filter.id IN (:status)')
						->setParameter('status', $value);
					break;
				case 'utilisateurs':
					$value = explode(',', $filter['value']);
					$qb
						->innerJoin('import.user', 'join_user_filter')
						->andWhere('join_user_filter.id IN (:user)')
						->setParameter('user', $value);
					break;
				case 'dateMin':
					$qb
						->andWhere('import.startDate >= :dateMin OR import.endDate >= :dateMin')
						->setParameter('dateMin', "{$filter['value']} 00:00:00");
					break;
				case 'dateMax':
					$qb
						->andWhere('import.startDate <= :dateMax OR import.endDate <= :dateMax')
						->setParameter('dateMax', "{$filter['value']} 23:59:59");
					break;
                case 'type':
                    $qb
                        ->innerJoin('import.type', 'join_type_filter')
                        ->andWhere('join_type_filter.label IN (:type)')
                        ->setParameter('type', $filter['value']);
			}
		}

		if (!empty($params)) {
			if (!empty($params->all('search'))) {
				$search = $params->all('search')['value'];
				if (!empty($search)) {
					$exprBuilder = $qb->expr();
					$qb
						->leftJoin('import.status', 'join_status_search')
						->leftJoin('import.user', 'join_user_search')
                        ->leftJoin('import.type', 'join_type_search')
						->andWhere($exprBuilder->orX(
							'import.label LIKE :value',
							'join_status_search.nom LIKE :value',
							'join_user_search.username LIKE :value',
                            'join_type_search.label LIKE :value'
						))
						->setParameter('value', "%$search%");
				}
			}

			if (!empty($params->all('order'))) {
				$order = $params->all('order')[0]['dir'];
				if (!empty($order)) {
					$column = $params->all('columns')[$params->all('order')[0]['column']]['data'];
					switch ($column) {
						case 'status':
							$qb
								->leftJoin('import.status', 'join_status_order')
								->orderBy('join_status_order.nom', $order);
							break;
						case 'user':
							$qb
								->leftJoin('import.user', 'join_user_order')
								->orderBy('join_user_order.username', $order);
							break;
                        case 'type':
                            $qb
                                ->leftJoin('import.type', 'join_type_order')
                                ->orderBy('join_type_order.label', $order);
                            break;
                        case 'frequency':
                            $qb
                                ->leftJoin('import.scheduleRule', 'join_schedule_rule_order')
                                ->orderBy('join_schedule_rule_order.frequency', $order);
                            break;
						default:
							$qb->orderBy('import.' . $column, $order);
					}
				}
			}
		}

		$countFiltered = QueryBuilderHelper::count($qb, 'import');

        if ($params->getInt('start')) {
            $qb->setFirstResult($params->getInt('start'));
        }

        if ($params->getInt('length')) {
            $qb->setMaxResults($params->getInt('length'));
        }

		$query = $qb->getQuery();

		return [
			'data' => $query?->getResult(),
			'count' => $countFiltered,
			'total' => $countTotal
		];
	}

	public function findByStatusLabel($statusLabel): array {
        return $this->createQueryBuilder("import")
            ->leftJoin("import.status", "join_status")
            ->andWhere("join_status.code = :status")
            ->setParameter("status", $statusLabel)
            ->getQuery()
            ->getResult();
    }


    /**
     * @return Import[]
     */
    public function findScheduled(): array {
        return $this->createQueryBuilder("import")
            ->innerJoin("import.type", "join_type", Join::WITH, "join_type.label = :type")
            ->innerJoin("import.status", "join_status", Join::WITH, "join_status.code = :status")
            ->setParameter("type", Type::LABEL_SCHEDULED_IMPORT)
            ->setParameter("status", Import::STATUS_SCHEDULED)
            ->getQuery()
            ->getResult();
    }
}
