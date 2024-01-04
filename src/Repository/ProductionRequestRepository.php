<?php

namespace App\Repository;

use App\Entity\FiltreSup;
use App\Entity\ProductionRequest;
use App\Helper\QueryBuilderHelper;
use App\Service\VisibleColumnService;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;

/**
 * @extends EntityRepository<ProductionRequest>
 *
 * @method ProductionRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductionRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductionRequest[]    findAll()
 * @method ProductionRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductionRequestRepository extends EntityRepository {
    public function findByParamsAndFilters(InputBag $params, array $filters, VisibleColumnService $visibleColumnService, array $options = []): array
    {
        $qb = $this->createQueryBuilder('production_request')
            ->groupBy('production_request.id');

        $total = QueryBuilderHelper::count($qb, 'production_request');
        $dateChoice = Stream::from($filters)->find(static fn($filter) => $filter['field'] === 'date-choice')["value"] ?? '';

        // filtres sup
        foreach ($filters as $filter) {
            dump($filter);
            switch ($filter['field']) {
                case 'dateMin':
                    $filteredDate = match ($dateChoice){
                        'createdAt' => 'createdAt',
                        'expectedAt' => 'expectedAt',
                        default => 'createdAt'
                    };
                    $qb->andWhere("production_request.{$filteredDate} >= :filter_dateMin_value")
                        ->setParameter('filter_dateMin_value', $filter['value'] . ' 00.00.00');
                    break;
                case 'dateMax':
                    $filteredDate = match ($dateChoice){
                        'createdAt' => 'createdAt',
                        'expectedAt' => 'expectedAt',
                        default => 'createdAt'
                    };
                    $qb->andWhere("production_request.{$filteredDate} <= :filter_dateMax_value")
                        ->setParameter('filter_dateMax_value', $filter['value'] . ' 23:59:59');
                    break;
                case FiltreSup::FIELD_MULTIPLE_TYPES:
                    if(!empty($filter['value'])){
                        $value = Stream::explode(',', $filter['value'])
                            ->filter()
                            ->map(static fn($type) => explode(':', $type)[0])
                            ->toArray();
                        $qb
                            ->join('production_request.type', 'filter_type')
                            ->andWhere('filter_type.id in (:filter_type_value)')
                            ->setParameter('filter_type_value', $value);
                    }
                    break;
                case 'statuses-filter':
                case 'statut':
                    if(!empty($filter['value'])) {
                        $value = explode(',', $filter['value']);
                        $qb
                            ->join('production_request.status', 'filter_status')
                            ->andWhere('filter_status.id in (:status)')
                            ->setParameter('status', $value);
                    }
                    break;
                case 'createdBy':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('production_request.createdBy', 'filter_createdBy')
                        ->andWhere('filter_createdBy.id in (:filter_createdBy_values)')
                        ->setParameter('filter_createdBy_values', $value);
                    break;
                case 'manufacturingOrderNumber':
                    $value = $filter['value'];
                    $qb
                        ->andWhere($qb->expr()->like('production_request.manufacturingOrderNumber', ':filter_manufactoringOrderNumber_value'))
                        ->setParameter('filter_manufactoringOrderNumber_value', '%' . $value . '%');
                    break;
                case 'attachmentsAssigned':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('production_request.attachments', 'filter_attachments')
                        ->andWhere('filter_attachments.id in (:filter_attachments_value)')
                        ->setParameter('filter_attachments_value', $value);
                    break;
            }
        }

        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    dump($search);
                    $conditions = [
                        "number" => "production_request.number LIKE :search_value",
                        "createdAt" => "DATE_FORMAT(production_request.createdAt, '%d/%m/%Y') LIKE :search_value",
                        "treatedBy" => "search_treatedBy.username LIKE :search_value",
                        "type" => "search_type.label LIKE :search_value",
                        "status" => "search_status.nom LIKE :search_value",
                        "expectedAt" => "DATE_FORMAT(production_request.expectedAt, '%d/%m/%Y') LIKE :search_value",
                        "dropLocation" => "search_dropLocation.label LIKE :search_value",
                        "lineNumber" => "production_request.lineNumber LIKE :search_value",
                        "manufacturingOrderNumber" => "production_request.manufacturingOrderNumber LIKE :search_value",
                        "productArticleCode" => "production_request.productArticleCode LIKE :search_value",
                        "quantity" => "production_request.quantity LIKE :search_value",
                        "emergency" => "production_request.emergency LIKE :search_value",
                        "projectNumber" => "production_request.projectNumber LIKE :search_value",
                        "comment" => "production_request.comment LIKE :search_value",
                    ];

                    $visibleColumnService->bindSearchableColumns($conditions, 'productionRequest', $qb, $options['user'], $search);

                    $qb
                        ->leftJoin('production_request.status', 'search_status')
                        ->leftJoin('production_request.treatedBy', 'search_treatedBy')
                        ->leftJoin('production_request.dropLocation', 'search_dropLocation')
                        ->leftJoin('production_request.type', 'search_type')
                        ;
                }
            }

            $filtered = QueryBuilderHelper::count($qb, 'production_request');

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];

                    if ($column === 'number') {
                        $qb->orderBy('production_request.number', $order);
                    } else if ($column === 'createdAt') {
                        $qb->orderBy('production_request.createdAt', $order);
                    } else if ($column === 'treatedBy') {
                        $qb
                            ->leftJoin('production_request.treatedBy', 'user')
                            ->orderBy('user.username', $order);
                    } else if ($column === 'type') {
                        $qb
                            ->leftJoin('production_request.type', 'type')
                            ->orderBy('type.label', $order);
                    } else if ($column === 'status') {
                        $qb
                            ->leftJoin('production_request.status', 'status')
                            ->orderBy('status.nom', $order);
                    } else if ($column === 'expectedAt') {
                        $qb->orderBy('production_request.expectedAt', $order);
                    } else if ($column === 'dropLocation') {
                        $qb
                            ->leftJoin('production_request.dropLocation', 'location')
                            ->orderBy('location.label', $order);
                    } else if ($column === 'lineNumber') {
                        $qb->orderBy('production_request.lineNumber', $order);
                    } else if ($column === 'manufacturingOrderNumber') {
                        $qb->orderBy('production_request.manufacturingOrderNumber', $order);
                    } else if ($column === 'productArticleCode') {
                        $qb->orderBy('production_request.productArticleCode', $order);
                    } else if ($column === 'quantity') {
                        $qb->orderBy('production_request.quantity', $order);
                    } else if ($column === 'emergency') {
                        $qb->orderBy('production_request.emergency', $order);
                    } else if ($column === 'projectNumber') {
                        $qb->orderBy('production_request.projectNumber', $order);
                    } else if ($column === 'comment') {
                        $qb->orderBy('production_request.comment', $order);
                    }
                }
            }
        }

        // counts the filtered elements
        $filtered = QueryBuilderHelper::count($qb, 'production_request');

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
}
