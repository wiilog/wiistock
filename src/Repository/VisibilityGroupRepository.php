<?php

namespace App\Repository;

use App\Entity\ReferenceArticle;
use App\Entity\VisibilityGroup;
use App\Helper\QueryCounter;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method VisibilityGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method VisibilityGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method VisibilityGroup[]    findAll()
 * @method VisibilityGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VisibilityGroupRepository extends EntityRepository {

    public function findByParamsAndFilters(InputBag $params): array
    {
        $queryBuilder = $this->createQueryBuilder("visibility_group");

        $countTotal = QueryCounter::count($queryBuilder, "visibility_group");

        //Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $queryBuilder
                        ->andWhere($queryBuilder->expr()->orX(
                            "visibility_group.label LIKE :value",
                            "visibility_group.description LIKE :value",
                        ))
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];
                    if ($column === 'status') {
                        $queryBuilder->addOrderBy("visibility_group.active", $order === 'asc' ? 'desc' : 'asc');
                    }
                    else if (property_exists(VisibilityGroup::class, $column)) {
                        $queryBuilder->addOrderBy("visibility_group.$column", $order);
                    }
                }
            }
        }

        $countFiltered = QueryCounter::count($queryBuilder, "visibility_group");

        if ($params) {
            if (!empty($params->get('start'))) $queryBuilder->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $queryBuilder->setMaxResults($params->get('length'));
        }

        $query = $queryBuilder->getQuery();
        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }
}
