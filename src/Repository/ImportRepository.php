<?php

namespace App\Repository;

use App\Entity\Import;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method Import|null find($id, $lockMode = null, $lockVersion = null)
 * @method Import|null findOneBy(array $criteria, array $orderBy = null)
 * @method Import[]    findAll()
 * @method Import[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImportRepository extends EntityRepository
{
	public function findByParamsAndFilters(InputBag $params, $filters)
	{
		$qb = $this->createQueryBuilder('i')
            ->join('i.status', 's')
            ->where('s.nom != :draft')
            ->setParameter('draft', Import::STATUS_DRAFT)
            ->orderBy('i.createdAt', 'DESC');

		$countTotal = QueryBuilderHelper::count($qb, 'i');

		// filtres sup
		foreach ($filters as $filter) {
			switch ($filter['field']) {
				case 'statut':
					$value = explode(',', $filter['value']);
					$qb
						->join('i.status', 's_filter')
						->andWhere('s_filter.id in (:status)')
						->setParameter('status', $value);
					break;
				case 'utilisateurs':
					$value = explode(',', $filter['value']);
					$qb
						->join('i.user', 'u_filter')
						->andWhere('u_filter.id in (:user)')
						->setParameter('user', $value);
					break;
				case 'dateMin':
					$qb
						->andWhere('i.startDate >= :dateMin OR i.endDate >= :dateMin')
						->setParameter('dateMin', $filter['value'] . " 00:00:00");
					break;
				case 'dateMax':
					$qb
						->andWhere('i.startDate <= :dateMax OR i.endDate <= :dateMax')
						->setParameter('dateMax', $filter['value'] . " 23:59:59");
					break;
			}
		}

		//Filter search
		if (!empty($params)) {
			if (!empty($params->all('search'))) {
				$search = $params->all('search')['value'];
				if (!empty($search)) {
					$exprBuilder = $qb->expr();
					$qb
						->leftJoin('i.status', 's_search')
						->leftJoin('i.user', 'u_search')
						->andWhere($exprBuilder->orX(
							'i.label LIKE :value',
							's_search.nom LIKE :value',
							'u_search.username LIKE :value'
						))
						->setParameter('value', '%' . $search . '%');
				}
			}

			if (!empty($params->all('order'))) {
				$order = $params->all('order')[0]['dir'];
				if (!empty($order)) {
					$column = $params->all('columns')[$params->all('order')[0]['column']]['data'];
					switch ($column) {
						case 'status':
							$qb
								->leftJoin('i.status', 's_order')
								->orderBy('s_order.nom', $order);
							break;
						case 'user':
							$qb
								->leftJoin('i.user', 'i_order')
								->orderBy('i_order.username', $order);
							break;
						default:
							$qb->orderBy('i.' . $column, $order);
					}
				}
			}
		}

		// compte éléments filtrés
		$countFiltered = QueryBuilderHelper::count($qb, 'i');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

		$query = $qb->getQuery();

		return [
			'data' => $query ? $query->getResult() : null,
			'count' => $countFiltered,
			'total' => $countTotal
		];
	}

    /**
     * @param string $statusLabel
     * @return Import[]
     */
	public function findByStatusLabel($statusLabel)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('i')
            ->from('App:Import', 'i')
            ->leftJoin('i.status', 's')
            ->where('s.nom = :statut')
            ->setParameter('statut', $statusLabel);

        $query = $qb->getQuery();

        return $query->execute();
    }
}
