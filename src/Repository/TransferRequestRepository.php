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
        $qb = $this->createQueryBuilder("t");
        $total =  QueryCounter::count($qb, "t");

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'status':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('t.status', 't')
                        ->andWhere('t.id in (:status)')
                        ->setParameter('status', $value);
                    break;
                case 'utilisateurs':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('t.requester', 'req')
                        ->andWhere("req.id in (:id)")
                        ->setParameter('id', $value);
                    break;
                case 'dateMin':
                    $qb->andWhere('t.creationDate >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb->andWhere('t.creationDate <= :dateMax')
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
                        ->andWhere(
                            $exprBuilder->orX(
                                't.number LIKE :value',
                                'req_search.username LIKE :value',
                                'status_search.nom LIKE :value'
                            )
                        )
                        ->setParameter('value', '%' . $search . '%')
                        ->leftJoin('t.requester', 'req_search')
                        ->leftJoin('t.status', 'status_search');
                }
            }

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];

                    switch ($column) {
                        case 'number':
                            $qb->orderBy("t.number", $order);
                            break;
                        case 'status':
                            $qb
                                ->leftJoin('t.status', 's2')
                                ->orderBy('s2.nom', $order);
                            break;
                        case 'destination':
                            $qb
                                ->leftJoin('t.destination', 'd2')
                                ->orderBy('d2.label', $order);
                            break;
                        case 'requester':
                            $qb
                                ->leftJoin('t.requester', 'r2')
                                ->orderBy('r2.username', $order);
                            break;
                        case 'creationDate':
                            $qb->orderBy("t.creationDate", $order);
                            break;
                        case 'validationDate':
                            $qb->orderBy("t.validationDate", $order);
                            break;
                        default:
                            $qb->orderBy('t.' . $column, $order);
                            break;
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered =  QueryCounter::count($qb, 't');

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

}
