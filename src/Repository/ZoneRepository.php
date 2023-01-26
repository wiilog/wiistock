<?php

namespace App\Repository;

use App\Entity\Zone;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method Zone|null find($id, $lockMode = null, $lockVersion = null)
 * @method Zone|null findOneBy(array $criteria, array $orderBy = null)
 * @method Zone[]    findAll()
 * @method Zone[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ZoneRepository extends EntityRepository
{

    private const DtToDbLabels = [
        'name' => 'name',
        'description' => 'description',
    ];

    public function findByParamsAndFilters(InputBag $params)
    {
        $queryBuilder = $this->createQueryBuilder('zone');

        $countTotal = QueryBuilderHelper::count($queryBuilder, 'zone');

        //Filter search
        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $queryBuilder
                        ->andWhere("(
                            zone.name LIKE :value OR
                            zone.description LIKE :value
						)")
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = self::DtToDbLabels[$params->all('columns')[$params->all('order')[0]['column']]['data']] ?? 'id';
                    if ($column === 'name') {
                        $queryBuilder->orderBy('zone.name', $order);
                    } else if ($column === 'description') {
                        $queryBuilder->orderBy('zone.description', $order);
                    } else {
                        $queryBuilder
                            ->orderBy('zone.' . $column, $order);
                    }
                    $orderId = ($column === 'datetime')
                        ? $order
                        : 'DESC';
                    $queryBuilder->addOrderBy('zone.id', $orderId);
                }
            }
        }
        $countFiltered = QueryBuilderHelper::count($queryBuilder, 'zone');

        if ($params->getInt('start')) $queryBuilder->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $queryBuilder->setMaxResults($params->getInt('length'));

        $query = $queryBuilder->getQuery();
        return [
            'data' => $query?->getResult(),
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    public function getForSelect(?string $term): array {
        return $this->createQueryBuilder("zone")
            ->select("zone.id AS id")
            ->addSelect("zone.name AS text")
            ->addSelect("zone.description AS description")
            ->andWhere("zone.name LIKE :term")
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getArrayResult();
    }
}
