<?php

namespace App\Repository\Tracking;

use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\IOT\Sensor;
use App\Entity\LocationGroup;
use App\Entity\Reception;
use App\Entity\ReceptionLine;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingEvent;
use App\Entity\Tracking\TrackingMovement;
use App\Helper\QueryBuilderHelper;
use App\Service\Tracking\TrackingTimerEvent;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;

class PackRepository extends EntityRepository
{
    private const DtToDbLabels = [
        'packNum' => 'code',
        'packNature' => 'packNature',
        'packLastDate' => 'packLastDate',
        'packOrigin' => 'packOrigin',
        'packLocation' => 'packLocation',
        'quantity' => 'quantity',
        'arrivageType' => 'arrivage',
        'project' => 'project',
        'limitTreatmentDate' => 'limitTreatmentDate',
        'carrier' => 'carrier',
        'supplier' => 'supplier',
    ];

    public function countPacksByDates(DateTime $dateMin,
                                      DateTime $dateMax,
                                      bool     $groupByNature = false,
                                      array    $arrivalStatusesFilter = [],
                                      array    $arrivalTypesFilter = []) {
        $queryBuilder = $this->createQueryBuilder('pack')
            ->select('COUNT(pack) AS count')
            ->join('pack.arrivage', 'arrival')
            ->where('arrival.date BETWEEN :dateMin AND :dateMax')
            ->andWhere('pack.groupIteration IS NULL')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);

        if ($groupByNature) {
            $queryBuilder = $queryBuilder
                ->addSelect('nature.id AS natureId')
                ->leftJoin('pack.nature', 'nature')
                ->groupBy('nature.id');
        }

        if (!empty($arrivalStatusesFilter)) {
            $queryBuilder
                ->andWhere('arrival.statut IN (:arrivalStatuses)')
                ->setParameter('arrivalStatuses', $arrivalStatusesFilter);
        }

        if (!empty($arrivalTypesFilter)) {
            $queryBuilder
                ->andWhere('arrival.type IN (:arrivalTypes)')
                ->setParameter('arrivalTypes', $arrivalTypesFilter);
        }

        $query = $queryBuilder->getQuery();

        return $groupByNature
            ? $query->getScalarResult()
            : $query->getSingleScalarResult();
    }


    public function iteratePacksByDates(DateTime $dateMin, DateTime $dateMax): iterable {
        $queryBuilder = $this->createQueryBuilder('pack')
            ->select('pack.code AS code')
            ->addSelect('join_nature.label AS nature')
            ->addSelect('join_last_action.datetime AS lastMvtDate')
            ->addSelect('join_last_action.id AS fromTo')
            ->addSelect('join_location.label AS location')
            ->addSelect('join_group.code AS groupCode')
            ->addSelect('pack.groupIteration AS groupIteration')
            ->addSelect('pack.weight AS weight')
            ->addSelect('pack.volume AS volume')
            ->addSelect('join_supplier.nom AS supplier')
            ->addSelect('join_carrier.label AS carrier')
            ->addSelect('join_arrival.numeroCommandeList AS orderNumbers')
            ->andWhere('join_last_action.datetime BETWEEN :dateMin AND :dateMax')
            ->leftJoin('pack.lastAction', 'join_last_action')
            ->leftJoin('pack.group', 'join_group')
            ->leftJoin('pack.arrivage', 'join_arrival')
            ->leftJoin('join_arrival.fournisseur', 'join_supplier')
            ->leftJoin('join_arrival.transporteur', 'join_carrier')
            ->leftJoin('join_last_action.emplacement', 'join_location')
            ->leftJoin('pack.nature', 'join_nature');
        return QueryBuilderHelper::addTrackingEntities($queryBuilder, 'join_last_action')
            ->setParameter('dateMin', $dateMin)
            ->setParameter('dateMax', $dateMax)
            ->getQuery()
            ->toIterable();
    }

    /**
     * @return array{
     *     data: Pack[],
     *     count: int,
     *     total: int,
     * }
     */
    public function getPackContentFiltered(InputBag $params, Pack $containerPack): array {
        $queryBuilder = $this->createQueryBuilder("contained_pack")
            ->innerJoin('contained_pack.group', 'join_container_pack', Join::WITH, 'contained_pack.group = :containerPack')
            ->leftJoin('contained_pack.nature', 'join_pack_nature')
            ->setParameter('containerPack', $containerPack);

        $total = QueryBuilderHelper::count($queryBuilder, 'contained_pack');

        if (!empty($params->all('search'))) {
            $search = $params->all('search')['value'];
            if (!empty($search)) {
                $exprBuilder = $queryBuilder->expr();
                $queryBuilder
                    ->andWhere($exprBuilder->orX(
                        'contained_pack.code LIKE :value',
                        'join_pack_nature.label LIKE :value',
                        'contained_pack.quantity LIKE :value',
                    ))
                    ->setParameter('value', '%' . $search . '%');
            }
        }

        $queryBuilder
            ->addSelect("COUNT_OVER(contained_pack.id) AS __query_count")
            ->leftJoin("contained_pack.currentTrackingDelay", "tracking_delay")
            // We put logistic units without limit treatment date at the end
            // That is to say we put logistic unit without tracking delay or with a paused tracking delay at the end
            ->orderBy("CASE WHEN tracking_delay.limitTreatmentDate IS NULL THEN 1 ELSE 0 END", Order::Ascending->value)
            ->addOrderBy("tracking_delay.limitTreatmentDate", Order::Ascending->value);

        if ($params->getInt('start')) {
            $queryBuilder->setFirstResult($params->getInt('start'));
        }

        $pageLength = $params->getInt('length') ? $params->getInt('length') : 100;
        if ($pageLength) {
            $queryBuilder->setMaxResults($pageLength);
        }

        $queryResult = $queryBuilder
            ->getQuery()
            ->getResult();

        return [
            'data' => Stream::from($queryResult)
                ->map(static fn(array $item) => $item[0])
                ->toArray(),
            'count' => $queryResult[0]['__query_count'] ?? 0,
            'total' => $total
        ];
    }



    public function findByParamsAndFilters(InputBag $params, $filters, array $options = []): array {
        $queryBuilder = $this->createQueryBuilder('pack');

        $queryBuilder
            ->leftJoin('pack.article', 'article')
            ->andWhere('article.currentLogisticUnit IS NULL');
        $countTotal = QueryBuilderHelper::count($queryBuilder, 'pack');

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'emplacement':
                    $emplacementValue = !is_array($filter['value'])
                        ? explode(',', $filter['value'])
                        : $filter['value'];
                    $queryBuilder
                        ->join('pack.lastAction', 'mFilter0')
                        ->join('mFilter0.emplacement', 'e')
                        ->andWhere('e.id IN (:location)')
                        ->setParameter('location', $emplacementValue, Connection::PARAM_INT_ARRAY);
                    break;
                case 'dateMin':
                    $queryBuilder
                        ->join('pack.lastAction', 'mFilter1')
                        ->andWhere('mFilter1.datetime >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $queryBuilder
                        ->join('pack.lastAction', 'mFilter2')
                        ->andWhere('mFilter2.datetime <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
                case 'UL':
                    $queryBuilder
                        ->andWhere('pack.code LIKE :UL')
                        ->setParameter('UL', '%' . $filter['value'] . '%');
                    break;
                case 'numArrivage':
                    $queryBuilder
                        ->join('pack.arrivage', 'a')
                        ->andWhere('a.numeroArrivage LIKE :arrivalNumber')
                        ->setParameter('arrivalNumber', '%' . $filter['value'] . '%');
                    break;
                case 'type':
                    $type = $filter['value'];
                    switch ($type) {
                        case 'pack':
                            $queryBuilder
                                ->andWhere('pack.groupIteration IS NULL');
                            break;
                        case 'group':
                            $queryBuilder
                                ->andWhere('pack.groupIteration IS NOT NULL');
                            break;
                        default:
                            break;
                    }
                    break;
                case 'natures':
                    $natures = !is_array($filter['value'])
                        ? explode(',', $filter['value'])
                        : $filter['value'];
                    $queryBuilder
                        ->join('pack.nature', 'natureFilter')
                        ->andWhere('natureFilter.id IN (:naturesFilter)')
                        ->setParameter('naturesFilter', $natures, Connection::PARAM_INT_ARRAY);
                    break;
                case 'project':
                    $queryBuilder
                        ->join('pack.project', 'projectFilter')
                        ->andWhere('projectFilter.id LIKE :projectCode')
                        ->setParameter('projectCode', $filter['value']);
                    break;
                case 'receiptAssociation':
                    $queryBuilder
                        ->join('pack.receiptAssociations', 'receiptAssociationFilter')
                        ->andWhere('receiptAssociationFilter.receptionNumber like :receiptAssociationCode')
                        ->setParameter('receiptAssociationCode', '%' . $filter['value'] . '%');
                    break;
                case FiltreSup::FIELD_PACK_WITH_TRACKING:
                    if ($filter['value']) {
                        // only pack with a current tracking delay
                        $queryBuilder->join('pack.currentTrackingDelay', 'filter_pack_with_tracking_tracking_delay');
                    }
                    break;
                case 'trackingEventTypes':

                    $events = $filter['value'];
                    $expr = $queryBuilder->expr();
                    $orX = $expr->orX();

                    if (in_array(null, $events, true)) {
                        $orX->add('filter_tracking_event_tracking_delay.lastTrackingEvent IS NULL');
                    }

                    $filteredEvents = Stream::from($events)
                        ->filter(static fn($event) => $event !== null)
                        ->toArray();
                    if (!empty($filteredEvents)) {
                        $orX->add('filter_tracking_event_tracking_delay.lastTrackingEvent IN (:events)');
                        $queryBuilder->setParameter('events', $filteredEvents);
                    }

                    $queryBuilder
                        ->innerJoin('pack.currentTrackingDelay', 'filter_tracking_event_tracking_delay')
                        ->andWhere($orX);
                    break;
                default:
                    break;
            }
        }

        // Filter bar search
        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $fields = $options["fields"] ?? [];
                    $searchParams = [
                        "arrival_type.label LIKE :value",
                        "child_articles_search.barCode LIKE :value"
                    ];

                    $queryBuilder
                        ->leftJoin('pack.arrivage', 'search_arrival')
                        ->leftJoin('pack.lastAction', 'search_last_action')
                        ->leftJoin('search_arrival.type', 'arrival_type')
                        ->leftJoin('pack.childArticles', 'child_articles_search');

                    foreach ($fields as $field) {
                        if ($field['fieldVisible'] ?? false) {
                            switch ($field['name'] ?? null) {
                                case "code":
                                    $searchParams[] = 'pack.code LIKE :value';
                                    break;
                                case "location":
                                    $queryBuilder->leftJoin('search_last_action.emplacement', 'search_last_action_location');
                                    $searchParams[] = 'search_last_action_location.label LIKE :value';
                                    break;
                                case "nature":
                                    $queryBuilder->leftJoin('pack.nature', 'natureSearch');
                                    $searchParams[] = 'natureSearch.label LIKE :value';
                                    break;
                                case "arrivage":
                                    $searchParams[] = 'search_arrival.numeroArrivage LIKE :value';
                                    break;
                                case "receiptAssociation":
                                    $queryBuilder->leftJoin('pack.receiptAssociations', 'receipt_associations_search');
                                    $searchParams[] = 'receipt_associations_search.receptionNumber LIKE :value';
                                    break;
                                case "lastMovementDate":
                                    $searchParams[] = 'search_last_action.datetime LIKE :value';
                                    break;
                                case "project":
                                    $queryBuilder->leftJoin('pack.project', 'projectSearch');
                                    $searchParams[] = 'projectSearch.code LIKE :value';
                                    break;
                                case "quantity":
                                    $searchParams[] = 'pack.quantity LIKE :value';
                                    break;
                                case "truckArrivalNumber":
                                    $queryBuilder->leftJoin('search_arrival.truckArrival', 'truckArrivalSearch');
                                    $searchParams[] = 'truckArrivalSearch.number LIKE :value';

                                    $queryBuilder->leftJoin('search_arrival.truckArrivalLines', 'truckArrivalLinesSearch')
                                        ->leftJoin('truckArrivalLinesSearch.truckArrival', 'truckArrivalByLinesSearch');

                                    $searchParams[] = 'truckArrivalByLinesSearch.number LIKE :value';
                                    break;
                                case "group":
                                    $queryBuilder->leftJoin('pack.group', 'parentSearch');
                                    $searchParams[] = 'parentSearch.code LIKE :value';
                                    break;
                                case 'supplier':
                                    $queryBuilder->leftJoin('search_arrival.fournisseur', 'search_arrival_supplier');
                                    $searchParams[] = 'search_arrival_supplier.nom LIKE :value';
                                    break;
                                case 'carrier':
                                    $queryBuilder->leftJoin('search_arrival.transporteur', 'search_arrival_carrier');
                                    $searchParams[] = 'search_arrival_carrier.label LIKE :value';
                                    break;
                                case 'orderNumbers':
                                    $searchParams[] = 'search_arrival.numeroCommandeList LIKE :value';
                                    break;
                                default:
                                    break;
                            }
                        }
                    }

                    if (!empty($searchParams)) {
                        $queryBuilder
                            ->andWhere($queryBuilder->expr()->orX(...$searchParams))
                            ->setParameter('value', '%' . $search . '%');
                    }
                }
            }

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = self::DtToDbLabels[$params->all('columns')[$params->all('order')[0]['column']]['data']] ?? 'id';
                    if ($column === 'location') {
                        $queryBuilder
                            ->leftJoin('pack.lastAction', 'order_packLocation_pack_lastAction')
                            ->leftJoin('order_packLocation_pack_lastAction.emplacement', 'order_packLocation_pack_lastAction_emplacement')
                            ->orderBy('order_packLocation_pack_lastAction_emplacement.label', $order);
                    } else if ($column === 'nature') {
                        $queryBuilder = QueryBuilderHelper::joinTranslations($queryBuilder, $options['language'], $options['defaultLanguage'], ['nature'], ["order" => $order]);
                    } else if ($column === 'LastMovementDate') {
                        $queryBuilder
                            ->leftJoin('pack.lastAction', 'order_packLastDate_pack_lastAction')
                            ->orderBy('order_packLastDate_pack_lastAction.datetime', $order);
                    } else if ($column === 'origin') {
                        $queryBuilder
                            ->leftJoin('pack.arrivage', 'order_packOrigin_pack_arrivage')
                            ->orderBy('order_packOrigin_pack_arrivage.numeroArrivage', $order);
                    } else if ($column === 'type') {
                        $queryBuilder
                            ->leftJoin('pack.arrivage', 'order_arrivageType_pack_arrivage')
                            ->orderBy('order_arrivageType_pack_arrivage.type', $order);
                    } else if ($column === 'pairing') {
                        $queryBuilder
                            ->leftJoin('pack.pairings', 'order_pairing_pack_pairings')
                            ->orderBy('order_pairing_pack_pairings.active', $order);
                    } else if ($column === 'project') {
                        $queryBuilder
                            ->leftJoin('pack.project', 'order_project_pack_project')
                            ->orderBy('order_project_pack_project.code', $order);
                    } else if ($column === 'truckArrivalNumber') {
                        $queryBuilder
                            ->leftJoin('pack.arrivage', 'order_truckArrivalNumber_pack_arrivage')
                            ->orderBy('order_truckArrivalNumber_pack_arrivage.noTracking', $order);
                    } else if ($column === 'limitTreatmentDate') {
                        // Group by limit treatment date with not pause event first in ASC ordering
                        $queryBuilder
                            ->leftJoin('pack.currentTrackingDelay', 'order_trackingDelay')
                            ->orderBy('IF(order_trackingDelay IS NOT NULL, 0, 1)', $order)
                            ->addOrderBy('IF(order_trackingDelay.lastTrackingEvent IS NULL OR order_trackingDelay.lastTrackingEvent != :orderBy_pauseTrackingEvent, 0, 1)', $order)
                            ->addOrderBy('order_trackingDelay.limitTreatmentDate', $order)
                            ->setParameter('orderBy_pauseTrackingEvent', TrackingEvent::PAUSE);
                    } else if ($column === 'supplier') {
                        $queryBuilder
                            ->leftJoin('pack.arrivage', 'order_supplier_arrival')
                            ->leftJoin('order_supplier_arrival.fournisseur', 'order_supplier')
                            ->orderBy('order_supplier.nom', $order);
                    } else if ($column === 'carrier') {
                        $queryBuilder
                            ->leftJoin('pack.arrivage', 'order_carrier_arrival')
                            ->leftJoin('order_carrier_arrival.transporteur', 'order_carrier')
                            ->orderBy('order_carrier.label', $order);
                    } else if (property_exists(Pack::class, $column)) {
                        $queryBuilder
                            ->orderBy('pack.' . $column, $order);
                    }
                    $orderId = ($column === 'datetime')
                        ? $order
                        : 'DESC';
                    $queryBuilder->addOrderBy('pack.id', $orderId);
                }
            }
        }
        // compte éléments filtrés
        $countFiltered = QueryBuilderHelper::count(
            $queryBuilder,
            'pack',
            parametersToReset: ['orderBy_pauseTrackingEvent']
        );

        $queryBuilder
            ->select('pack');

        if ($params->getInt('start')) {
            $queryBuilder->setFirstResult($params->getInt('start'));
        }

        if ($params->getInt('length')) {
            $queryBuilder->setMaxResults($params->getInt('length'));
        }

        $query = $queryBuilder->getQuery();
        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    public function getCurrentPackOnLocations(array $locations, array $options = []) {
        $natures = $options['natures'] ?? [];
        $isCount = $options['isCount'] ?? true;
        $field = $options['field'] ?? 'pack.id';
        $start = $options['start'] ?? null;
        $limit = $options['limit'] ?? null;
        $order = $options['order'] ?? 'desc';
        $onlyLate = $options['onlyLate'] ?? false;
        $fromOnGoing = $options['fromOnGoing'] ?? false;

        $queryBuilder = $this->createQueryBuilder('pack');
        $queryBuilderExpr = $queryBuilder->expr();
        $queryBuilder
            ->select($isCount ? $queryBuilderExpr->count($field) : $field)
            ->leftJoin('pack.nature', 'nature')
            ->leftJoin('pack.arrivage', 'pack_arrival')
            ->leftJoin('pack.article', 'article')
            ->innerJoin('pack.lastOngoingDrop', 'lastOngoingDrop')
            ->innerJoin('lastOngoingDrop.emplacement', 'emplacement')
            ->andWhere('pack.groupIteration IS NULL');

        if ($fromOnGoing) {
            $queryBuilder
                ->addSelect("COALESCE(join_referenceArticle.reference, join_article_referenceArticle.reference) AS reference_reference")
                ->addSelect("COALESCE(join_referenceArticle.libelle, join_article.label) AS reference_label")
                ->leftJoin('pack.referenceArticle', 'join_referenceArticle')
                ->leftJoin('pack.article', 'join_article')
                ->leftJoin("join_article.articleFournisseur", "join_article_supplierArticle")
                ->leftJoin("join_article_supplierArticle.referenceArticle", "join_article_referenceArticle")
                ->leftJoin("pack.firstAction", "join_first_action");
            $queryBuilder = QueryBuilderHelper::addTrackingEntities($queryBuilder, "join_first_action");
        }

        if (!empty($locations)) {
            $queryBuilder
                ->andWhere(
                    $queryBuilderExpr->in('emplacement.id', ':locations')
                )
                ->setParameter('locations', $locations);
        }
        if (!empty($dateBracket)) {
            $queryBuilder
                ->andWhere(
                    $queryBuilderExpr->between('lastOngoingDrop.datetime', ':dateFrom', ':dateTo')
                )
                ->setParameter('dateFrom', $dateBracket['minDate'])
                ->setParameter('dateTo', $dateBracket['maxDate']);
        }
        if (!empty($natures)) {
            $queryBuilder
                ->andWhere(
                    $queryBuilderExpr->in('nature.id', ':natures')
                )
                ->setParameter('natures', $natures);
        }

        $queryBuilder->orderBy('lastOngoingDrop.datetime', $order);

        if ($onlyLate) {
            $queryBuilder
                ->andWhere(
                    $queryBuilderExpr->isNotNull('emplacement.dateMaxTime')
                );
        }

        if ($start) {
            $queryBuilder->setFirstResult((int)$start);
        }

        if ($limit) {
            $queryBuilder->setMaxResults((int)$limit);
        }

        if ($isCount) {
            $queryBuilder
                ->andWhere('article.currentLogisticUnit IS NULL');

            return $queryBuilder
                ->getQuery()
                ->getSingleScalarResult();
        }

        return $queryBuilder
            ->getQuery()
            ->execute();
    }

    public function countPacksByArrival(DateTime $from, DateTime $to) {
        $queryBuilder = $this->createQueryBuilder('pack');
        $queryBuilderExpr = $queryBuilder->expr();
        $queryBuilder
            ->select('count(pack.id) as nbUL')
            ->addSelect('nature.id AS natureId')
            ->addSelect('arrivage.id AS arrivageId')
            ->join('pack.nature', 'nature')
            ->join('pack.arrivage', 'arrivage')
            ->where($queryBuilderExpr->between('arrivage.date', ':dateFrom', ':dateTo'))
            ->andWhere('pack.groupIteration IS NULL')
            ->groupBy('nature.id')
            ->addGroupBy('arrivage.id')
            ->setParameter('dateFrom', $from)
            ->setParameter('dateTo', $to);

        $result = $queryBuilder->getQuery()->execute();

        return array_reduce(
            $result,
            function (array $carry, $counter) {
                $arrivageId = $counter['arrivageId'];
                $natureId = $counter['natureId'];
                $nbPacks = $counter['nbUL'];
                if (!isset($carry[$arrivageId])) {
                    $carry[$arrivageId] = [];
                }
                $carry[$arrivageId][$natureId] = intval($nbPacks);
                return $carry;
            },
            []
        );
    }

    public function getPacksById(array $packIds): array {
        $queryBuilder = $this->createQueryBuilder('pack');
        $exprBuilder = $queryBuilder->expr();
        return Stream::from(
            $queryBuilder
                ->select('pack.code AS ref_article')
                ->addSelect('join_type_last_ongoing_drop.code AS type')
                ->addSelect('join_location.label AS ref_emplacement')
                ->addSelect('join_last_ongoing_drop.datetime AS date')
                ->addSelect('join_last_ongoing_drop.quantity AS quantity')
                ->addSelect('join_nature.id AS nature_id')
                ->join('pack.lastOngoingDrop', 'join_last_ongoing_drop')
                ->leftJoin('pack.nature', 'join_nature')
                ->join('join_last_ongoing_drop.type', 'join_type_last_ongoing_drop')
                ->join('join_last_ongoing_drop.emplacement', 'join_location')
                ->andWhere('pack.groupIteration IS NULL')
                ->andWhere($exprBuilder->in('pack.id', ':packIds'))
                ->setParameter('packIds', $packIds)
                ->getQuery()
                ->getResult()
        )
            ->map(function ($pack) {
                $pack['date'] = isset($pack['date']) ? $pack['date']->format(DateTimeInterface::ATOM) : null;
                return $pack;
            })
            ->toArray();
    }

    public function findWithNoPairing(?string $term) {
        return $this->createQueryBuilder("pack")
            ->select("pack.id AS id, pack.code AS text")
            ->leftJoin("pack.pairings", "pairings")
            ->where("pairings.pack IS NULL OR pairings.active = 0")
            ->andWhere("pack.code LIKE :term")
            ->setParameter("term", "%$term%")
            ->setMaxResults(100)
            ->getQuery()
            ->getArrayResult();
    }

    public function getSensorPairingData(Pack $pack, int $start, int $count): array {
        $unionSQL = $this->createSensorPairingDataQueryUnion($pack);

        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();
        /** @noinspection SqlResolve */
        return $connection
            ->executeQuery("
                SELECT *
                FROM ($unionSQL) AS pairing
                ORDER BY `date` DESC
                LIMIT $count OFFSET $start
            ")
            ->fetchAllAssociative();
    }

    private function createSensorPairingDataQueryUnion(Pack $pack): string {
        $createQueryBuilder = function () {
            return $this->createQueryBuilder('pack')
                ->select('pairing.id AS pairingId')
                ->addSelect('sensorWrapper.name AS name')
                ->addSelect('(CASE WHEN sensorWrapper.deleted = false AND pairing.active = true AND (pairing.end IS NULL OR pairing.end > NOW()) THEN 1 ELSE 0 END) AS active')
                ->addSelect('pack.code AS entity')
                ->addSelect("'" . Sensor::PACK . "' AS entityType")
                ->addSelect('pack.id AS entityId')
                ->join('pack.pairings', 'pairing')
                ->join('pairing.sensorWrapper', 'sensorWrapper')
                ->where('pack = :pack');
        };

        $startQueryBuilder = $createQueryBuilder();
        $startQueryBuilder
            ->addSelect("pairing.start AS date")
            ->addSelect("'start' AS type")
            ->andWhere('pairing.start IS NOT NULL');

        $endQueryBuilder = $createQueryBuilder();
        $endQueryBuilder
            ->addSelect("pairing.end AS date")
            ->addSelect("'end' AS type")
            ->andWhere('pairing.end IS NOT NULL');

        $sqlAliases = [
            '/AS \w+_0/' => 'AS pairingId',
            '/AS \w+_1/' => 'AS name',
            '/AS \w+_2/' => 'AS active',
            '/AS \w+_3/' => 'AS entity',
            '/AS \w+_4/' => 'AS entityType',
            '/AS \w+_5/' => 'AS entityId',
            '/AS \w+_6/' => 'AS date',
            '/AS \w+_7/' => 'AS type',
            '/\?/' => $pack->getId(),
        ];

        $startSQL = $startQueryBuilder->getQuery()->getSQL();
        $startSQL = StringHelper::multiplePregReplace($sqlAliases, $startSQL);

        $endSQL = $endQueryBuilder->getQuery()->getSQL();
        $endSQL = StringHelper::multiplePregReplace($sqlAliases, $endSQL);

        $entityManager = $this->getEntityManager();
        $locationGroupRepository = $entityManager->getRepository(LocationGroup::class);
        $locationGroupSQL = $locationGroupRepository->createPackSensorPairingDataQueryUnion($pack);

        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $locationSQL = $locationRepository->createPackSensorPairingDataQueryUnion($pack);

        return "
            ($startSQL)
            UNION
            ($endSQL)
            UNION
            $locationGroupSQL
            UNION
            $locationSQL
        ";
    }

    public function countSensorPairingData(Pack $pack): int {
        $unionSQL = $this->createSensorPairingDataQueryUnion($pack);

        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();
        $unionQuery = $connection->executeQuery("
            SELECT COUNT(*) AS count
            FROM ($unionSQL) AS pairing
        ");
        $res = $unionQuery->fetchAllAssociative();
        return $res[0]['count'] ?? 0;
    }

    public function getQueryBuilderForSelect(?string $term, array $options = [], ?bool $withoutArticle = false): QueryBuilder {
        $exclude = $options['exclude'] ?? null;
        if ($exclude && !is_array($exclude)) {
            $exclude = [$exclude];
        }

        $dispatchId = $options['dispatchId'] ?? null;
        $limit = isset($options['limit']) ? intval($options['limit']) : null;

        $qb = $this->createQueryBuilder("pack")
            ->select("pack.id AS id")
            ->addSelect("pack.code AS text")
            ->addSelect("1 AS exists")
            ->addSelect("nature.id AS nature_id")
            ->addSelect("nature.label AS nature_label")
            ->addSelect("nature.defaultQuantityForDispatch AS nature_default_quantity_for_dispatch")
            ->addSelect("pack.weight AS weight")
            ->addSelect("pack.volume AS volume")
            ->addSelect("pack.comment AS comment")
            ->addSelect("DATE_FORMAT(last_action.datetime, '%d/%m/%Y %H:%i') AS lastMvtDate")
            ->addSelect("last_action_location.label AS lastLocation")
            ->addSelect("last_action_location.id AS lastLocationId")
            ->addSelect("last_action_user.username AS operator")
            ->addSelect("nature.defaultQuantityForDispatch AS defaultQuantityForDispatch")
            ->andWhere("pack.code LIKE :term")
            ->leftJoin("pack.nature", "nature")
            ->leftJoin("pack.lastAction", "last_action")
            ->leftJoin("last_action.emplacement", "last_action_location")
            ->leftJoin("last_action.operateur", "last_action_user")
            ->setParameter("term", "%$term%");

        if ($exclude) {
            $qb->andWhere("pack.code NOT IN (:exclude)")
                ->setParameter("exclude", $exclude);
        }

        if ($dispatchId) {
            $qb->leftJoin("pack.dispatchPacks", "dispatch_packs")
                ->andWhere("dispatch_packs.dispatch = :dispatch")
                ->setParameter("dispatch", $dispatchId);
        }

        if ($withoutArticle) {
            $qb->leftJoin("pack.article", "article")
                ->andWhere("article.id IS NULL");
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb;
    }

    public function getForSelect(?string $term, array $options = [], ?bool $withoutArticle = false)  {
        return self::getQueryBuilderForSelect($term, $options, $withoutArticle)->getQuery()->getResult();
    }

    public function iterateForSelect(?string $term, array $options = [], ?bool $withoutArticle = false): iterable {
        return self::getQueryBuilderForSelect($term, $options, $withoutArticle)
            ->getQuery()
            ->toIterable();
    }

    /**
     * @param int[] $waitingDays Number of days returned packs are waiting on their delivery point
     * @return Pack[]
     */
    public function findOngoingPacksOnDeliveryPoints(array $waitingDays): array {
        if (empty($waitingDays)) {
            throw new \RuntimeException("waitingDays shouldn't be empty");
        }

        $subQuery = $this->createQueryBuilder('pack')
            ->addSelect('DATEDIFF(NOW(), IF(dropGroupLocation.id IS NULL, lastOngoingDrop.datetime, MIN(movement.datetime))) AS packWaitingDays')
            ->innerJoin('pack.lastOngoingDrop', 'lastOngoingDrop')
            ->innerJoin('pack.arrivage', 'arrival')
            ->innerJoin('lastOngoingDrop.emplacement', 'dropLocation')
            ->leftJoin('dropLocation.locationGroup', 'dropGroupLocation')
            ->innerJoin('pack.trackingMovements', 'movement')
            ->innerJoin('movement.emplacement', 'movementLocation')
            ->innerJoin('movement.type', 'movementType')
            ->leftJoin('movementLocation.locationGroup', 'locationGroup')
            ->innerJoin("arrival.receivers", "join_receivers")
            ->andWhere('arrival IS NOT NULL')
            ->andWhere('dropLocation.isDeliveryPoint = true')
            ->andWhere('dropGroupLocation.id IS NULL OR dropGroupLocation.id = locationGroup.id')
            ->andWhere('movementType.code = :dropType')
            ->groupBy('pack')
            ->having("packWaitingDays IN (:waitingDays)")
            ->setParameter('dropType', TrackingMovement::TYPE_DEPOSE)
            ->setParameter('waitingDays', $waitingDays);

        return $subQuery
            ->getQuery()
            ->getResult();
    }

    public function isInOngoingReception(Pack|int $pack): bool {
        if (!$pack || !$pack->getId()) {
            return false;
        }

        return intval($this->createQueryBuilder("pack")
                ->select("COUNT(reception)")
                ->join(ReceptionLine::class, "reception_line", Join::WITH, "reception_line.pack = pack")
                ->join("reception_line.reception", "reception")
                ->join("reception.statut", "status")
                ->andWhere("status.code = :ongoing")
                ->andWhere("pack.id = :pack")
                ->setParameter("pack", $pack)
                ->setParameter("ongoing", Reception::STATUT_EN_ATTENTE)
                ->getQuery()
                ->getSingleScalarResult()) > 0;
    }

    public function getForSelectFromDelivery(?string $term, ?int $delivery, bool $allowNoProject): array {
        $qb = $this->createQueryBuilder("pack")
            ->select("pack.id AS id, pack.code AS text")
            ->leftJoin(DeliveryRequestArticleLine::class, "request_line", Join::WITH, "request_line.pack = pack")
            ->leftJoin("request_line.request", "request")
            ->leftJoin("request.statut", "request_status")
            ->join(Demande::class, "edited_request", Join::WITH, "edited_request.id = :delivery")
            ->andWhere('pack.childArticles IS NOT EMPTY')
            ->andWhere("pack.code LIKE :term")
            ->andWhere("request.id IS NULL OR request_status.code NOT IN (:ongoing_statuses)")
            ->andWhere("edited_request.project IS NULL OR edited_request.project = pack.project")
            ->groupBy("pack")
            ->setParameters([
                "term" => "%$term%",
                "delivery" => $delivery,
                "ongoing_statuses" => [
                    Demande::STATUT_BROUILLON,
                    Demande::STATUT_A_TRAITER,
                    Demande::STATUT_PREPARE,
                    Demande::STATUT_INCOMPLETE,
                ]
            ]);

        if (!$allowNoProject) {
            $qb->andWhere("pack.project IS NOT NULL");
        }

        return $qb
            ->setMaxResults(100)
            ->getQuery()
            ->getArrayResult();
    }

    public function getOneArticleByBarCodeAndLocation(string $barCode, ?string $location) {
        $query = $this->createQueryBuilder("pack")
            ->addSelect("pack.id AS id")
            ->addSelect("pack.code AS barCode")
            ->addSelect("pack.quantity AS quantity")
            ->addSelect("pack_location.label AS location")
            ->addSelect("GROUP_CONCAT(child_articles.barCode SEPARATOR ';') AS articles")
            ->addSelect("COUNT(child_articles.id) AS articlesCount")
            ->addSelect("0 AS is_ref")
            ->addSelect("1 AS is_lu")
            ->addSelect("pack_project.code AS project")
            ->addSelect("join_nature.code AS natureCode")
            ->addSelect("join_nature.color AS natureColor")
            ->addSelect("DATE_FORMAT(last_action.datetime, '%d/%m/%Y %H:%i:%s') AS lastActionDate")
            ->join("pack.lastAction", "last_action")
            ->join("last_action.emplacement", "pack_location")
            ->leftJoin("pack.childArticles", "child_articles")
            ->leftJoin("pack.project", "pack_project")
            ->leftJoin("pack.nature", 'join_nature')
            ->andWhere("pack.code = :barcode")
            ->andWhere("pack.groupIteration IS NULL")
            ->groupBy("pack")
            ->setParameter("barcode", $barCode);

        if ($location) {
            $query
                ->andWhere("pack_location.label = :location")
                ->setParameter("location", $location);
        }

        $result = $query
            ->getQuery()
            ->getArrayResult();

        return !empty($result) ? $result[0] : null;
    }

    public function findWithoutArticle(string $code): ?Pack {
        return $this->createQueryBuilder("pack")
            ->leftJoin("pack.article", "article")
            ->andWhere("pack.article IS NULL")
            ->andWhere("pack.code = :code")
            ->setParameter("code", $code)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
