<?php

namespace App\Repository;

use App\Entity\LocationGroup;
use App\Helper\QueryCounter;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use function Doctrine\ORM\QueryBuilder;

/**
 * @method LocationGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method LocationGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method LocationGroup[]    findAll()
 * @method LocationGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LocationGroupRepository extends EntityRepository
{

    public function findByParamsAndFilters($params)
    {
        $queryBuilder = $this->createQueryBuilder("location_group");

        $countTotal = QueryCounter::count($queryBuilder, "location_group");

        //Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $queryBuilder
                        ->andWhere($queryBuilder->expr()->orX(
                            "location_group.name LIKE :value",
                            "location_group.description LIKE :value",
                        ))
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];
                    $queryBuilder->orderBy("location_group.$column", $order);
                }
            }
        }

        $countFiltered = QueryCounter::count($queryBuilder, "location_group");

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

    public function getWithNoAssociationForSelect($term)
    {
        return $this->createQueryBuilder('location_group')
            ->select("CONCAT('locationGroup:', location_group.id) AS id")
            ->addSelect('location_group.name AS text')
            ->leftJoin('location_group.pairings', 'pairings')
            ->where('pairings.locationGroup IS NULL')
            ->andWhere("location_group.name LIKE :term")
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getResult();
    }

}
