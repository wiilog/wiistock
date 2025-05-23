<?php

namespace App\Repository\Tracking;

use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\FreeField\FreeField;
use App\Entity\Language;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingEvent;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Utilisateur;
use App\Helper\QueryBuilderHelper;
use App\Service\FieldModesService;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Exception;
use Generator;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;
use Iterator;

class TrackingMovementRepository extends EntityRepository
{

    public const MOUVEMENT_TRACA_DEFAULT = 'tracking';
    public const MOUVEMENT_TRACA_STOCK = 'stock';

    private const MAX_ARTICLE_TRACKING_MOVEMENTS_TIMELINE = 6;

    private const DtToDbLabels = [
        'date' => 'datetime',
        'code' => 'code',
        'location' => 'emplacement',
        'type' => 'status',
        'reference' => 'reference',
        'label' => 'label',
        'operateur' => 'user',
        'quantity' => 'quantity'
    ];

    public function getByDates(DateTime $dateMin,
                               DateTime $dateMax,
                               string   $userDateFormat = Language::DMY_FORMAT): array {
        $dateMax = $dateMax->format("Y-m-d H:i:s");
        $dateMin = $dateMin->format("Y-m-d H:i:s");
        $dateFormat = Language::MYSQL_DATE_FORMATS[$userDateFormat] . " %H:%i:%s";

        $queryBuilder = $this->createQueryBuilder("tracking_movement")
            ->select("tracking_movement.id AS id")
            ->addSelect("DATE_FORMAT(tracking_movement.datetime, '$dateFormat') AS date")
            ->addSelect("pack.code AS logisticUnit")
            ->addSelect("tracking_movement.quantity AS quantity")
            ->addSelect("join_location.label AS location")
            ->addSelect("join_type.nom AS type")
            ->addSelect("join_operator.username as operator")
            ->addSelect("tracking_movement.commentaire AS comment")
            ->addSelect("pack_arrival.numeroCommandeList AS arrivalOrderNumber")
            ->addSelect("pack_arrival.isUrgent AS isUrgent")
            ->addSelect("tracking_movement.freeFields as freeFields")
            ->addSelect("CONCAT(join_packGroup.code, '-', tracking_movement.groupIteration) AS packGroup")
            ->addSelect("IF(SIZE(tracking_movement.attachments) > 0, 'oui', 'non') AS hasAttachments")
            ->andWhere("tracking_movement.datetime BETWEEN :dateMin AND :dateMax")
            ->innerJoin("tracking_movement.pack", "pack")
            ->leftJoin("tracking_movement.emplacement", "join_location")
            ->leftJoin("tracking_movement.type", "join_type")
            ->leftJoin("tracking_movement.operateur", "join_operator")
            ->leftJoin("pack.arrivage", "pack_arrival")
            ->leftJoin("tracking_movement.packGroup", "join_packGroup")
            ->setParameter("dateMin", $dateMin)
            ->setParameter("dateMax", $dateMax);

        return QueryBuilderHelper::addTrackingEntities($queryBuilder, "tracking_movement")
            ->getQuery()
            ->getResult();
    }

    public function findByParamsAndFilters(InputBag $params, ?array $filters, Utilisateur $user, FieldModesService $fieldModesService): array
    {
        $qb = $this->createQueryBuilder('tracking_movement')
            ->groupBy('tracking_movement.id');

        if($params->get('article')) {
            $qb
                ->leftJoin('tracking_movement.pack', 'from_article_join_pack')
                ->leftJoin('from_article_join_pack.article', 'from_article_join_article')
                ->andWhere('from_article_join_article.id = :article')
                ->setParameter('article', $params->get('article'));
        } else {
            foreach ($filters as $filter) {
                switch ($filter['field']) {
                    case 'statut':
                        $value = explode(',', $filter['value']);
                        $qb
                            ->join('tracking_movement.type', 'filter_type')
                            ->andWhere('filter_type.id in (:type)')
                            ->setParameter('type', $value);
                        break;
                    case 'emplacement':
                        $emplacementValue = explode(':', $filter['value']);
                        $qb
                            ->innerJoin(
                                'tracking_movement.emplacement',
                                'filter_location',
                                Join::WITH,
                                'filter_location.id = :location'
                            )
                            ->setParameter('location', $emplacementValue[0] ?? $filter['value']);
                        break;
                    case 'utilisateurs':
                        $value = explode(',', $filter['value']);
                        $qb
                            ->join('tracking_movement.operateur', 'filter_operator')
                            ->andWhere("filter_operator.id in (:userId)")
                            ->setParameter('userId', $value);
                        break;
                    case 'dateMin':
                        $qb
                            ->andWhere('tracking_movement.datetime >= :dateMin')
                            ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                        break;
                    case 'dateMax':
                        $qb
                            ->andWhere('tracking_movement.datetime <= :dateMax')
                            ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                        break;
                    case 'logisticUnits':
                        $packs = Stream::explode(",", $filter["value"])
                            ->map(static fn(string $pack) => (int)explode(':', $pack)[0])
                            ->toArray();
                        $qb
                            ->innerJoin(
                                "tracking_movement.pack",
                                "filter_pack",
                                Join::WITH,
                                "filter_pack.id  IN (:packs)"
                            )
                            ->setParameter("packs", $packs);
                        break;
                    case FiltreSup::FIELD_ARTICLE:
                        $value = explode(':', $filter['value'])[0];
                        $qb
                            ->leftJoin('tracking_movement.pack', 'filter_article_pack')
                            ->andWhere("filter_article_pack.article = :filter_article")
                            ->setParameter("filter_article", $value);
                        break;
                }
            }
        }

        //Filter search
        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $conditions = [
                        "date" =>  "DATE_FORMAT(tracking_movement.datetime, '%d/%m/%Y %H:%i:%s') LIKE :search_value",
                        "pack" => "search_pack.code LIKE :search_value",
                        "reference" => "(search_pack_supplierItem_referenceArticle.reference LIKE :search_value OR search_pack_referenceArticle.reference LIKE :search_value)",
                        "label" => '(search_pack_article.label LIKE :search_value  OR search_pack_referenceArticle.libelle LIKE :search_value)',
                        "group" => "search_pack_group.code LIKE :search_value",
                        "quantity" => null,
                        "location" => "search_location.label LIKE :search_value",
                        "type" => "search_type.nom LIKE :search_value",
                        "operator" => "search_operator.username LIKE :search_value",
                        "article" => "search_pack_article.barCode LIKE :search_value",
                    ];

                    $fieldModesService->bindSearchableColumns($conditions, 'trackingMovement', $qb, $user, $search);

                    $qb
                        ->innerJoin('tracking_movement.pack', 'search_pack')
                        ->leftJoin('tracking_movement.logisticUnitParent', 'search_logistic_unit_parent')
                        ->leftJoin('tracking_movement.emplacement', 'search_location')
                        ->leftJoin('tracking_movement.packGroup', 'search_pack_group')
                        ->leftJoin('tracking_movement.operateur', 'search_operator')
                        ->leftJoin('tracking_movement.type', 'search_type')
                        ->leftJoin('search_pack.referenceArticle', 'search_pack_referenceArticle')
                        ->leftJoin('search_pack.article', 'search_pack_article')
                        ->leftJoin('search_pack_article.articleFournisseur', 'search_pack_article_supplierItem')
                        ->leftJoin('search_pack_article_supplierItem.referenceArticle', 'search_pack_supplierItem_referenceArticle');
                }
            }

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = self::DtToDbLabels[$params->all('columns')[$params->all('order')[0]['column']]['data']] ?? $params->all('columns')[$params->all('order')[0]['column']]['data'];
                    if ($column === 'emplacement') {
                        $qb
                            ->leftJoin('tracking_movement.emplacement', 'order_location')
                            ->orderBy('order_location.label', $order);
                    } else if ($column === 'group') {
                        $qb
                            ->leftJoin('tracking_movement.packGroup', 'order_pack_group')
                            ->orderBy('order_pack_group.code', $order)
                            ->addOrderBy('tracking_movement.groupIteration', $order);
                    } else if ($column === 'status') {
                        $qb
                            ->leftJoin('tracking_movement.type', 'order_type')
                            ->orderBy('order_type.nom', $order);
                    } else if ($column === 'article') {
                        $qb
                            ->leftJoin('tracking_movement.pack', 'order_pack')
                            ->leftJoin('order_pack.article', 'order_pack_article')
                            ->orderBy('order_pack_article.barCode', $order);
                    } else if ($column === 'reference') {
                        $qb
                            ->innerJoin('tracking_movement.pack', 'order_pack')
                            ->leftJoin('order_pack.referenceArticle', 'order_pack_referenceArticle')
                            ->leftJoin('order_pack.article', 'order_pack_article')
                            ->leftJoin('order_pack_article.articleFournisseur', 'order_pack_article_articleFournisseur')
                            ->leftJoin('order_pack_article_articleFournisseur.referenceArticle', 'order_pack_article_articleFournisseur_referenceArticle')
                            ->orderBy('order_pack_referenceArticle.reference', $order)
                            ->addOrderBy('order_pack_article_articleFournisseur_referenceArticle.reference', $order);
                    } else if ($column === 'label') {
                        $qb
                            ->innerJoin('tracking_movement.pack', 'label_order_pack')
                            ->leftJoin('label_order_pack.referenceArticle', 'label_order_pack_referenceArticle')
                            ->leftJoin('label_order_pack.article', 'label_order_pack_article')
                            ->orderBy('label_order_pack_referenceArticle.libelle', $order)
                            ->addOrderBy('label_order_pack_article.label', $order);
                    } else if ($column === 'user') {
                        $qb
                            ->leftJoin('tracking_movement.operateur', 'order_operator')
                            ->orderBy('order_operator.username', $order);
                    }  else if ($column === 'packCode') {
                        $qb
                            ->leftJoin('tracking_movement.pack', 'code_order_pack')
                            ->leftJoin('code_order_pack.article', 'code_order_pack_article')
                            ->leftJoin('tracking_movement.logisticUnitParent', 'code_order_logistic_unit')
                            ->orderBy('IF(code_order_logistic_unit.id IS NOT NULL,
                                            code_order_logistic_unit.code,
                                            (IF (code_order_pack_article.currentLogisticUnit IS NOT NULL,
                                                NULL,
                                                code_order_pack.code)))', $order);
                    } else {
                        $freeFieldId = FieldModesService::extractFreeFieldId($column);
                        if(is_numeric($freeFieldId)) {
                            /** @var FreeField $freeField */
                            $freeField = $this->getEntityManager()->getRepository(FreeField::class)->find($freeFieldId);
                            if($freeField->getTypage() === FreeField::TYPE_NUMBER) {
                                $qb->orderBy("CAST(JSON_EXTRACT(tracking_movement.freeFields, '$.\"$freeFieldId\"') AS SIGNED)", $order);
                            } else {
                                $qb->orderBy("JSON_EXTRACT(tracking_movement.freeFields, '$.\"$freeFieldId\"')", $order);
                            }
                        } else if (property_exists(TrackingMovement::class, $column)) {
                            $qb->orderBy("tracking_movement.$column", $order);
                        }
                    }

                    $order = ($column === 'datetime')
                        ? $order
                        : Criteria::DESC;
                    $qb->addOrderBy('tracking_movement.orderIndex', $order);
                }
            }
        }

        if($params->get('movementsFilter')) {
            $trackingMovements = explode(',', $params->get('movementsFilter'));
            $qb->andWhere('tracking_movement IN (:tracking_movements)')
                ->setParameter('tracking_movements', $trackingMovements);
        }

        if(!$params->has("order")) {
            $qb->addOrderBy("tracking_movement.datetime", "DESC");
            $qb->addOrderBy("tracking_movement.orderIndex", "DESC");
        }

        if ($params->getInt('start')) {
            $qb->setFirstResult($params->getInt('start'));
        }

        if ($params->getInt('length')) {
            $qb->setMaxResults($params->getInt('length'));
        }

        $query = $qb
            ->addSelect("COUNT_OVER(tracking_movement.id) AS __query_count")
            ->getQuery();

        $queryResult = $query?->getResult();
        $countFiltered = $queryResult[0]['__query_count'] ?? 0;
        $data = Stream::from($queryResult)
            ->map(static fn($row) => $row[0])
            ->toArray();

        return [
            'count' => $countFiltered,
            'data' => $data,
        ];
    }

    /**
     * @param Utilisateur $operator
     * @param string $type self::MOUVEMENT_TRACA_STOCK | self::MOUVEMENT_TRACA_DEFAULT
     * @param array $filterDemandeCollecteIds
     * @return TrackingMovement[]
     */
    public function getPickingByOperatorAndNotDropped(Utilisateur $operator,
                                                      string $type,
                                                      array $filterDemandeCollecteIds = []) {
        $queryBuilder = $this->createQueryBuilder('tracking_movement');

        $typeCondition = ($type === self::MOUVEMENT_TRACA_STOCK)
            ? 'join_stockMovement.id IS NOT NULL'
            : 'join_stockMovement.id IS NULL';

        $queryBuilder
            ->join('tracking_movement.type', 'join_trackingType')
            ->join('tracking_movement.operateur', 'join_operator')
            ->join('tracking_movement.emplacement', 'join_location')
            ->leftJoin('tracking_movement.pack', 'join_pack')
            ->leftJoin('join_pack.nature', 'join_pack_nature')
            ->leftJoin('join_pack.article', 'join_pack_article')
            ->leftJoin('join_pack.childArticles', 'join_pack_child_articles')
            ->leftJoin('tracking_movement.mouvementStock', 'join_stockMovement')
            ->leftJoin('tracking_movement.packGroup', 'join_packGroup')
            ->innerJoin(Pack::class, "join_packLastAction", Join::WITH, "join_packLastAction.lastAction = tracking_movement") // check if it's the last tracking pick
            ->andWhere('join_operator = :operator')
            ->andWhere('join_trackingType.nom LIKE :priseType')
            ->andWhere('tracking_movement.finished = :finished')
            ->andWhere('join_pack_article.currentLogisticUnit IS NULL')
            ->andWhere($typeCondition)
            ->setParameter('operator', $operator)
            ->setParameter('priseType', TrackingMovement::TYPE_PRISE)
            ->setParameter('finished', false);

        if (!empty($filterDemandeCollecteIds)) {
            $queryBuilder
                ->join('join_stockMovement.collecteOrder', 'join_stockMovement_collectOrder')
                ->andWhere('join_stockMovement_collectOrder.id IN (:collecteOrderId)')
                ->setParameter('collecteOrderId', $filterDemandeCollecteIds, Connection::PARAM_STR_ARRAY);
        }

        return $queryBuilder
            ->getQuery()
            ->execute();
    }

    public function countDropsOnLocationsOn(DateTime $dateTime, array $locations): int
    {
        $qb = $this->createQueryBuilder('tracking_movement');
        $start = clone $dateTime;
        $end = clone $dateTime;
        $start->setTime(0, 0, 0);
        $end->setTime(23, 59, 59);
        $qb
            ->select('COUNT(DISTINCT pack.id)')
            ->join('tracking_movement.emplacement', 'join_location')
            ->join('tracking_movement.pack', 'pack')
            ->join('tracking_movement.type', 'type')
            ->andWhere('join_location.id IN (:locations)')
            ->andWhere('tracking_movement.datetime BETWEEN :start AND :end')
            ->andWhere('type.nom = :drop')
            ->setParameter('drop', TrackingMovement::TYPE_DEPOSE)
            ->setParameter('locations', $locations)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByLocation(Emplacement $location): int {
        return $this->createQueryBuilder('tracking_movement')
            ->select('COUNT(tracking_movement.id)')
            ->andWhere('tracking_movement.emplacement = :location')
            ->setParameter('location', $location)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLastTakingNotFinished(string $code) {
        return $this->createQueryBuilder('tracking_movement')
            ->join('tracking_movement.pack', 'join_pack')
            ->join('tracking_movement.type', 'join_type')
            ->join('tracking_movement.mouvementStock', 'join_stock_movement')
            ->where('join_pack.code = :code')
            ->andWhere('join_type.code = :takingCode')
            ->andWhere('tracking_movement.finished = false')
            ->orderBy('tracking_movement.datetime', 'DESC')
            ->setParameter('takingCode', TrackingMovement::TYPE_PRISE)
            ->setParameter('code', $code)
            ->getQuery()
            ->getResult();
    }

    public function findTrackingMovementsForGroupHistory($pack, $params) {
        $qb = $this->createQueryBuilder('tracking_movement');

        $qb->select('tracking_movement')
            ->leftJoin('tracking_movement.pack', 'pack')
            ->leftJoin('tracking_movement.type', 'type')
            ->where('pack.id = :pack')
            ->andWhere('type.nom = :groupType OR type.nom = :ungroupType')
            ->setParameters([
                'pack' => $pack,
                'groupType' => TrackingMovement::TYPE_GROUP,
                'ungroupType' => TrackingMovement::TYPE_UNGROUP
            ]);

        $countTotal = QueryBuilderHelper::count($qb, "tracking_movement");

        //Filter search
        if (!empty($params)) {
            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];
                    if ($column === 'group') {
                        $qb
                            ->leftJoin('tracking_movement.pack', 'order_pack')
                            ->leftJoin('order_pack.parent', 'pack_group')
                            ->orderBy('pack_group.label', $order);
                    } else if ($column === 'date') {
                        $qb
                            ->orderBy('tracking_movement.datetime', $order);
                    } else if ($column === 'type') {
                        $qb
                            ->leftJoin('tracking_movement.type', 'order_type')
                            ->orderBy('order_type.nom', $order);
                    }
                }
            }
        }

        $countFiltered = QueryBuilderHelper::count($qb, "tracking_movement");

        return [
            'data' => $qb->getQuery()->getResult(),
            'filtered' => $countFiltered,
            'total' => $countTotal
        ];
    }

    public function getArticleTrackingMovements(int $article, $option = []): array {
        $qb =  $this->createQueryBuilder('tracking_movement');
        $qb
            ->addSelect('tracking_movement.id AS id')
            ->addSelect('join_status.code AS type')
            ->addSelect('join_location.label AS location')
            ->addSelect('tracking_movement.datetime AS date')
            ->addSelect('join_operator.username AS operator')
            ->addSelect('join_logisticUnitParent.code AS logisticUnitParent')
            ->leftJoin('tracking_movement.pack', 'join_pack')
            ->leftJoin('join_pack.article', 'join_article')
            ->leftJoin('tracking_movement.type', 'join_status')
            ->leftJoin('tracking_movement.emplacement', 'join_location')
            ->leftJoin('tracking_movement.operateur', 'join_operator')
            ->leftJoin('tracking_movement.logisticUnitParent', 'join_logisticUnitParent')
            ->andWhere('join_article.id = :article')
            ->orderBy('tracking_movement.datetime', 'DESC')
            ->addOrderBy('tracking_movement.orderIndex', 'DESC')
            ->setMaxResults(self::MAX_ARTICLE_TRACKING_MOVEMENTS_TIMELINE)
            ->setParameter('article', $article);

        if ($option['mainMovementOnly'] ?? false) {
            $qb->andWhere('tracking_movement.mainMovement IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    public function findChildArticleMovementsBy(Pack $pack) {
        return $this->createQueryBuilder("movement")
            ->join("movement.type", "movement_status")
            ->join("movement.pack", "movement_pack")
            ->join("movement_pack.article", "movement_pack_article")
            ->andWhere("movement_status.code IN (:types)")
            ->andWhere("movement.logisticUnitParent = :pack")
            ->setParameter("types", [
                TrackingMovement::TYPE_PICK_LU,
                TrackingMovement::TYPE_DROP_LU,
            ])
            ->setParameter("pack", $pack)
            ->getQuery()
            ->getResult();
    }

    public function countByArticle(Article $article): int {
        $result = $this->createQueryBuilder("tracking_movement")
            ->select('COUNT(DISTINCT tracking_movement.id)')
            ->join("tracking_movement.pack", "join_pack", Join::WITH)
            ->andWhere("join_pack.article = :article")
            ->setParameter("article", $article)
            ->getQuery()
            ->getSingleResult();


        return $result[1] ?? 0;
    }

    /**
     * @return Generator<TrackingMovement>
     */
    public function iterateEventTrackingMovementBetween(Pack      $pack,
                                                        DateTime  $timerStartedAt,
                                                        ?DateTime $timerStoppedAt = null): iterable {
        // Request 1: main request to get movements which modify tracking delay
        $eventTrackingMovementsQueryBuilder = $this->createQueryBuilder("tracking")
            ->andWhere("tracking.pack = :pack")
            ->andWhere("tracking.datetime >= :timerStartedAt")
            ->andWhere("tracking.event IS NOT NULL")
            ->orderBy("tracking.datetime", Order::Ascending->value)
            ->addOrderBy("tracking.orderIndex", Order::Ascending->value)
            ->addOrderBy("tracking.id", Order::Ascending->value)
            ->setParameter("pack", $pack)
            ->setParameter("timerStartedAt", $timerStartedAt);

        if ($timerStoppedAt) {
            $eventTrackingMovementsQueryBuilder
                ->andWhere("tracking.datetime <= :timerStoppedAt")
                ->setParameter("timerStoppedAt", $timerStoppedAt);
        }

        /** @var Iterator<TrackingMovement> $eventTrackingMovementIterator */
        $eventTrackingMovementIterator = $eventTrackingMovementsQueryBuilder
            ->getQuery()
            ->toIterable();

        // Request 2: Sub request to get next unpause movement after a pause movement
        $eventAfterPauseQueryBuilder = $this->createQueryBuilder("tracking_after");
        $exprBuilder = $eventAfterPauseQueryBuilder->expr();
        $eventAfterPauseQueryBuilder
            ->select("tracking_after.id")
            ->innerJoin("tracking_after.type", "after_type")
            ->andWhere("tracking_after.pack = tracking.pack")
            ->andWhere("after_type.code IN (:unpauseTrackingTypes)")
            ->andWhere($exprBuilder->orX(
                "tracking_after.datetime > tracking.datetime",
                $exprBuilder->andX(
                    "tracking_after.datetime = tracking.datetime",
                    $exprBuilder->orX(
                        "tracking_after.orderIndex > tracking.orderIndex",
                        $exprBuilder->andX("tracking_after.orderIndex = tracking.orderIndex", "tracking_after.id > tracking.id")
                    )
                )
            ))
            ->andWhere("tracking_after.id != tracking.id")
            ->orderBy("tracking_after.datetime", Order::Ascending->value)
            ->addOrderBy("tracking_after.orderIndex", Order::Ascending->value)
            ->addOrderBy("tracking_after.id", Order::Ascending->value);


        // Request 3: Same request than Request 1 to get only unpause tracking movement associated to pause movement
        // We reused Request 1
        $eventTrackingMovementsQueryBuilder
            ->select("tracking_after_pause")
            ->andWhere("tracking.event = :eventPause")
            ->innerJoin(TrackingMovement::class, 'tracking_after_pause', Join::WITH, "tracking_after_pause.id = FIRST({$eventAfterPauseQueryBuilder->getQuery()->getDQL()})")
            ->setParameter("eventPause", TrackingEvent::PAUSE->value)
            ->setParameter("unpauseTrackingTypes", [
                TrackingMovement::TYPE_PRISE,
                TrackingMovement::TYPE_DEPOSE,
            ]);

        /** @var Iterator<TrackingMovement> $trackingMovementAfterPauseIterator */
        $trackingMovementAfterPauseIterator = $eventTrackingMovementsQueryBuilder
            ->getQuery()
            ->toIterable();

        foreach ($eventTrackingMovementIterator as $trackingMovement) {
            yield $trackingMovement;

            if ($trackingMovement->getEvent() === TrackingEvent::PAUSE) {
                $trackingMovementAfterPause = $trackingMovementAfterPauseIterator->current();
                if ($trackingMovementAfterPause) {
                    if (!$trackingMovementAfterPause->getEvent()) {
                        yield $trackingMovementAfterPause;
                    }
                    $trackingMovementAfterPauseIterator->next();
                }
            }
        }

        if (empty($eventTrackingMovementIterator)) {
            yield;
        }
    }

    /**
     * @param "action"|"picking"|"drop"|"start"|"stop" $type
     */
    public function findLastByPack(string            $type,
                                   Pack              $pack,
                                   ?TrackingMovement $ignored): ?TrackingMovement {
        $queryBuilder = $this->createQueryBuilder("movement")
            ->andWhere("movement.pack = :pack")
            ->orderBy("movement.datetime", Order::Descending->value)
            ->addOrderBy("movement.orderIndex", Order::Descending->value)
            ->addOrderBy("movement.id", Order::Descending->value)
            ->setMaxResults(1)
            ->setParameter("pack", $pack);

        if ($ignored?->getId()) {
            $queryBuilder
                ->andWhere("movement != :ignored_movement")
                ->setParameter("ignored_movement", $ignored);
        }

        switch ($type) {
            case "action":
                // get the last one whatever the movement
                break;
            case "picking":
                $queryBuilder
                    ->andWhere("join_type.code = :picking_type")
                    ->join("movement.type", "join_type")
                    ->setParameter("picking_type", TrackingMovement::TYPE_PRISE);
                break;
            case "drop":
                $queryBuilder
                    ->andWhere("join_type.code = :drop_type")
                    ->join("movement.type", "join_type")
                    ->setParameter("drop_type", TrackingMovement::TYPE_DEPOSE);
                break;
            case "movement":
                $queryBuilder
                    ->andWhere("join_type.code = :picking_type OR join_type.code = :picking_type")
                    ->join("movement.type", "join_type")
                    ->setParameter("picking_type", TrackingMovement::TYPE_PRISE)
                    ->setParameter("drop_type", TrackingMovement::TYPE_DEPOSE);
                break;
            case "start":
                $queryBuilder
                    ->andWhere("movement.event = :startEvent")
                    ->setParameter("startEvent", TrackingEvent::START->value);
                break;
            case "stop":
                $queryBuilder
                    ->andWhere("movement.event = :stopEvent")
                    ->setParameter("stopEvent", TrackingEvent::STOP->value);
                break;
            default:
                throw new Exception("Not implemented yet");
        }

        return $queryBuilder
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findFistActionByPack(Pack              $pack,
                                         ?TrackingMovement $ignored): ?TrackingMovement {
        $queryBuilder = $this->createQueryBuilder("movement")
            ->andWhere("movement.pack = :pack")
            ->orderBy("movement.datetime", Order::Ascending->value)
            ->addOrderBy("movement.orderIndex", Order::Ascending->value)
            ->addOrderBy("movement.id", Order::Ascending->value)
            ->setMaxResults(1)
            ->setParameter("pack", $pack);

        if ($ignored?->getId()) {
            $queryBuilder
                ->andWhere("movement != :ignored_movement")
                ->setParameter("ignored_movement", $ignored);
        }

        return $queryBuilder
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Returns an iterable of TrackingMovement older than the given date
     *
     * @param DateTime $date
     * @return iterable<TrackingMovement>
     */
    public function iterateOlderThan(DateTime $date): iterable {
        $queryBuilder = $this->createQueryBuilder("tracking_movement")
            ->andWhere("tracking_movement.datetime < :date")
            ->addOrderBy("tracking_movement.pack", Order::Ascending->value)
            ->setParameter("date", $date);

        return $queryBuilder
            ->getQuery()
            ->toIterable();
    }

    /**
     * Counts the number of TrackingMovement older than the given date.
     *
     * @param DateTime $date
     * @return int
     * @throws NonUniqueResultException|NoResultException
     */
    public function countOlderThan(DateTime $date): int {
        return $this->createQueryBuilder("tracking_movement")
            ->select("COUNT(tracking_movement.id)")
            ->andWhere("tracking_movement.datetime < :date")
            ->setParameter("date", $date)
            ->getQuery()
            ->getSingleScalarResult();
    }

}
