<?php

namespace App\Repository;

use App\Entity\Import;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Import|null find($id, $lockMode = null, $lockVersion = null)
 * @method Import|null findOneBy(array $criteria, array $orderBy = null)
 * @method Import[]    findAll()
 * @method Import[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Import::class);
    }

	public function findByParamsAndFilters($params, $filters)
	{
		$em = $this->getEntityManager();
		$qb = $em->createQueryBuilder();

		$qb
			->select('i')
			->from('App\Entity\Import', 'i');

		$countTotal = count($qb->getQuery()->getResult());

		// filtres sup
		foreach ($filters as $filter) {
			switch ($filter['field']) {
				//TODO CG
			}
		}

		//Filter search
		if (!empty($params)) {
			if (!empty($params->get('search'))) {
				$search = $params->get('search')['value'];
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

			if (!empty($params->get('order'))) {
				$order = $params->get('order')[0]['dir'];
				if (!empty($order)) {
					$column = $params->get('columns')[$params->get('order')[0]['column']]['data'];
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
		$countFiltered = count($qb->getQuery()->getResult());

		if ($params) {
			if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
			if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
		}

		$query = $qb->getQuery();

		return [
			'data' => $query ? $query->getResult() : null,
			'count' => $countFiltered,
			'total' => $countTotal
		];
	}
}
