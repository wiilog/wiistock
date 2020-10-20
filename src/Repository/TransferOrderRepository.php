<?php

namespace App\Repository;

use App\Entity\TransferOrder;
use App\Helper\QueryCounter;
use Doctrine\ORM\EntityRepository;

/**
 * @method TransferOrder|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransferOrder|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransferOrder[]    findAll()
 * @method TransferOrder[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransferOrderRepository extends EntityRepository {

    public function findByParamsAndFilters($params, $filters) {
        $qb = $this->createQueryBuilder("transfer_order");
        $total =  QueryCounter::count($qb, "transfer_order");

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statut':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('transfer_order.status', 'status')
                        ->andWhere('status.id in (:status)')
                        ->setParameter('status', $value);
                    break;
                case 'requesters':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('transfer_order.request', 'filter_request')
                        ->andWhere("filter_request.requester in (:id)")
                        ->setParameter('id', $value);
                    break;
                case 'operators':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->andWhere("transfer_order.operator in (:id)")
                        ->setParameter('id', $value);
                    break;
                case 'dateMin':
                    $qb->andWhere('transfer_order.creationDate >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb->andWhere('transfer_order.creationDate <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
            }
        }

        //Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb
                        ->join('transfer_order.request', 'search_request')
                        ->andWhere(
                            $exprBuilder->orX(
                                'search_request.number LIKE :value',
                                'search_requester.username LIKE :value',
                                'search_operator.username LIKE :value',
                                'search_status.nom LIKE :value'
                            )
                        )
                        ->setParameter('value', '%' . $search . '%')
                        ->leftJoin('search_request.requester', 'search_requester')
                        ->leftJoin('transfer_order.operator', 'search_operator')
                        ->leftJoin('search_request.status', 'search_status');
                }
            }

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];

                    switch ($column) {
                        case 'number':
                            $qb
                                ->orderBy("transfer_order.number", $order);
                            break;
                        case 'status':
                            $qb
                                ->leftJoin('transfer_order.status', 'order_status')
                                ->orderBy('order_status.nom', $order);
                            break;
                        case 'destination':
                            $qb
                                ->leftJoin('transfer_order.request', 'order_request')
                                ->leftJoin('order_request.destination', 'order_requestDestination')
                                ->orderBy('order_requestDestination.label', $order);
                            break;
                        case 'requester':
                            $qb
                                ->leftJoin('transfer_order.request', 'order_request')
                                ->leftJoin('order_request.requester', 'order_requestRequester')
                                ->orderBy('order_requestRequester.username', $order);
                            break;
                        case 'creationDate':
                            $qb
                                ->orderBy("transfer_order.creationDate", $order);
                            break;
                        case 'validationDate':
                            $qb
                                ->leftJoin('transfer_order.request', 'order_request')
                                ->orderBy("order_request.validationDate", $order);
                            break;
                        default:
                            $qb->orderBy('transfer_order.' . $column, $order);
                            break;
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered =  QueryCounter::count($qb, 'transfer_order');

        if ($params) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

        return [
            'data' => $qb->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $total
        ];
    }

    public function getLastTransferNumberByPrefix($prefix) {
        return $this->createQueryBuilder('transfer_order')
                ->select('transfer_order.number')
                ->where('transfer_order.number LIKE :value')
                ->orderBy('transfer_order.creationDate', 'DESC')
                ->setParameter('value', $prefix . '%')
                ->getQuery()
                ->getFirstResult()["number"] ?? null;
    }

    public function getByDates($dateMin, $dateMax) {

        $qb = $this->createQueryBuilder("transfer_order");

        $qb
            ->where("transfer_order.creationDate BETWEEN :dateMin AND :dateMax")
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);

        return $qb
            ->getQuery()
            ->getResult();
    }

}
