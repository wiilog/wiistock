<?php

namespace App\Repository;

use App\Entity\TransferOrder;
use App\Entity\Utilisateur;
use App\Helper\QueryCounter;
use Doctrine\ORM\EntityRepository;

/**
 * @method TransferOrder|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransferOrder|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransferOrder[]    findAll()
 * @method TransferOrder[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransferOrderRepository extends EntityRepository {

    public function findByParamsAndFilters($params, $filters, $receptionFilter) {
        $qb = $this->createQueryBuilder("transfer_order");
        $total =  QueryCounter::count($qb, "transfer_order");

        if ($receptionFilter) {
            $qb
                ->join('transfer_order.request', 'r')
                ->join('r.reception', 'reception')
                ->andWhere('reception.id = :reception')
                ->setParameter('reception', $receptionFilter);
        } else {
            // filtres sup
            foreach($filters as $filter) {
                switch($filter['field']) {
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
        }

        //Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb
                        ->andWhere('(' .
                            $exprBuilder->orX(
                                'search_request.number LIKE :value',
                                'search_requester.username LIKE :value',
                                'search_operator.username LIKE :value',
                                'search_destination.label LIKE :value',
                                'search_origin.label LIKE :value',
                                'search_status.nom LIKE :value'
                            )
                        . ')')
                        ->join('transfer_order.request', 'search_request')
                        ->leftJoin('search_request.requester', 'search_requester')
                        ->leftJoin('transfer_order.operator', 'search_operator')
                        ->leftJoin('search_request.status', 'search_status')
                        ->leftJoin('search_request.destination', 'search_destination')
                        ->leftJoin('search_request.origin', 'search_origin')
                        ->setParameter('value', '%' . $search . '%');
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
                        case 'origin':
                            $qb
                                ->leftJoin('transfer_order.request', 'order_requestOrigin')
                                ->leftJoin('order_requestOrigin.origin', 'order_requestOrigin_location')
                                ->orderBy('order_requestOrigin_location.label', $order);
                            break;
                        case 'destination':
                            $qb
                                ->leftJoin('transfer_order.request', 'order_requestDestination')
                                ->leftJoin('order_requestDestination.destination', 'order_requestDestination_location')
                                ->orderBy('order_requestDestination_location.label', $order);
                            break;
                        case 'requester':
                            $qb
                                ->leftJoin('transfer_order.request', 'order_requestRequester')
                                ->leftJoin('order_requestRequester.requester', 'order_requestRequester_user')
                                ->orderBy('order_requestRequester_user.username', $order);
                            break;
                        case 'creationDate':
                            $qb
                                ->orderBy("transfer_order.creationDate", $order);
                            break;
                        case 'validationDate':
                            $qb
                                ->leftJoin('transfer_order.request', 'order_requestValidationDate')
                                ->orderBy("order_requestValidationDate.validationDate", $order);
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
        $qb = $this->createQueryBuilder('transfer_order');

        $qb
            ->select('transfer_order.number')
            ->where('transfer_order.number LIKE :value')
            ->orderBy('transfer_order.creationDate', 'DESC')
            ->setParameter('value', $prefix . '%');

        $result = $qb
            ->getQuery()
            ->getResult();
        return $result ? $result[0]['number'] : null;
    }

    public function findByDates($dateMin, $dateMax) {

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

    public function getMobileTransferOrders(Utilisateur $user): array {
        return $this->createQueryBuilder('transferOrder')
            ->select('transferOrder.id AS id')
            ->addSelect('transferOrder.number AS number')
            ->addSelect('join_requester.username AS requester')
            ->addSelect('join_origin.label AS origin')
            ->addSelect('join_destination.label AS destination')
            ->join('transferOrder.status', 'join_orderStatus')
            ->join('transferOrder.request', 'join_transferRequest')
            ->join('join_transferRequest.requester', 'join_requester')
            ->join('join_transferRequest.destination', 'join_destination')
            ->join('join_transferRequest.origin', 'join_origin')
            ->andWhere('join_orderStatus.nom = :toTreatStatusLabel')
            ->andWhere('transferOrder.operator IS NULL OR transferOrder.operator = :operator')
            ->setParameters([
                'toTreatStatusLabel' => TransferOrder::TO_TREAT,
                'operator' => $user,
            ])
            ->getQuery()
            ->getResult();
    }

}
