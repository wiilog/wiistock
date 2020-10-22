<?php

namespace App\Repository;

use App\Entity\TransferRequest;
use App\Helper\QueryCounter;
use Doctrine\ORM\EntityRepository;

/**
 * @method TransferRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransferRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransferRequest[]    findAll()
 * @method TransferRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransferRequestRepository extends EntityRepository {

    public function findByParamsAndFilters($params, $filters) {
        $qb = $this->createQueryBuilder("transfer_request");
        $total = QueryCounter::count($qb, "transfer_request");

        // filtres sup
        foreach($filters as $filter) {
            switch($filter['field']) {
                case 'statut':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('transfer_request.status', 'filter_status')
                        ->andWhere('filter_status.id in (:status)')
                        ->setParameter('status', $value);
                    break;
                case 'requesters':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('transfer_request.requester', 'filter_requester')
                        ->andWhere("filter_requester.id in (:filter_value_requester)")
                        ->setParameter('filter_value_requester', $value);
                    break;
                case 'operators':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('transfer_request.order', 'filter_order')
                        ->join('filter_order.operator', 'filter_orderOperator')
                        ->andWhere("filter_orderOperator.id in (:filter_value_operators)")
                        ->setParameter('filter_value_operators', $value);
                    break;
                case 'dateMin':
                    $qb->andWhere('transfer_request.creationDate >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb->andWhere('transfer_request.creationDate <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
            }
        }

        //Filter search
        if(!empty($params)) {
            if(!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if(!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb
                        ->andWhere('(' .
                            $exprBuilder->orX(
                                'transfer_request.number LIKE :value',
                                'search_requester.username LIKE :value',
                                'search_origin.label LIKE :value',
                                'search_destination.label LIKE :value',
                                'search_status.nom LIKE :value'
                            )
                        . ')')
                        ->leftJoin('transfer_request.requester', 'search_requester')
                        ->leftJoin('transfer_request.origin', 'search_origin')
                        ->leftJoin('transfer_request.destination', 'search_destination')
                        ->leftJoin('transfer_request.status', 'search_status')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if(!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if(!empty($order)) {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];

                    switch($column) {
                        case 'number':
                            $qb
                                ->orderBy("transfer_request.number", $order);
                            break;
                        case 'status':
                            $qb
                                ->leftJoin('transfer_request.status', 'order_status')
                                ->orderBy('order_status.nom', $order);
                            break;
                        case 'origin':
                            $qb
                                ->leftJoin('transfer_request.origin', 'order_origin')
                                ->orderBy('order_origin.label', $order);
                            break;
                        case 'destination':
                            $qb
                                ->leftJoin('transfer_request.destination', 'order_destination')
                                ->orderBy('order_destination.label', $order);
                            break;
                        case 'requester':
                            $qb
                                ->leftJoin('transfer_request.requester', 'order_requester')
                                ->orderBy('order_requester.username', $order);
                            break;
                        case 'creationDate':
                            $qb
                                ->orderBy("transfer_request.creationDate", $order);
                            break;
                        case 'validationDate':
                            $qb
                                ->orderBy("transfer_request.validationDate", $order);
                            break;
                        default:
                            $qb
                                ->orderBy('transfer_request.' . $column, $order);
                            break;
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryCounter::count($qb, 'transfer_request');

        if($params) {
            if(!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if(!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

        return [
            'data' => $qb->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $total
        ];
    }

    public function findByStatutLabelAndUser($statutLabel, $user)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT t
            FROM App\Entity\TransferRequest t
            JOIN t.status s
            WHERE s.nom = :statutLabel AND t.requester = :user "
        )->setParameters([
            'statutLabel' => $statutLabel,
            'user' => $user,
        ]);
        return $query->execute();
    }

    public function getLastTransferNumberByPrefix($prefix) {
        return $this->createQueryBuilder('transfer_request')
            ->select('transfer_request.number')
            ->where('transfer_request.number LIKE :value')
            ->orderBy('transfer_request.creationDate', 'DESC')
            ->setParameter('value', $prefix . '%')
            ->getQuery()
            ->getFirstResult()["number"] ?? null;
    }

    public function findByDates($dateMin, $dateMax) {
        $qb = $this->createQueryBuilder("transfer_request");

        $qb
            ->where("transfer_request.creationDate BETWEEN :dateMin AND :dateMax")
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);

        return $qb
            ->getQuery()
            ->getResult();
    }
}
