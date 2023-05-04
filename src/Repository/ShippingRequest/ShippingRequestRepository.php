<?php

namespace App\Repository\ShippingRequest;

use App\Entity\ShippingRequest\ShippingRequest;
use App\Helper\QueryBuilderHelper;
use App\Service\VisibleColumnService;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

class ShippingRequestRepository extends EntityRepository {

    public function findByParamsAndFilters(InputBag $params, array $filters, VisibleColumnService $visibleColumnService, array $options = []): array
    {
        $qb = $this->createQueryBuilder("shipping_request");

        $total = QueryBuilderHelper::count($qb, 'shipping_request');

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

    public function getLastNumberByDate(string $date): ?string
    {
        $result = $this->createQueryBuilder('shipping_request')
            ->select('shipping_request.number')
            ->where('shipping_request.number LIKE :value')
            ->orderBy('shipping_request.createdAt', 'DESC')
            ->addOrderBy('shipping_request.number', 'DESC')
            ->setParameter('value', ShippingRequest::NUMBER_PREFIX . '-' . $date . '%')
            ->getQuery()
            ->execute();
        return $result ? $result[0]['number'] : null;
    }
}
