<?php

namespace App\Repository;

use App\Entity\ReceiptAssociation;
use App\Helper\QueryCounter;
use DateTime;
use Doctrine\ORM\EntityRepository;

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

    public function findByParamsAndFilters($params, $filters)
    {
        $qb = $this->createQueryBuilder("receipt_association");

        $countTotal = QueryCounter::count($qb, 'receipt_association');

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
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('receipt_association.pack', 'filter_pack')
                        ->andWhere("filter_pack.code in (:value)")
                        ->setParameter('value', $value);
                    break;
                case 'reception_string':
                    dump('test');
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
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb
                        ->leftJoin('receipt_association.user', 'search_user')
                        ->leftJoin('receipt_association.pack', 'search_pack')
                        ->leftJoin('search_pack.lastTracking', 'search_lastTracking')
                        ->leftJoin('search_lastTracking.emplacement', 'search_location')
                        ->andWhere($exprBuilder->orX(
                            "DATE_FORMAT(receipt_association.creationDate, '%d/%m/%Y') LIKE :value",
                            "DATE_FORMAT(receipt_association.creationDate, '%H:%i:%S') LIKE :value",
                            "search_user.username LIKE :value",
                            "search_pack.code LIKE :value",
                            "receipt_association.receptionNumber LIKE :value",
                            "DATE_FORMAT(search_lastTracking.datetime, '%d/%m/%Y') LIKE :value",
                            "DATE_FORMAT(search_lastTracking.datetime, '%H:%i:%S') LIKE :value"
                        ))
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order')))
            {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order))
                {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];

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
        $countFiltered = QueryCounter::count($qb, 'receipt_association');

        if ($params) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

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
        $iterator = $this->createQueryBuilder('receipt_association')
            ->where($exprBuilder->between('receipt_association.creationDate', ':start', ':end'))
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->iterate();

        foreach($iterator as $item) {
            yield array_pop($item);
        }
    }
}
