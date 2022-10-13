<?php

namespace App\Repository;

use App\Entity\ReceiptAssociation;
use App\Helper\QueryBuilderHelper;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method ReceiptAssociation|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReceiptAssociation|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReceiptAssociation[]    findAll()
 * @method ReceiptAssociation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceiptAssociationRepository extends EntityRepository
{
    public function countByDays($firstDay, $lastDay) {
        $from = new \DateTime(str_replace("/", "-", $firstDay) ." 00:00:00");
        $to   = new \DateTime(str_replace("/", "-", $lastDay) ." 23:59:59");
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(r.id) as count, r.creationDate as date
			FROM App\Entity\ReceiptAssociation r
			WHERE r.creationDate BETWEEN :firstDay AND :lastDay
			GROUP BY r.creationDate"
        )->setParameters([
            'lastDay' => $to,
            'firstDay' => $from
        ]);
        return $query->execute();
    }

    public function findByParamsAndFilters(InputBag $params, $filters)
    {
        $qb = $this->createQueryBuilder("receipt_association");

        $countTotal = QueryBuilderHelper::count($qb, 'receipt_association');

        foreach ($filters as $filter) {
            switch($filter['field']) {
                case 'utilisateurs':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('receipt_association.user', 'filter_user')
                        ->andWhere("filter_user.id in (:value)")
                        ->setParameter('value', $value);
                    break;
                case 'colis':
                    $value = $filter['value'];
                    $qb
                        ->andWhere("receipt_association.packCode LIKE :value")
                        ->setParameter('value', "%$value%");
                    break;
                case 'reception_string':
                    $qb
                        ->andWhere('receipt_association.receptionNumber LIKE :value')
                        ->setParameter('value', '%' . $filter['value'] . '%');
                    break;
                case 'dateMin':
                    $qb
                        ->andWhere('receipt_association.creationDate >= :dateMin')
                        ->setParameter('dateMin', $filter['value']. " 00:00:00");
                    break;
                case 'dateMax':
                    $qb
                        ->andWhere('receipt_association.creationDate <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
            }
        }

        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb
                        ->leftJoin('receipt_association.user', 'search_user')
                        ->andWhere($exprBuilder->orX(
                            "DATE_FORMAT(receipt_association.creationDate, '%d/%m/%Y') LIKE :value",
                            "DATE_FORMAT(receipt_association.creationDate, '%H:%i:%S') LIKE :value",
                            "search_user.username LIKE :value",
                            "receipt_association.packCode LIKE :value",
                            "receipt_association.receptionNumber LIKE :value",
                        ))
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->all('order')))
            {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order))
                {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];

                    if ($column === 'user') {
                        $qb
                            ->leftJoin('receipt_association.user', 'order_user')
                            ->orderBy('order_user.username', $order);
                    } else if ($column === 'pack') {
                        $qb
                            ->leftJoin('receipt_association.pack', 'order_pack')
                            ->orderBy('order_pack.code', $order);
                    } else if ($column === 'lastLocation') {
                        $qb
                            ->leftJoin('receipt_association.pack', 'order_pack')
                            ->leftJoin('order_pack.lastTracking', 'pack_lastTracking')
                            ->leftJoin('pack_lastTracking.emplacement', 'lastTracking_location')
                            ->orderBy('lastTracking_location.label', $order);
                    } else if ($column === 'lastMovementDate') {
                        $qb
                            ->leftJoin('receipt_association.pack', 'order_pack')
                            ->leftJoin('order_pack.lastTracking', 'pack_lastTracking')
                            ->orderBy('pack_lastTracking.datetime', $order);
                    } else if (property_exists(ReceiptAssociation::class, $column)) {
                        $qb->orderBy("receipt_association.$column", $order);
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryBuilderHelper::count($qb, 'receipt_association');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

        $query = $qb->getQuery();

        return [
            'data' => $query ? $query->getResult() : null ,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    public function iterateBetween(DateTime $start,
                                   DateTime $end) {
        $queryBuilder = $this->createQueryBuilder('receipt_association');
        $exprBuilder = $queryBuilder->expr();
        return $this->createQueryBuilder('receipt_association')
            ->where($exprBuilder->between('receipt_association.creationDate', ':start', ':end'))
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->toIterable();
    }
}
