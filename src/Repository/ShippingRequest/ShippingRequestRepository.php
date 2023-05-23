<?php

namespace App\Repository\ShippingRequest;

use App\Entity\Statut;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Helper\QueryBuilderHelper;
use App\Service\VisibleColumnService;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\HttpFoundation\InputBag;

class ShippingRequestRepository extends EntityRepository {

    public function findByParamsAndFilters(InputBag $params, array $filters, VisibleColumnService $visibleColumnService, array $options = []): array
    {
        $qb = $this->createQueryBuilder("shipping_request");

        $total = QueryBuilderHelper::count($qb, 'shipping_request');
        //filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statut':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('shipping_request.status', 'filter_status')
                        ->andWhere('filter_status.id IN (:statut)')
                        ->setParameter('statut', $value);
                    break;
                case 'customerOrderNumber':
                    $qb
                        ->andWhere('shipping_request.customerOrderNumber LIKE :customerOrderNumber')
                        ->setParameter('customerOrderNumber', '%' . $filter['value'] . '%');
                    break;
                case 'carriers':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('shipping_request.carrier', 'carrier')
                        ->andWhere('carrier.id IN (:carrierId)')
                        ->setParameter('carrierId', $value);
                    break;
                case 'utilisateurs':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('shipping_request.requesters', 'filter_requester')
                        ->andWhere("filter_requester.id in (:filter_requester_username_value)")
                        ->setParameter('filter_requester_username_value', $value);
                    break;
                case 'date-choice':
                    $chosenDate = $filter['value'];
                    foreach ($filters as $filter) {
                        switch ($filter['field']) {
                            case 'dateMin':
                                $qb->andWhere('shipping_request.' . $chosenDate . ' >= :filter_dateMin_value' )
                                    ->setParameter('filter_dateMin_value', $filter['value'] . ' 00:00:00');
                                break;
                            case 'dateMax':
                                $qb->andWhere('shipping_request.' . $chosenDate . ' <= :filter_dateMax_value')
                                    ->setParameter('filter_dateMax_value', $filter['value'] . ' 23:59:59');
                                break;
                        }
                    }
                    break;
            }
        }


        //Filter search
        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $conditions = [
                        "number" => "shipping_request.number LIKE :search_value",
                        "trackingNumber" => "shipping_request.trackingNumber LIKE :search_value",
                        "status" => "search_status.code LIKE :search_value",
                        "createdAt" => "DATE_FORMAT(shipping_request.createdAt, '%d/%m/%Y') LIKE :search_value",
                        "requestCaredAt" => "DATE_FORMAT(shipping_request.requestCaredAt, '%d/%m/%Y') LIKE :search_value",
                        "validatedAt" => "DATE_FORMAT(shipping_request.validatedAt, '%d/%m/%Y') LIKE :search_value",
                        "plannedAt" => "DATE_FORMAT(shipping_request.plannedAt, '%d/%m/%Y') LIKE :search_value",
                        "expectedPickedAt" => "DATE_FORMAT(shipping_request.expectedPickedAt, '%d/%m/%Y') LIKE :search_value",
                        "treatedAt" => "DATE_FORMAT(shipping_request.treatedAt, '%d/%m/%Y') LIKE :search_value",
                        "requesters" => "search_requesters.username LIKE :search_value",
                        "customerOrderNumber" => "shipping_request.customerOrderNumber LIKE :search_value",
                        "customerName" => "shipping_request.customerName LIKE :search_value",
                        "customerRecipient" => "shipping_request.customerRecipient LIKE :search_value",
                        "customerPhone" => "shipping_request.customerPhone LIKE :search_value",
                        "customerAddress" => "shipping_request.customerAddress LIKE :search_value",
                        "carrier" => "search_carrier.label LIKE :search_value",
                        "shipment" => "shipping_request.shipment LIKE :search_value",
                        "comment" => "shipping_request.comment LIKE :search_value",
                        "grossWeight" => "shipping_request.grossWeight LIKE :search_value",
                    ];

                    $visibleColumnService->bindSearchableColumns($conditions, 'shippingRequest', $qb, $options['user'], $search);

                    $qb
                        ->leftJoin('shipping_request.status', 'search_status')
                        ->leftJoin('shipping_request.requesters', 'search_requesters')
                        ->leftJoin('shipping_request.carrier', 'search_carrier');
                }
            }

            $filtered = QueryBuilderHelper::count($qb, 'shipping_request');

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];

                    if ($column === 'number') {
                        $qb->orderBy('shipping_request.number', $order);
                    } else if ($column === 'trackingNumber') {
                        $qb->orderBy('shipping_request.trackingNumber', $order);
                    } else if ($column === 'status') {
                        $qb
                            ->leftJoin('shipping_request.status', 'status')
                            ->orderBy('status.code', $order);
                    } else if ($column === 'createdAt') {
                        $qb->orderBy('shipping_request.createdAt', $order);
                    } else if ($column === 'requestCaredAt') {
                        $qb->orderBy('shipping_request.requestCaredAt', $order);
                    } else if ($column === 'validatedAt') {
                        $qb->orderBy('shipping_request.validatedAt', $order);
                    } else if ($column === 'plannedAt') {
                        $qb->orderBy('shipping_request.plannedAt', $order);
                    } else if ($column === 'expectedPickedAt') {
                        $qb->orderBy('shipping_request.expectedPickedAt', $order);
                    } else if ($column === 'treatedAt') {
                        $qb->orderBy('shipping_request.treatedAt', $order);
                    } else if ($column === 'customerOrderNumber') {
                        $qb->orderBy('shipping_request.customerOrderNumber', $order);
                    } else if ($column === 'customerName') {
                        $qb->orderBy('shipping_request.customerName', $order);
                    } else if ($column === 'carrier') {
                        $qb
                            ->leftJoin('shipping_request.carrier', 'order_carrier')
                            ->orderBy('order_carrier.label', $order);
                    } else if ($column === 'customerAddress') {
                        $qb->orderBy('shipping_request.customerAddress', $order);
                    } else if ($column === 'customerRecipient') {
                        $qb->orderBy('shipping_request.customerRecipient', $order);
                    } else if ($column === 'customerPhone') {
                        $qb->orderBy('shipping_request.customerPhone', $order);
                    } else if ($column === 'shipment') {
                        $qb->orderBy('shipping_request.shipment', $order);
                    } else if ($column === 'carrying') {
                        $qb->orderBy('shipping_request.carrying', $order);
                    } else if ($column === 'comment') {
                        $qb->orderBy('shipping_request.comment', $order);
                    } else if ($column === 'grossWeight') {
                        $qb->orderBy('shipping_request.grossWeight', $order);
                    } else if ($column === 'freeDelivery') {
                        $qb->orderBy('shipping_request.freeDelivery', $order);
                    } else if ($column === 'compliantArticles') {
                        $qb->orderBy('shipping_request.compliantArticles', $order);
                    }
                }
            }
        }

        // counts the filtered elements
        $filtered = QueryBuilderHelper::count($qb, 'shipping_request');

        if (!empty($params)) {
            if ($params->getInt('start')) {
                $qb->setFirstResult($params->getInt('start'));
            }

            $pageLength = $params->getInt('length') ? $params->getInt('length') : 100;
            if ($pageLength) {
                $qb->setMaxResults($pageLength);
            }
        }

        return [
            'data' => $qb->getQuery()->getResult(),
            'count' => $filtered,
            'total' => $total
        ];
    }

    public function iterateShippingRequests(): iterable {
        return $this->createQueryBuilder('shipping_request')
            ->select('shipping_request.number')
            ->addSelect('join_status.code AS statusCode')
            ->addSelect('shipping_request.createdAt')
            ->addSelect('shipping_request.validatedAt')
            ->addSelect('shipping_request.plannedAt')
            ->addSelect('shipping_request.expectedPickedAt')
            ->addSelect('shipping_request.treatedAt')
            ->addSelect('shipping_request.requestCaredAt')
            ->addSelect("GROUP_CONCAT(DISTINCT join_requesters.username SEPARATOR ', ') AS requesterNames")
            ->addSelect('shipping_request.customerOrderNumber')
            ->addSelect('shipping_request.freeDelivery')
            ->addSelect('shipping_request.compliantArticles')
            ->addSelect('shipping_request.customerName')
            ->addSelect('shipping_request.customerRecipient')
            ->addSelect('shipping_request.customerPhone')
            ->addSelect('shipping_request.customerAddress')
            ->addSelect('IF(COUNT(join_packLine.id) = 0, NULL, join_pack.code) as packCode')
            ->addSelect('join_packNature.label AS nature')
            ->addSelect('join_expectedLine_RefArticle.reference as refArticle')
            ->addSelect('join_expectedLine_RefArticle.reference as refArticleLibelle')
            ->addSelect('IF(COUNT(join_packLine.id) = 0, NULL, join_article.label) as article')
            ->addSelect('IF(COUNT(join_packLine.id) = 0, NULL, join_line.quantity) as articleQuantity')
            ->addSelect('join_expectedLine.price')
            ->addSelect('join_expectedLine.weight')
            ->addSelect('IF(COUNT(join_packLine.id) = 0, NULL, (join_line.quantity * join_expectedLine.price)) as totalAmount')
            ->addSelect('join_expectedLine_RefArticle.dangerous_goods')
            ->addSelect('join_expectedLine_RefArticle.onu_code')
            ->addSelect('join_expectedLine_RefArticle.product_class')
            ->addSelect('join_expectedLine_RefArticle.ndp_code')
            ->addSelect('shipping_request.shipment')
            ->addSelect('shipping_request.carrying')
            ->addSelect('COUNT(join_packLine.id) AS nbPacks')
            ->addSelect('IF(COUNT(join_packLine.id) = 0, NULL, join_packLine.size) as size')
            ->addSelect('SUM(join_expectedLine.weight) AS totalWeight')
            ->addSelect('shipping_request.grossWeight')
            ->addSelect('SUM(join_expectedLine.price) AS totalSum')
            ->addSelect('join_carrier.label AS carrierName')
            ->leftJoin('shipping_request.status', 'join_status')
            ->leftJoin('shipping_request.carrier', 'join_carrier')
            ->leftJoin('shipping_request.requesters', 'join_requesters')
            ->leftJoin('shipping_request.packLines', 'join_packLine')
            ->leftJoin('join_packLine.pack', 'join_pack')
            ->leftJoin('join_pack.nature', 'join_packNature')
            ->leftJoin('shipping_request.expectedLines', 'join_expectedLine')
            ->leftJoin('join_expectedLine.referenceArticle', 'join_expectedLine_RefArticle')
            ->leftJoin('join_expectedLine.lines', 'join_line')
            ->leftJoin('join_line.article', 'join_article')
            ->addGroupBy('shipping_request.number')
            ->addGroupBy('join_packLine.id')
            ->addGroupBy('join_pack.code')
            ->addGroupBy('join_packNature.label')
            ->addGroupBy('join_line.id')
            ->addGroupBy('join_expectedLine.id')
            ->addGroupBy('join_expectedLine_RefArticle.reference')
            ->getQuery()
            ->getResult();
    }

    public function getLastNumberByDate(string $date): ?string
    {
        $result = $this->createQueryBuilder('shipping_request')
            ->select('shipping_request.number')
            ->andWhere('shipping_request.number LIKE :value')
            ->orderBy('shipping_request.createdAt', 'DESC')
            ->addOrderBy('shipping_request.number', 'DESC')
            ->setParameter('value', ShippingRequest::NUMBER_PREFIX . '-' . $date . '%')
            ->getQuery()
            ->execute();
        return $result ? $result[0]['number'] : null;
    }

    /**
     * @param array $types
     * @param array $statuses
     * @return DateTime|null
     * @throws NonUniqueResultException
     */
    public function getOlderDateToTreat(array $types = [],
                                        array $statuses = []): ?DateTime {
        if (!empty($statuses)) {
            $res = $this
                ->createQueryBuilder('shipping_request')
                ->select('shipping_request.validatedAt AS date')
                ->innerJoin('shipping_request.status', 'status')
                ->andWhere('status IN (:statuses)')
                ->addOrderBy('shipping_request.validatedAt', 'ASC')
                ->setParameter('statuses', $statuses)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            return $res['date'] ?? null;
        }
        else {
            return null;
        }
    }
}
