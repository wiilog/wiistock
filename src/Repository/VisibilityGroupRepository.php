<?php

namespace App\Repository;

use App\Entity\VisibilityGroup;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method VisibilityGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method VisibilityGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method VisibilityGroup[]    findAll()
 * @method VisibilityGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VisibilityGroupRepository extends EntityRepository {

    public function getForSelect(?string $term) {
        $qb = $this->createQueryBuilder("visibility_group")
            ->select("visibility_group.id AS id, visibility_group.label AS text")
            ->andWhere("visibility_group.label LIKE :term")
            ->andWhere('visibility_group.active = true')
            ->setParameter("term", "%$term%");

        return $qb
            ->getQuery()
            ->getArrayResult();
    }

    public function findByParamsAndFilters(InputBag $params): array
    {
        $queryBuilder = $this->createQueryBuilder("visibility_group");

        $countTotal = QueryBuilderHelper::count($queryBuilder, "visibility_group");

        //Filter search
        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $queryBuilder
                        ->andWhere($queryBuilder->expr()->orX(
                            "visibility_group.label LIKE :value",
                            "visibility_group.description LIKE :value",
                        ))
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];
                    if ($column === 'status') {
                        $queryBuilder->addOrderBy("visibility_group.active", $order === 'asc' ? 'desc' : 'asc');
                    }
                    else if (property_exists(VisibilityGroup::class, $column)) {
                        $queryBuilder->addOrderBy("visibility_group.$column", $order);
                    }
                }
            }
        }

        $countFiltered = QueryBuilderHelper::count($queryBuilder, "visibility_group");

        if ($params->getInt('start')) $queryBuilder->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $queryBuilder->setMaxResults($params->getInt('length'));

        $query = $queryBuilder->getQuery();
        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

}
