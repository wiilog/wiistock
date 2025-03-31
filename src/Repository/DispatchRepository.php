<?php

namespace App\Repository;

use App\Entity\AverageRequestTime;
use App\Entity\Dispatch;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\FreeField\FreeField;
use App\Entity\Interfaces\StatusHistoryContainer;
use App\Entity\Language;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Helper\QueryBuilderHelper;
use App\Service\UniqueNumberService;
use WiiCommon\Helper\Stream;
use App\Service\FieldModesService;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use RuntimeException;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method Dispatch|null find($id, $lockMode = null, $lockVersion = null)
 * @method Dispatch|null findOneBy(array $criteria, array $orderBy = null)
 * @method Dispatch[]    findAll()
 * @method Dispatch[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DispatchRepository extends EntityRepository
{
    public function findByParamAndFilters(InputBag          $params,
                                          array             $filters,
                                          Utilisateur       $user,
                                          FieldModesService $fieldModesService,
                                          array             $options = []): array {
        $qb = $this->createQueryBuilder('dispatch')
            ->groupBy('dispatch.id');

        if(!empty($user->getDispatchTypeIds())){
            $qb
                ->join('dispatch.type', 'join_type_user')
                ->andWhere('join_type_user.id IN (:userDispatchTypeIds)')
                ->setParameter('userDispatchTypeIds', $user->getDispatchTypeIds());
        }

        $countTotal = QueryBuilderHelper::count($qb, 'dispatch');

        $dateChoiceConfig = Stream::from($filters)->find(static fn($filter) => $filter['field'] === 'date-choice')
            ?? Stream::from(FiltreSup::DATE_CHOICE_VALUES[Dispatch::class])->find(static fn($config) => $config['default'] ?? false);
        $dateChoice = $dateChoiceConfig["value"] ?? '';

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statuses-filter':
                case 'statut':
                    if(!empty($filter['value'])) {
                        $value = explode(',', $filter['value']);
                        $qb
                            ->join('dispatch.statut', 'filter_status')
                            ->andWhere('filter_status.id in (:statut)')
                            ->setParameter('statut', $value);
                    }
					break;
                case FiltreSup::FIELD_MULTIPLE_TYPES:
                    if(!empty($filter['value'])){
                        $value = Stream::explode(',', $filter['value'])
                            ->filter()
                            ->map(static fn($type) => explode(':', $type)[0])
                            ->toArray();
                        $qb
                            ->join('dispatch.type', 'filter_type')
                            ->andWhere('filter_type.id in (:filter_type_value)')
                            ->setParameter('filter_type_value', $value);
                    }
                    break;
                case FiltreSup::FIELD_REQUESTERS:
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('dispatch.requester', 'filter_requester')
                        ->andWhere('filter_requester.id in (:filter_requester_values)')
                        ->setParameter('filter_requester_values', $value);
                    break;
                case FiltreSup::FIELD_RECEIVERS:
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('dispatch.receivers', 'filter_receivers')
                        ->andWhere('filter_receivers.id in (:filter_receivers_values)')
                        ->setParameter('filter_receivers_values', $value);
                    break;
                case FiltreSup::FIELD_CARRIERS:
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('dispatch.carrier', 'filter_carrier')
                        ->andWhere('filter_carrier.id in (:filter_carrier_value)')
                        ->setParameter('filter_carrier_value', $value);
                    break;
                case FiltreSup::FIELD_DISPATCH_NUMBER:
                    $value = explode(',', $filter['value']);
                    $qb->andWhere("dispatch.id = :filter_dispatchnumber_value")
                        ->setParameter("filter_dispatchnumber_value", $value);
                    break;
                case FiltreSup::FIELD_COMMAND_LIST:
                    $value = array_map(function($value) {
                        return explode(":", $value)[0];
                    }, explode(',', $filter['value']));
                    $qb->andWhere("dispatch.commandNumber IN (:filter_commandNumber_value)")
                        ->setParameter("filter_commandNumber_value", $value);
                    break;
                case FiltreSup::FIELD_EMERGENCY_MULTIPLE:
                    $value = array_map(function($value) {
                        return explode(":", $value)[0];
                    }, explode(',', $filter['value']));

                    $nonUrgentCondition = in_array($options['nonUrgentTranslationLabel'], $value) ? 'OR dispatch.emergency IS NULL' : '';

                    $qb->andWhere("dispatch.emergency IN (:filter_emergencies_value) $nonUrgentCondition")
                        ->setParameter("filter_emergencies_value", $value);
                    break;
                case 'dateMin':
                    $filteredDate = match ($dateChoice){
                        'validationDate' => 'validationDate',
                        'treatmentDate' => 'treatmentDate',
                        'endDate' => 'startDate',
                        'lastPartialStatusDate' => 'lastPartialStatusDate',
                        default => 'creationDate'
                    };
                    $qb->andWhere("dispatch.{$filteredDate} >= :filter_dateMin_value")
                        ->setParameter('filter_dateMin_value', $filter['value'] . ' 00.00.00');
                    break;
                case 'dateMax':
                    $filteredDate = match ($dateChoice){
                        'validationDate' => 'validationDate',
                        'treatmentDate' => 'treatmentDate',
                        'endDate' => 'endDate',
                        default => 'creationDate'
                    };
                    $qb->andWhere("dispatch.{$filteredDate} <= :filter_dateMax_value")
                        ->setParameter('filter_dateMax_value', $filter['value'] . ' 23:59:59');
                    break;
                case 'destination':
                    $qb->andWhere('dispatch.destination LIKE :filter_destination_value')
                        ->setParameter("filter_destination_value", '%' . $filter['value'] . '%');
                    break;
                case 'pickLocation':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('dispatch.locationFrom', 'filter_pick_location')
                        ->andWhere("filter_pick_location.id in (:pickLocationId)")
                        ->setParameter('pickLocationId', $value);
                    break;
                case 'dropLocation':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('dispatch.locationTo', 'filter_drop_location')
                        ->andWhere("filter_drop_location.id in (:dropLocationId)")
                        ->setParameter('dropLocationId', $value);
                    break;
                case 'logisticUnits':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->innerJoin('dispatch.dispatchPacks', 'filter_dispatch_packs')
                        ->innerJoin('filter_dispatch_packs.pack', 'filter_dispatch_packs_pack')
                        ->andWhere("filter_dispatch_packs_pack.id IN (:logisticUnitIds)")
                        ->setParameter('logisticUnitIds', $value);
                    break;
                case FiltreSup::FIELD_LOCATION_DROP_WITH_GROUPS:
                    if($options['fromDashboard']) {
                        $locations = explode(",", $filter["value"]);
                        $locationGroups = explode(",", $filter["value"]);
                    } else {
                        [$locations, $locationGroups] = $this->extractLocationsAndGroups($filter);
                    }

                    $qb->leftJoin('dispatch.locationTo', 'drop_location')
                        ->leftJoin("drop_location.locationGroup", "drop_location_group")
                        ->andWhere($qb->expr()->orX(
                            "drop_location.id IN (:filter_drop_locations)",
                            "drop_location_group.id IN (:filter_drop_location_groups)",
                        ))
                        ->setParameter("filter_drop_location_groups", $locationGroups)
                        ->setParameter("filter_drop_locations", $locations);
                    break;
                case FiltreSup::FIELD_LOCATION_PICK_WITH_GROUPS:
                    if($options['fromDashboard']) {
                        $locations = explode(",", $filter["value"]);
                        $locationGroups = explode(",", $filter["value"]);
                    } else {
                        [$locations, $locationGroups] = $this->extractLocationsAndGroups($filter);
                    }

                    $qb->leftJoin('dispatch.locationFrom', 'pick_location')
                        ->leftJoin("pick_location.locationGroup", "pick_location_group")
                        ->andWhere($qb->expr()->orX(
                            "pick_location.id IN (:filter_pick_locations)",
                            "pick_location_group.id IN (:filter_pick_location_groups)",
                        ))
                        ->setParameter("filter_pick_location_groups", $locationGroups)
                        ->setParameter("filter_pick_locations", $locations);
                    break;
                case FiltreSup::FIELD_BUSINESS_UNIT:
                    $values = Stream::explode(",", $filter['value'])
                        ->filter()
                        ->map(fn(string $value) => strtok($value, ':'))
                        ->toArray();
                    $qb
                        ->andWhere("dispatch.businessUnit IN (:businessUnit)")
                        ->setParameter('businessUnit', $values);
                    break;
                case FiltreSup::FIELD_PROJECT_NUMBER:
                    $value = explode(',', $filter['value']);
                    $qb
                        ->andWhere('dispatch.projectNumber IN (:filter_project_number_value)')
                        ->setParameter('filter_project_number_value', $value);
                    break;
            }
        }
        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $conditions = [
                        "creationDate" => "DATE_FORMAT(dispatch.creationDate, '%e/%m/%Y') LIKE :search_value",
                        "validationDate" => "DATE_FORMAT(dispatch.validationDate, '%e/%m/%Y') LIKE :search_value",
                        "treatmentDate" => "DATE_FORMAT(dispatch.treatmentDate, '%e/%m/%Y') LIKE :search_value",
                        "lastPartialStatusDate" => "DATE_FORMAT(dispatch.lastPartialStatusDate, '%e/%m/%Y') LIKE :search_value",
                        "endDate" => "DATE_FORMAT(dispatch.endDate, '%e/%m/%Y') LIKE :search_value",
                        "type" => "search_type.label LIKE :search_value",
                        "requester" => "search_requester.username LIKE :search_value",
                        "receivers" => "search_receivers.username LIKE :search_value",
                        "number" => "dispatch.number LIKE :search_value",
                        "locationFrom" => "search_locationFrom.label LIKE :search_value",
                        "locationTo" => "search_locationTo.label LIKE :search_value",
                        "status" => "search_statut.nom LIKE :search_value",
                        "destination" => "dispatch.destination LIKE :search_value",
                        "customerName" => "dispatch.customerName LIKE :search_value",
                        "customerPhone" => "dispatch.customerPhone LIKE :search_value",
                        "customerRecipient" => "dispatch.customerRecipient LIKE :search_value",
                        "customerAddress" => "dispatch.customerAddress LIKE :search_value",
                    ];

                    $fieldModesService->bindSearchableColumns($conditions, 'dispatch', $qb, $user, $search);

                    $qb
                        ->leftJoin('dispatch.locationFrom', 'search_locationFrom')
                        ->leftJoin('dispatch.locationTo', 'search_locationTo')
                        ->leftJoin('dispatch.statut', 'search_statut')
                        ->leftJoin('dispatch.type', 'search_type')
                        ->leftJoin('dispatch.requester','search_requester')
                        ->leftJoin('dispatch.receivers', 'search_receivers');
                }
            }
            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];
                    if ($column === 'status') {
                        $qb = QueryBuilderHelper::joinTranslations($qb, $options['language'], $options['defaultLanguage'], ['statut'], ["order" => $order]);
                    } else if ($column === 'requester') {
                        $qb
                            ->leftJoin('dispatch.requester', 'sort_requester')
                            ->orderBy('sort_requester.username', $order);
                    } else if ($column === 'type') {
                        $qb = QueryBuilderHelper::joinTranslations($qb, $options['language'], $options['defaultLanguage'], ['type'], ["order" => $order]);
                    } else if ($column === 'locationFrom') {
                        $qb
                            ->leftJoin('dispatch.locationFrom', 'sort_locationFrom')
                            ->orderBy('sort_locationFrom.label', $order);
                    } else if ($column === 'locationTo') {
                        $qb
                            ->leftJoin('dispatch.locationTo', 'sort_locationTo')
                            ->orderBy('sort_locationTo.label', $order);
                    } else {
                        $freeFieldId = FieldModesService::extractFreeFieldId($column);
                        if(is_numeric($freeFieldId)) {
                            /** @var FreeField $freeField */
                            $freeField = $this->getEntityManager()->getRepository(FreeField::class)->find($freeFieldId);
                            if($freeField->getTypage() === FreeField::TYPE_NUMBER) {
                                $qb->orderBy("CAST(JSON_EXTRACT(dispatch.freeFields, '$.\"$freeFieldId\"') AS SIGNED)", $order);
                            } else {
                                $qb->orderBy("JSON_EXTRACT(dispatch.freeFields, '$.\"$freeFieldId\"')", $order);
                            }
                        } else if (property_exists(Dispatch::class, $column) || property_exists(StatusHistoryContainer::class, $column)) {
                            $qb->orderBy("dispatch.$column", $order);
                        }
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = QueryBuilderHelper::count($qb, 'dispatch');

        $qb->select('dispatch');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

        $pageLength = $params->getInt('length') ? $params->getInt('length') : 100;
        if ($pageLength) {
            $qb->setMaxResults($pageLength);
        }

        $query = $qb->getQuery();

        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    public function countByUser($user): int {
        return $this->createQueryBuilder("dispatch")
            ->select("COUNT(dispatch)")
            ->where(":user MEMBER OF dispatch.receivers")
            ->orWhere("dispatch.requester = :user")
            ->setParameter("user", $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByLocation(Emplacement $location): int {
        return $this->createQueryBuilder("dispatch")
            ->select("COUNT(dispatch.id)")
            ->andWhere("
                dispatch.locationFrom = :location
                OR dispatch.locationTo = :location
            ")
            ->setParameter("location", $location)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param $date
     * @return mixed|null
     */
    public function getLastNumberByDate(string $date, ?string $prefix, ?string $format): ?string {
        $value = ($format === UniqueNumberService::DATE_COUNTER_FORMAT_DISPATCH)
            ? ($date . '%')
            : (Dispatch::NUMBER_PREFIX . '-' . $date . '%');
        $result = $this->createQueryBuilder('dispatch')
            ->select('dispatch.number')
            ->where('dispatch.number LIKE :value')
            ->addOrderBy('dispatch.number', 'DESC')
            ->setParameter('value', $value)
            ->getQuery()
            ->execute();
        return $result ? $result[0]['number'] : null;
    }

    public function getMobileDispatches(?Utilisateur $user = null, ?Dispatch $dispatch = null, ?bool $offlineMode = false)
    {
        $queryBuilder = $this->createQueryBuilder('dispatch');
        $queryBuilder
            ->select('dispatch_requester.username AS requester')
            ->addSelect('dispatch.id AS id')
            ->addSelect('dispatch_created_by.id AS createdBy')
            ->addSelect('dispatch.number AS number')
            ->addSelect('dispatch.startDate AS startDate')
            ->addSelect('dispatch.endDate AS endDate')
            ->addSelect('dispatch.emergency AS emergency')
            ->addSelect('locationFrom.label AS locationFromLabel')
            ->addSelect('locationFrom.id AS locationFromId')
            ->addSelect('locationTo.label AS locationToLabel')
            ->addSelect('locationTo.id AS locationToId')
            ->addSelect('dispatch.destination AS destination')
            ->addSelect('dispatch.updatedAt AS updatedAt')
            ->addSelect('dispatch.carrierTrackingNumber AS carrierTrackingNumber')
            ->addSelect('dispatch.commentaire AS comment')
            ->addSelect('type.label AS typeLabel')
            ->addSelect('type.id AS typeId')
            ->addSelect('status.id AS statusId')
            ->addSelect('status.nom AS statusLabel')
            ->addSelect('status.groupedSignatureColor AS groupedSignatureStatusColor')
            ->addSelect('IF(status.state = :draftStatusState, 1, 0) AS draft')
            ->addSelect("reference_article.reference AS packReferences")
            ->addSelect("dispatch_reference_articles.quantity AS lineQuantity")
            ->addSelect("pack.code AS packs")
            ->addSelect("GROUP_CONCAT(status_history_status.id SEPARATOR ',') AS historyStatusesId")
            ->join('dispatch.requester', 'dispatch_requester')
            ->join('dispatch.createdBy', 'dispatch_created_by')
            ->leftJoin('dispatch.dispatchPacks', 'dispatch_packs')
            ->leftJoin('dispatch_packs.pack', 'pack')
            ->leftJoin('dispatch_packs.dispatchReferenceArticles', 'dispatch_reference_articles')
            ->leftJoin('dispatch_reference_articles.referenceArticle', 'reference_article')
            ->leftJoin('dispatch.locationFrom', 'locationFrom')
            ->leftJoin('dispatch.locationTo', 'locationTo')
            ->leftJoin('dispatch.statusHistory', 'dispatch_status_history')
            ->leftJoin('dispatch_status_history.status', 'status_history_status')
            ->join('dispatch.type', 'type')
            ->join('dispatch.statut', 'status')
            ->setParameter('draftStatusState', Statut::DRAFT);

        if ($dispatch) {
            $queryBuilder
                ->andWhere("dispatch = :dispatch")
                ->setParameter("dispatch", $dispatch);
        } else {
            $queryBuilder
                ->andWhere('type.id IN (:dispatchTypeIds)');
            if ($offlineMode){
                $queryBuilder
                    ->andWhere('dispatch_created_by = :user')
                    ->andWhere($queryBuilder->expr()->orX(
                        $queryBuilder->expr()->andX(
                            'status.needsMobileSync = true',
                            'status.state IN (:untreatedStates)',
                        ),
                        'status.state = :draftStatusState',
                    ))
                ->setParameter('user', $user);
            } else {
                $queryBuilder
                    ->andWhere('status.needsMobileSync = true')
                    ->andWhere('status.state IN (:untreatedStates)');
            }
            $queryBuilder
                ->setParameter('dispatchTypeIds', $user->getDispatchTypeIds())
                ->setParameter('untreatedStates', [Statut::NOT_TREATED, Statut::PARTIAL]);
        }

        $queryBuilder = QueryBuilderHelper::setGroupBy($queryBuilder, ['historyStatusesId']);
        return $queryBuilder->getQuery()->getResult();
    }

    public function findRequestToTreatByUserAndTypes(?Utilisateur $requester, int $limit, array $types = []) {
        $qb = $this->createQueryBuilder("dispatch");

        if($requester) {
            $qb
                ->andWhere("dispatch.requester = :requester")
                ->setParameter("requester", $requester);
        }

        if(!empty($types)) {
            $qb
                ->andWhere("dispatch.type IN (:types)")
                ->setParameter("types", $types);
        }

        return $qb
            ->innerJoin("dispatch.statut", "status")
            ->leftJoin(AverageRequestTime::class, 'art', Join::WITH, 'art.type = dispatch.type')
            ->andWhere("status.state != " . Statut::TREATED)
            ->addOrderBy('status.state', 'ASC')
            ->addOrderBy("DATE_ADD(dispatch.creationDate, art.average, 'second')", 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getByDates(DateTime $dateMin, DateTime $dateMax, string $userDateFormat = Language::DMY_FORMAT): array {
        $dateMax = $dateMax->format('Y-m-d H:i:s');
        $dateMin = $dateMin->format('Y-m-d H:i:s');
        $dateFormat = Language::MYSQL_DATE_FORMATS[$userDateFormat] . " %H:%i:%s";

        $subQuery = $this->createQueryBuilder('sub_dispatch')
            ->select('COUNT(join_sub_dispatch_packs)')
            ->innerJoin('sub_dispatch.dispatchPacks', 'join_sub_dispatch_packs', Join::WITH, 'sub_dispatch.id = dispatch.id')
            ->getQuery()
            ->getDQL();

        $queryBuilder = $this->createQueryBuilder('dispatch')
            ->select('dispatch.id AS id')
            ->addSelect('dispatch.freeFields AS freeFields')
            ->addSelect('dispatch.number AS number')
            ->addSelect('dispatch.commandNumber AS orderNumber')
            ->addSelect("DATE_FORMAT(dispatch.creationDate, '$dateFormat') AS creationDate")
            ->addSelect("DATE_FORMAT(dispatch.validationDate, '$dateFormat') AS validationDate")
            ->addSelect("DATE_FORMAT(dispatch.lastPartialStatusDate, '$dateFormat') AS lastPartialStatusDate")
            ->addSelect("DATE_FORMAT(dispatch.treatmentDate, '$dateFormat') AS treatmentDate")
            ->addSelect('join_type.label AS type')
            ->addSelect('join_requester.username AS requester')
            ->addSelect("GROUP_CONCAT(join_receivers.username SEPARATOR ',') AS receivers")
            ->addSelect("join_treated_by.username AS treatedBy")
            ->addSelect("join_carrier.label AS carrier")
            ->addSelect("join_location_from.label AS locationFrom")
            ->addSelect("join_location_to.label AS locationTo")
            ->addSelect("dispatch.destination AS destination")
            ->addSelect("($subQuery) AS packCount")
            ->addSelect("join_status.nom AS status")
            ->addSelect("dispatch.businessUnit AS businessUnit")
            ->addSelect("dispatch.commentaire AS comment")
            ->addSelect("dispatch.emergency AS emergency")
            ->addSelect("dispatch.customerName AS customerName")
            ->addSelect("dispatch.customerPhone AS customerPhone")
            ->addSelect("dispatch.customerRecipient AS customerRecipient")
            ->addSelect("dispatch.customerAddress AS customerAddress")
            ->addSelect("join_dispatch_packs_pack.code AS packCode")
            ->addSelect("join_dispatch_packs_pack_nature.code AS packNature")
            ->addSelect("join_dispatch_packs.height AS dispatchPackHeight")
            ->addSelect("join_dispatch_packs.width AS dispatchPackWidth")
            ->addSelect("join_dispatch_packs.length AS dispatchPackLength")
            ->addSelect("join_dispatch_packs_pack.volume AS packVolume")
            ->addSelect("join_dispatch_packs_pack.comment AS packComment")
            ->addSelect("join_dispatch_packs_pack.quantity AS packQuantity")
            ->addSelect("join_dispatch_packs.quantity AS dispatchPackQuantity")
            ->addSelect("join_dispatch_packs_pack.weight AS packWeight")
            ->addSelect("DATE_FORMAT(join_dispatch_packs_pack_last_action.datetime, '$dateFormat') AS packLastActionDate")
            ->addSelect("join_dispatch_packs_pack_last_action_location.label AS packLastActionLocation")
            ->addSelect("join_dispatch_packs_pack_last_action_operator.username AS packLastActionOperator")
            ->leftJoin('dispatch.type', 'join_type')
            ->leftJoin('dispatch.statut', 'join_status')
            ->leftJoin('dispatch.requester', 'join_requester')
            ->leftJoin('dispatch.receivers', 'join_receivers')
            ->leftJoin('dispatch.treatedBy', 'join_treated_by')
            ->leftJoin('dispatch.carrier', 'join_carrier')
            ->leftJoin('dispatch.locationFrom', 'join_location_from')
            ->leftJoin('dispatch.locationTo', 'join_location_to')
            ->leftJoin('dispatch.dispatchPacks', 'join_dispatch_packs')
            ->leftJoin('join_dispatch_packs.pack', 'join_dispatch_packs_pack')
            ->leftJoin('join_dispatch_packs_pack.nature', 'join_dispatch_packs_pack_nature')
            ->leftJoin('join_dispatch_packs_pack.lastAction', 'join_dispatch_packs_pack_last_action')
            ->leftJoin('join_dispatch_packs_pack_last_action.emplacement', 'join_dispatch_packs_pack_last_action_location')
            ->leftJoin('join_dispatch_packs_pack_last_action.operateur', 'join_dispatch_packs_pack_last_action_operator')
            ->andWhere('dispatch.creationDate BETWEEN :dateMin AND :dateMax')
            ->groupBy('join_dispatch_packs.id');

        Stream::from($queryBuilder->getDQLParts()['select'])
            ->flatMap(fn($selectPart) => [$selectPart->getParts()[0]])
            ->map(fn($selectString) => trim(explode('AS', $selectString)[1]))
            ->filter(fn($selectAlias) => !in_array($selectAlias, ['receivers']))
            ->each(fn($field) => $queryBuilder->addGroupBy($field));

        return $queryBuilder
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ])
            ->getQuery()
            ->getResult();
    }

    public function getDispatchNumbers($search) {
        return $this->createQueryBuilder("dispatch")
            ->select("dispatch.id, dispatch.number AS text")
            ->where("dispatch.number LIKE :search")
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getResult();
    }

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @param array $dispatchStatusesFilter
     * @param array $dispatchTypesFilter
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countByDates(DateTime $dateMin,
                                 DateTime $dateMax,
                                 bool $separateType,
                                 array $dispatchStatusesFilter = [],
                                 array $dispatchTypesFilter = [],
                                 string $date = "endDate")
    {
        $qb = $this->createQueryBuilder('dispatch')
            ->select('COUNT(dispatch) ' . ($separateType ? ' AS count' : ''))
            ->join('dispatch.type','type')
            ->andWhere("dispatch.$date BETWEEN :dateMin AND :dateMax")
            ->setParameter('dateMin', $dateMin)
            ->setParameter('dateMax', $dateMax);

        if ($separateType) {
            $qb
                ->groupBy('type.id')
                ->addSelect('type.label as typeLabel');
        }
        if (!empty($dispatchStatusesFilter)) {
            $qb
                ->andWhere('dispatch.statut IN (:dispatchStatuses)')
                ->setParameter('dispatchStatuses', $dispatchStatusesFilter);
        }

        if (!empty($dispatchTypesFilter)) {
            $qb
                ->andWhere('dispatch.type IN (:dispatchTypes)')
                ->setParameter('dispatchTypes', $dispatchTypesFilter);
        }
        return $separateType ? $qb->getQuery()->getResult() : $qb->getQuery()->getSingleScalarResult();
    }

    public function getProcessingTime() {
        $threeMonthsAgo = new DateTime("-3 month");

        return $this->createQueryBuilder("dispatch")
            ->select("dispatch_type.id AS type")
            ->addSelect("SUM(UNIX_TIMESTAMP(dispatch.treatmentDate) - UNIX_TIMESTAMP(dispatch.validationDate)) AS total")
            ->addSelect("COUNT(dispatch) AS count")
            ->join("dispatch.type", "dispatch_type")
            ->join("dispatch.statut", "status")
            ->where("status.nom = :treated")
            ->andWhere("dispatch.validationDate >= :from")
            ->andWhere("dispatch.validationDate IS NOT NULL AND dispatch.treatmentDate IS NOT NULL")
            ->groupBy("dispatch.type")
            ->setParameter("from", $threeMonthsAgo)
            ->setParameter("treated", Statut::TREATED)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @param array $statuses
     * @return DateTime|null
     * @throws NonUniqueResultException
     */
    public function getOlderDateToTreat(array $types = [],
                                        array $statuses = [],
                                        array $options = []): ?DateTime {
        if (!empty($types) && !empty($statuses)) {
            $queryBuilder = $this
                ->createQueryBuilder('dispatch')
                ->select('dispatch.validationDate AS date')
                ->innerJoin('dispatch.statut', 'status')
                ->innerJoin('dispatch.type', 'type')
                ->andWhere('status IN (:statuses)')
                ->andWhere('type IN (:types)')
                ->andWhere('status.state IN (:treatedStates)')
                ->addOrderBy('dispatch.creationDate', 'ASC')
                ->setParameter('statuses', $statuses)
                ->setParameter('types', $types)
                ->setParameter('treatedStates', [Statut::PARTIAL, Statut::NOT_TREATED]);

            if(!empty($options['dispatchEmergencies'])){
                $nonUrgentCondition = in_array($options['nonUrgentTranslationLabel'], $options['dispatchEmergencies'])
                    ? 'OR dispatch.emergency IS NULL'
                    : '';

                $queryBuilder
                    ->andWhere("dispatch.emergency IN (:dispatchEmergencies) $nonUrgentCondition")
                    ->setParameter('dispatchEmergencies', $options['dispatchEmergencies']);
            }

            $res = $queryBuilder
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            return $res["date"] ?? null;
        }
        else {
            return null;
        }
    }

    public function getReceiversByDates(DateTime $dateMin,
                                        DateTime $dateMax) {
        $dateMin = $dateMin->format('Y-m-d H:i:s');
        $dateMax = $dateMax->format('Y-m-d H:i:s');

        $queryBuilder = $this->createQueryBuilder('dispatch')
            ->select('dispatch.id AS id')
            ->addSelect('join_receivers.username AS username')
            ->join('dispatch.receivers', 'join_receivers')
            ->where('dispatch.creationDate BETWEEN :dateMin AND :dateMax')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);

        $res = $queryBuilder
            ->getQuery()
            ->getResult();

        return Stream::from($res)
            ->reduce(function (array $carry, array $dispatch) {
                $id = $dispatch['id'];
                $username = $dispatch['username'];

                if (!isset($carry[$id])) {
                    $carry[$id] = [];
                }

                $carry[$id][] = $username;

                return $carry;
            }, []);
    }

    public function iterateAll(DateTime $dateTimeMin, DateTime $dateTimeMax){
        $qb = $this->createQueryBuilder('dispatch')
            ->andWhere('dispatch.creationDate BETWEEN :dateMin AND :dateMax')
            ->setParameter('dateMin', $dateTimeMin)
            ->setParameter('dateMax', $dateTimeMax);
        return $qb
            ->getQuery()
            ->toIterable();
    }

    private function extractLocationsAndGroups($filter): array {
        $items = explode(",", $filter["value"]);
        $locations = [];
        $locationGroups = [];

        foreach ($items as $item) {
            [$type, $id] = explode("-", strtok($item, ":"));
            if ($type === "locationGroup") {
                $locationGroups[] = $id;
            }
            else {
                if ($type === "location") {
                    $locations[] = $id;
                }
                else {
                    throw new RuntimeException("Unknown location type $type");
                }
            }
        }

        return [
            $locations,
            $locationGroups,
        ];
    }

    public function countByFilters(array $filters = []): int {
        $qb = $this->createQueryBuilder('dispatch')
            ->select('COUNT(dispatch)')
            ->innerJoin('dispatch.statut', 'status', JOIN::WITH, 'status.id IN (:statuses)')
            ->innerJoin('dispatch.type', 'type', JOIN::WITH, 'type.id IN (:types)')
            ->setParameter('types', $filters['types'])
            ->setParameter('statuses', $filters['statuses']);

        if(!empty($filters['pickLocations'])){
            $qb->innerJoin('dispatch.locationFrom', 'pickLocation', JOIN::WITH, 'pickLocation.id IN (:pickLocations)')
                ->setParameter('pickLocations', $filters['pickLocations']);
        }

        if(!empty($filters['dropLocations'])){
            $qb->innerJoin('dispatch.locationTo', 'dropLocation', JOIN::WITH, 'dropLocation.id IN (:dropLocations)')
                ->setParameter('dropLocations', $filters['dropLocations']);
        }

        if(!empty($filters['dispatchEmergencies'])){
            $nonUrgentCondition = in_array($filters['nonUrgentTranslationLabel'], $filters['dispatchEmergencies']) ? 'OR dispatch.emergency IS NULL' : '';

            $qb->andWhere("dispatch.emergency IN (:dispatchEmergencies) $nonUrgentCondition")
                ->setParameter('dispatchEmergencies', $filters['dispatchEmergencies']);
        }

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }
}
