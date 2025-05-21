<?php

namespace App\Repository\Emergency;


use App\Command\Users\DeactivateUserCommand;
use App\Entity\Arrivage;
use App\Entity\Emergency\EmergencyTriggerEnum;
use App\Entity\Emergency\EndEmergencyCriteriaEnum;
use App\Entity\Emergency\StockEmergency;
use App\Entity\Emergency\TrackingEmergency;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\FreeField\FreeField;
use App\Entity\FiltreSup;
use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use App\Service\FieldModesService;
use DateTime;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpFoundation\ParameterBag;
use WiiCommon\Helper\Stream;

/**
 * @extends EntityRepository<EmergencyRepository>
 */
class EmergencyRepository extends EntityRepository {

    public function getLastArrivalNumbersSubquery(): QueryBuilder {
        $entityManager = $this->getEntityManager();
        return $entityManager->createQueryBuilder()
            ->select('arrival.numeroArrivage')
            ->from(Arrivage::class, 'arrival')
            ->innerJoin('arrival.trackingEmergencies', 'emergency_arrival', Join::WITH, 'emergency_arrival = emergency')
            ->orderBy('arrival.date', Order::Descending->value);
    }

    public function getLastReceptionNumbersSubquery(): QueryBuilder {
        $entityManager = $this->getEntityManager();
        return $entityManager->createQueryBuilder()
            ->select('reception.number')
            ->from(Reception::class, 'reception')
            ->andWhere('emergency_reception.id = emergency.id')
            ->innerJoin("reception.lines", "reception_line")
            ->innerJoin("reception_line.receptionReferenceArticles", "reception_reference_article")
            ->innerJoin("reception_reference_article.stockEmergencies", "emergency_reception")
            ->orderBy('reception.date', Order::Descending->value);
    }

    public function findByParamsAndFilters(ParameterBag $params, array $filters, array $visibleColumnsConfig): array {
        $lastArrivalNumbersSubquery = $this->getLastArrivalNumbersSubquery();
        $lastReceptionNumbersSubquery = $this->getLastReceptionNumbersSubquery();
        $entityManager = $this->getEntityManager();

        $columns = [
            FixedFieldEnum::dateStart->name => "emergency.dateStart",
            FixedFieldEnum::dateEnd->name => "emergency.dateEnd",
            "lastEntityNumber"=> [
                "lastArrivalNumber" => "FIRST($lastArrivalNumbersSubquery)",
                "lastReceptionNumber" => "FIRST($lastReceptionNumbersSubquery)",
            ],
            FixedFieldEnum::createdAt->name => "emergency.createdAt",
            "lastTriggeredAt" => "emergency.lastTriggeredAt",
            "closedAt"=> "emergency.closedAt",
            FixedFieldEnum::orderNumber->name => "emergency.orderNumber",
            FixedFieldEnum::postNumber->name => "tracking_emergency.postNumber",
            FixedFieldEnum::buyer->name => "emergency_buyer.username",
            FixedFieldEnum::supplier->name => "emergency_supplier.nom",
            FixedFieldEnum::carrier->name => "emergency_carrier.label",
            FixedFieldEnum::carrierTrackingNumber->name => "emergency.carrierTrackingNumber",
            FixedFieldEnum::type->name => "emergency_type.label",
            FixedFieldEnum::internalArticleCode->name=> "tracking_emergency.internalArticleCode",
            FixedFieldEnum::supplierArticleCode->name => "tracking_emergency.supplierArticleCode",
        ];

        $order = $params->all('order')[0]['dir'] ?? null;
        $columnToOrder = $params->all('columns')[$params->all('order')[0]['column']]['data'] ?? null;
        $searchParams = $params->all('search');
        $search = $searchParams['value'] ?? null;
        $presentSearchableColumns = Stream::from($visibleColumnsConfig)
            ->filter(static fn(array $config): bool => ($config['searchable'] ?? false) && ($config['fieldVisible'] ?? false))
            ->map(static fn(array $config): string => $config['data'])
            ->toArray();
        $searches = [];

        $queryBuilder = $this->createQueryBuilder("emergency")
            ->select("emergency.id AS id")
            ->distinct()
            ->addSelect("emergency.freeFields AS freeFields")
            ->addSelect("emergency_category.label AS emergency_category_label");
        $exprBuilder = $queryBuilder->expr();

        $total = QueryBuilderHelper::count($queryBuilder, 'emergency');

        foreach ($filters as $filter) {
            switch($filter['field']) {
                case 'dateMin':
                    $queryBuilder
                        ->andWhere("emergency.createdAt >= :filter_dateMin_value")
                        ->setParameter('filter_dateMin_value', "{$filter['value']} 00:00:00");
                    break;
                case 'dateMax':
                    $queryBuilder
                        ->andWhere("emergency.createdAt <= :filter_dateMax_value")
                        ->setParameter('filter_dateMax_value', "{$filter['value']} 23:59:59");
                    break;
                case FiltreSup::FIELD_EMERGENCY_STATUT:
                    if ($filter['value']) {
                        $conditions = Stream::explode(',', $filter['value'])
                            ->filter()
                            ->map(static fn($entity) => explode(':', $entity)[0])
                            ->map(static function(string $statut) {
                                return match($statut) {
                                    "Actives" => "emergency.closedAt IS NULL",
                                    "Cloturées" => "emergency.closedAt IS NOT NULL",
                                    default => "",
                                };
                            })
                            ->toArray();

                        $queryBuilder->andWhere($exprBuilder->orX(...($conditions)));
                    }
                    break;
                case FiltreSup::FIELD_MULTIPLE_TYPES:
                    if(!empty($filter['value'])){
                        $value = Stream::explode(',', $filter['value'])
                            ->filter()
                            ->map(static fn($type) => explode(':', $type)[0])
                            ->toArray();

                        $queryBuilder
                            ->andWhere('emergency_type.id in (:filter_type_value)')
                            ->setParameter('filter_type_value', $value);
                    }
                    break;
                case FiltreSup::FIELD_CARRIERS:
                    if(!empty($filter['value'])){
                        $value = Stream::explode(',', $filter['value'])
                            ->filter()
                            ->map(static fn($carrier) => explode(':', $carrier)[0])
                            ->toArray();
                        $queryBuilder
                            ->andWhere('emergency_carrier.id in (:filter_carrier_value)')
                            ->setParameter('filter_carrier_value', $value);
                    }
                    break;
                case FiltreSup::FIELD_EMERGENCY_APPLIED_TO:
                    if(!empty($filter['value'])){
                        $value = Stream::explode(',', $filter['value'])
                            ->filter()
                            ->map(static fn($entity) => explode(':', $entity)[0])
                            ->toArray();

                        $queryBuilder
                            ->andWhere(
                                $exprBuilder->orX(
                                    ...(in_array('Trace', $value) ? ['emergency INSTANCE OF :filter_trackingEmergencyClass'] : []),
                                    ...(in_array('Stock', $value) ? ['emergency INSTANCE OF :filter_stockEmergencyClass'] : []),
                                )
                            );

                        if(in_array('Trace', $value)) {
                            $queryBuilder->setParameter('filter_trackingEmergencyClass', $entityManager->getClassMetadata(TrackingEmergency::class));
                        }

                        if(in_array('Stock', $value)) {
                            $queryBuilder->setParameter('filter_stockEmergencyClass', $entityManager->getClassMetadata(StockEmergency::class));
                        }
                    }
                    break;
                case FiltreSup::FIELD_TRACKING_CARRIER_NUMBER:
                    $queryBuilder->andWhere('emergency.carrierTrackingNumber = :filter_carrierTrackingNumber ')
                        ->setParameter('filter_carrierTrackingNumber', $filter['value']);
                    break;
                case FiltreSup::FIELD_COMMANDE:
                    $queryBuilder->andWhere('emergency.orderNumber = :filter_orderNumber ')
                        ->setParameter('filter_orderNumber', $filter['value']);
                    break;
                case "referenceArticle":
                    $queryBuilder
                        ->innerJoin(StockEmergency::class, "stock_emergency", Join::WITH, "stock_emergency.id = emergency.id")
                        ->leftJoin("stock_emergency.supplier", "stock_emergency_supplier")
                        ->leftJoin("stock_emergency_supplier.articlesFournisseur", "stock_emergency_supplier_article")
                        ->innerJoin(ReferenceArticle::class, 'join_reference_article', Join::WITH,
                            $exprBuilder->andX(
                                $exprBuilder->orX(
                                    $exprBuilder->eq("join_reference_article", "stock_emergency.referenceArticle"),
                                    $exprBuilder->eq("stock_emergency_supplier_article.referenceArticle", "join_reference_article"),
                                ),
                                $exprBuilder->eq("join_reference_article.id", ":referenceArticleId"),
                            )
                        )
                        ->setParameter("referenceArticleId",  $filter['value'])
                        ->andWhere(StockEmergencyRepository::getTriggeredStockEmergenciesCondition($exprBuilder, "stock_emergency"))
                        ->setParameter("emergencyTriggerReference", EmergencyTriggerEnum::REFERENCE)
                        ->setParameter("emergencyTriggerSupplier", EmergencyTriggerEnum::SUPPLIER)
                        ->setParameter("endEmergencyCriteriaRemainingQuantity", EndEmergencyCriteriaEnum::REMAINING_QUANTITY)
                        ->setParameter("endEmergencyCriteriaEndDate", EndEmergencyCriteriaEnum::END_DATE)
                        ->setParameter("endEmergencyCriteriaManual", EndEmergencyCriteriaEnum::MANUAL)
                        ->setParameter("now", new DateTime());
                    break;
            }
        }

        foreach ($visibleColumnsConfig as $config) {
            $columnName = $config['data'] ?? null;
            $columnsSelected = $columns[$columnName] ?? [];
            if(!is_array($columnsSelected)) {
                $columnsSelected = [$columnName => $columnsSelected];
            }

            if ($order && $columnToOrder === $columnName) {
                if (str_starts_with($columnToOrder, FieldModesService::FREE_FIELD_NAME_PREFIX)) {
                    $freeFieldId = FieldModesService::extractFreeFieldId($columnToOrder);
                    if(is_numeric($freeFieldId)) {
                        $freeField = $this->getEntityManager()->getRepository(FreeField::class)->find($freeFieldId);
                        $sort = $freeField->getTypage() === FreeField::TYPE_NUMBER
                            ? "CAST(JSON_EXTRACT(emergency.freeFields, '$.\"$freeFieldId\"') AS SIGNED)"
                            : "JSON_EXTRACT(emergency.freeFields, '$.\"$freeFieldId\"')";
                        $queryBuilder->orderBy($sort, $order);
                    }
                } else {
                    $column = $columnsSelected[$columnName];
                    $queryBuilder
                        ->orderBy($column, $order)
                        ->addSelect("$column AS order_$columnName");
                }
            }

            foreach ($columnsSelected as $field => $column) {
                if (!$field || !$column || !($config['fieldVisible'] ?? false)) {
                    $queryBuilder->addSelect("'' AS $field");
                } else {
                    $queryBuilder->addSelect("$column AS $field");
                    if (!empty($search) && in_array($field, $presentSearchableColumns)) {
                        $searches[] = $exprBuilder->like("$column", ":value");
                    }
                }
            }
        }

        if (!empty($search)) {
            $queryBuilder
                ->andWhere($exprBuilder->orX(...$searches))
                ->setParameter('value', "%$search%");
        }

        $queryBuilder
            ->leftJoin(TrackingEmergency::class, "tracking_emergency", Join::WITH, "tracking_emergency.id = emergency.id")
            ->leftJoin("emergency.buyer", "emergency_buyer")
            ->leftJoin("emergency.supplier", "emergency_supplier")
            ->leftJoin("emergency.carrier", "emergency_carrier")
            ->leftJoin("emergency.type", "emergency_type")
            ->leftJoin("emergency_type.category", "emergency_category");

        $filtered = QueryBuilderHelper::count($queryBuilder, 'emergency');

        if ($params->getInt('start')) {
            $queryBuilder->setFirstResult($params->getInt('start'));
        }

        $pageLength = $params->getInt('length') ? $params->getInt('length') : 100;
        if ($pageLength) {
            $queryBuilder->setMaxResults($pageLength);
        }

        return [
            'data' => $queryBuilder->getQuery()->getResult(),
            'count' => $filtered,
            'total' => $total
        ];
    }

    public function countUntriggered(bool $daily = false,
                                     bool $active = false): ?int {
        $queryBuilder = $this->createQueryBuilder('emergency');

        $exprBuilder = $queryBuilder->expr();

        $queryBuilder = $queryBuilder
            ->select('COUNT(emergency)')
            ->leftJoin(StockEmergency::class, 'stock_emergency', Join::WITH, 'emergency.id = stock_emergency.id')
            ->andWhere('tracking_emergency.arrivals IS EMPTY')
            ->andWhere($exprBuilder->isNotNull('tracking_emergency.closedAt'))
            ->andWhere($exprBuilder->orX(
                $exprBuilder->andX(
                    $exprBuilder->isNotNull('tracking_emergency '),
                    $exprBuilder->lt("tracking_emergency.dateStart ", ":now")
                ),
                // TODO WIIS-12769 for stock emergency
            ))
            ->setParameter('now', new DateTime('now'));

        /*
         TODO WIIS-12769 for stock emergency
            ->leftJoin(TrackingEmergency::class, 'tracking_emergency', Join::WITH, 'emergency.id = tracking_emergency.id')
            ->andWhere('stock_emergency.receptionReferenceArticles IS EMPTY AND tracking_emergency.arrivals IS EMPTY') -> replace existing andWhere tracking_emergency.arrivals IS EMPTY'
         */

        if ($daily) {
            $todayEvening = new DateTime('now');
            $todayEvening->setTime(23, 59, 59, 59);
            $todayMorning = new DateTime('now');
            $todayMorning->setTime(0, 0, 0, 1);
            $queryBuilder
                ->andWhere('emergency.dateEnd < :todayEvening')
                ->andWhere('emergency.dateEnd > :todayMorning')
                ->setParameter('todayEvening', $todayEvening)
                ->setParameter('todayMorning', $todayMorning);
        }

        if ($active) {
            $today = new DateTime('now');
            $queryBuilder
                ->andWhere('emergency.dateEnd >= :todayEvening')
                ->setParameter('todayEvening', $today);
        }

        return $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function iterateByDates(DateTime $dateMin, DateTime $dateMax): iterable {
        $lastArrivalNumberSubquery = $this->getLastArrivalNumbersSubquery();
        $lastReceptionNumberSubquery = $this->getLastReceptionNumbersSubquery();

        $queryBuilder = $this->createQueryBuilder("emergency")
            ->select('emergency.id AS id')
            ->addSelect('emergency.dateStart AS dateStart')
            ->addSelect('emergency.dateEnd AS dateEnd')
            ->addSelect('emergency.closedAt AS closedAt')
            ->addSelect('emergency.lastTriggeredAt AS lastTriggeredAt')
            ->addSelect("FIRST($lastArrivalNumberSubquery) as lastArrivalNumber")
            ->addSelect("FIRST($lastReceptionNumberSubquery) as lastReceptionNumber")
            ->addSelect('emergency.createdAt AS createdAt')
            ->addSelect('emergency.orderNumber AS orderNumber')
            ->addSelect('tracking_emergency.postNumber AS postNumber')
            ->addSelect('emergency_buyer.username AS buyer')
            ->addSelect('emergency_supplier.nom AS supplier')
            ->addSelect('emergency_carrier.label AS carrier')
            ->addSelect('emergency.carrierTrackingNumber AS carrierTrackingNumber')
            ->addSelect("emergency_type.label AS type")
            ->addSelect('tracking_emergency.internalArticleCode AS internalArticleCode')
            ->addSelect('tracking_emergency.supplierArticleCode AS supplierArticleCode')
            ->addSelect("emergency.freeFields AS freeFields")
            ->leftJoin(TrackingEmergency::class, "tracking_emergency", Join::WITH, "tracking_emergency.id = emergency.id")
            ->leftJoin("emergency.buyer", "emergency_buyer")
            ->leftJoin("emergency.supplier", "emergency_supplier")
            ->leftJoin("emergency.carrier", "emergency_carrier")
            ->leftJoin("emergency.type", "emergency_type")
            ->andWhere('emergency.createdAt BETWEEN :dateMin AND :dateMax')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax,
            ]);

        return $queryBuilder
            ->getQuery()
            ->getArrayResult();
    }
}
