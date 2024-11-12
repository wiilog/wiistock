<?php

namespace App\Repository;

use App\Entity\Attachment;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\FiltreSup;
use App\Entity\Language;
use App\Entity\ProductionRequest;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Entity\WorkPeriod\WorkedDay;
use App\Entity\WorkPeriod\WorkFreeDay;
use App\Helper\QueryBuilderHelper;
use App\Service\FieldModesService;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;

class ProductionRequestRepository extends EntityRepository
{

    public function getLastNumberByDate(string $date): ?string {
        $result = $this->createQueryBuilder('production_request')
            ->select('production_request.number')
            ->where('production_request.number LIKE :value')
            ->addOrderBy('production_request.number', 'DESC')
            ->setParameter('value', ProductionRequest::NUMBER_PREFIX . '-' . $date . '%')
            ->getQuery()
            ->execute();
        return $result ? $result[0]['number'] : null;
    }


    public function findByParamsAndFilters(InputBag $params, array $filters, FieldModesService $fieldModesService, array $options = []): array {
        $qb = $this->createQueryBuilder('production_request')
            ->groupBy('production_request.id');

        $total = QueryBuilderHelper::count($qb, 'production_request');

        $dateChoiceConfig = Stream::from($filters)->find(static fn($filter) => $filter['field'] === 'date-choice')
            ?? Stream::from(FiltreSup::DATE_CHOICE_VALUES[ProductionRequest::class])->find(static fn($config) => $config['default'] ?? false);
        $dateChoice = $dateChoiceConfig["value"] ?? '';
        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'dateMin':
                    $filteredDate = match ($dateChoice){
                        'expectedAt' => 'expectedAt',
                        default => 'createdAt'
                    };
                    $qb->andWhere("production_request.{$filteredDate} >= :filter_dateMin_value")
                        ->setParameter('filter_dateMin_value', $filter['value'] . ' 00.00.00');
                    break;
                case 'dateMax':
                    $filteredDate = match ($dateChoice){
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
                            ->andWhere('filter_type.id IN (:filter_type_value)')
                            ->setParameter('filter_type_value', $value);
                    }
                    break;
                case 'statuses-filter':
                case 'statut':
                    if(!empty($filter['value'])) {
                        $value = explode(',', $filter['value']);
                        $qb
                            ->join('production_request.status', 'filter_status')
                            ->andWhere('filter_status.id IN (:status)')
                            ->setParameter('status', $value);
                    }
                    break;
                case 'requesters':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('production_request.createdBy', 'filter_createdBy')
                        ->andWhere('filter_createdBy.id IN (:filter_createdBy_values)')
                        ->setParameter('filter_createdBy_values', $value);
                    break;
                case FixedFieldEnum::dropLocation->name:
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('production_request.dropLocation', 'filter_dropLocation')
                        ->andWhere('filter_dropLocation.id IN (:filter_dropLocation_values)')
                        ->setParameter('filter_dropLocation_values', $value);
                    break;
                case FixedFieldEnum::manufacturingOrderNumber->name:
                    $value = $filter['value'];
                    $qb
                        ->andWhere('production_request.manufacturingOrderNumber LIKE :filter_manufactoringOrderNumber_value')
                        ->setParameter('filter_manufactoringOrderNumber_value', '%' . $value . '%');
                    break;
                case FixedFieldEnum::productArticleCode->name:
                    $value = $filter['value'];
                    $qb
                        ->andWhere('production_request.productArticleCode LIKE :filter_productArticleCode_value')
                        ->setParameter('filter_productArticleCode_value', '%' . $value . '%');
                    break;
                case 'attachmentsAssigned':
                    if ($filter['value'] == '1') {
                        $qb
                            ->join('production_request.attachments', 'filter_attachments')
                            ->andWhere('filter_attachments IS NOT NULL');
                        break;
                    }
                case 'emergencyMultiple':
                    $value = array_map(function ($value) {
                        return explode(":", $value)[0];
                    }, explode(',', $filter['value']));
                    $qb
                        ->andWhere("production_request.emergency in (:filter_emergency_value)")
                        ->setParameter('filter_emergency_value', $value);
                    break;
            }
        }

        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $conditions = [
                        FixedFieldEnum::number->name => "production_request.number LIKE :search_value",
                        FixedFieldEnum::createdAt->name => "DATE_FORMAT(production_request.createdAt, '%d/%m/%Y') LIKE :search_value",
                        FixedFieldEnum::createdBy->name => "search_createdBy.username LIKE :search_value",
                        FixedFieldEnum::treatedBy->name => "search_treatedBy.username LIKE :search_value",
                        FixedFieldEnum::type->name => "search_type.label LIKE :search_value",
                        FixedFieldEnum::status->name => "search_status.nom LIKE :search_value",
                        FixedFieldEnum::expectedAt->name => "DATE_FORMAT(production_request.expectedAt, '%d/%m/%Y') LIKE :search_value",
                        FixedFieldEnum::dropLocation->name => "search_dropLocation.label LIKE :search_value",
                        FixedFieldEnum::destinationLocation->name => "search_destinationLocation.label LIKE :search_value",
                        FixedFieldEnum::lineCount->name => "production_request.lineCount LIKE :search_value",
                        FixedFieldEnum::manufacturingOrderNumber->name => "production_request.manufacturingOrderNumber LIKE :search_value",
                        FixedFieldEnum::productArticleCode->name => "production_request.productArticleCode LIKE :search_value",
                        FixedFieldEnum::quantity->name => "production_request.quantity LIKE :search_value",
                        FixedFieldEnum::emergency->name => "production_request.emergency LIKE :search_value",
                        FixedFieldEnum::projectNumber->name => "production_request.projectNumber LIKE :search_value",
                        FixedFieldEnum::comment->name => "production_request.comment LIKE :search_value",
                    ];

                    $fieldModesService->bindSearchableColumns($conditions, 'productionRequest', $qb, $options['user'], $search);

                    $qb
                        ->leftJoin('production_request.status', 'search_status')
                        ->leftJoin('production_request.treatedBy', 'search_treatedBy')
                        ->innerJoin('production_request.createdBy', 'search_createdBy')
                        ->leftJoin('production_request.dropLocation', 'search_dropLocation')
                        ->leftJoin('production_request.destinationLocation', 'search_destinationLocation')
                        ->innerJoin('production_request.type', 'search_type');
                }
            }

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];

                    if ($column === FixedFieldEnum::number->name) {
                        $qb->orderBy('production_request.number', $order);
                    } else if ($column === 'createdAt') {
                        $qb->orderBy('production_request.createdAt', $order);
                    } else if ($column === FixedFieldEnum::treatedBy->name) {
                        $qb
                            ->leftJoin('production_request.treatedBy', 'order_treatedBy')
                            ->orderBy('order_treatedBy.username', $order);
                    } else if ($column === FixedFieldEnum::createdAt->name) {
                        $qb
                            ->leftJoin('production_request.createdBy', 'order_createdBy')
                            ->orderBy('order_createdBy.username', $order);
                    } else if ($column === FixedFieldEnum::type->name) {
                        $qb
                            ->leftJoin('production_request.type', 'type')
                            ->orderBy('type.label', $order);
                    } else if ($column === FixedFieldEnum::status->name) {
                        $qb
                            ->leftJoin('production_request.status', 'status')
                            ->orderBy('status.nom', $order);
                    } else if ($column === FixedFieldEnum::expectedAt->name) {
                        $qb->orderBy('production_request.expectedAt', $order);
                    } else if ($column === FixedFieldEnum::dropLocation->name) {
                        $qb
                            ->leftJoin('production_request.dropLocation', 'order_drop_location')
                            ->orderBy('order_drop_location.label', $order);
                    } else if ($column === FixedFieldEnum::destinationLocation->name) {
                        $qb
                            ->leftJoin('production_request.destinationLocation', 'order_destination_location')
                            ->orderBy('order_destination_location.label', $order);
                    } else if ($column === FixedFieldEnum::lineCount->name) {
                        $qb->orderBy('production_request.lineCount', $order);
                    } else if ($column === FixedFieldEnum::manufacturingOrderNumber->name) {
                        $qb->orderBy('production_request.manufacturingOrderNumber', $order);
                    } else if ($column === FixedFieldEnum::productArticleCode->name) {
                        $qb->orderBy('production_request.productArticleCode', $order);
                    } else if ($column === FixedFieldEnum::quantity->name) {
                        $qb->orderBy('production_request.quantity', $order);
                    } else if ($column === FixedFieldEnum::emergency->name) {
                        $qb->orderBy('production_request.emergency', $order);
                    } else if ($column === FixedFieldEnum::projectNumber->name) {
                        $qb->orderBy('production_request.projectNumber', $order);
                    } else if ($column === FixedFieldEnum::comment->name) {
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

        if($options['dispatchMode']) {
            $qb->orderBy("production_request.createdAt", "DESC");
        }

        return [
            'data' => $qb->getQuery()->getResult(),
            'count' => $filtered,
            'total' => $total
        ];
    }

    public function getByDates(DateTime $dateMin,
                               DateTime $dateMax,
                               InputBag $filters,
                               array    $options = []): array
    {
        $defaultLanguage = $options["defaultLanguage"];
        $language = $options["language"] ?: $defaultLanguage;
        $dateFormat = Language::MYSQL_DATE_FORMATS[$options["userDateFormat"]] . " %H:%i:%s";

        $referenceDate = $filters->getBoolean("date-choice_createdAt") ? "createdAt" : "expectedAt";
        $types = $filters->has("multipleTypes")
            ? Stream::from($filters->all("multipleTypes"))
                ->map(static fn(array $type) => intval($type["id"]))
                ->toArray()
            : [];
        $statuses = Stream::from($filters->all())
            ->filterMap(static fn(string|array $value, string $key) => $value === "true" && str_starts_with($key, "statuses-filter_") ? $key : null)
            ->map(static fn(string $value) => intval(explode("_", $value)[1]))
            ->values();
        $requesters = $filters->has("requesters")
            ? Stream::from($filters->all("requesters"))
                ->map(static fn(array $user) => intval($user["id"]))
                ->toArray()
            : [];
        $dropLocations = $filters->has("dropLocation")
            ? Stream::from($filters->all("dropLocation"))
                ->map(static fn(array $dropLocation) => intval($dropLocation["id"]))
                ->toArray()
            : [];
        $emergencies = $filters->has("emergencyMultiple")
            ? Stream::from($filters->all("emergencyMultiple"))
                ->map(static fn(array $emergency) => $emergency["id"])
                ->toArray()
            : [];
        $manufacturingOrderNumber = $filters->get("manufacturingOrderNumber");
        $productArticleCode = $filters->get("productArticleCode");
        $hasAttachments = $filters->getBoolean("attachmentAssigned");

        $queryBuilder = $this->createQueryBuilder("production_request")
            ->select("production_request.id AS " . FixedFieldEnum::id->name)
            ->addSelect("production_request.number AS " . FixedFieldEnum::number->name)
            ->addSelect("DATE_FORMAT(production_request.createdAt, '$dateFormat') AS " . FixedFieldEnum::createdAt->name)
            ->addSelect("join_createdBy.username AS " . FixedFieldEnum::createdBy->name)
            ->addSelect("join_treatedBy.username AS " . FixedFieldEnum::treatedBy->name)
            ->addSelect("COALESCE(join_translation_type.translation, join_translation_default_type.translation, join_type.label) AS " . FixedFieldEnum::type->name)
            ->addSelect("COALESCE(join_translation_status.translation, join_translation_default_status.translation, join_status.nom) AS " . FixedFieldEnum::status->name)
            ->addSelect("DATE_FORMAT(production_request.expectedAt, '$dateFormat') AS " . FixedFieldEnum::expectedAt->name)
            ->addSelect("join_dropLocation.label AS " . FixedFieldEnum::dropLocation->name)
            ->addSelect("production_request.lineCount AS " . FixedFieldEnum::lineCount->name)
            ->addSelect("production_request.manufacturingOrderNumber AS " . FixedFieldEnum::manufacturingOrderNumber->name)
            ->addSelect("production_request.productArticleCode AS " . FixedFieldEnum::productArticleCode->name)
            ->addSelect("production_request.quantity " . FixedFieldEnum::quantity->name)
            ->addSelect("production_request.emergency AS " . FixedFieldEnum::emergency->name)
            ->addSelect("production_request.projectNumber AS " . FixedFieldEnum::projectNumber->name)
            ->addSelect("production_request.comment AS " . FixedFieldEnum::comment->name)
            ->addSelect("join_destinationLocation.label AS " . FixedFieldEnum::destinationLocation->name)
            ->addSelect("production_request.freeFields AS freeFields")
            ->innerJoin("production_request.createdBy", "join_createdBy")
            ->leftJoin("production_request.treatedBy", "join_treatedBy")
            ->leftJoin("production_request.dropLocation", "join_dropLocation")
            ->leftJoin("production_request.destinationLocation", "join_destinationLocation")
            ->andWhere("production_request.$referenceDate BETWEEN :dateMin AND :dateMax")
            ->setParameter("dateMin", $dateMin)
            ->setParameter("dateMax", $dateMax);

        if(!empty($types)) {
            $queryBuilder
                ->andWhere("join_type.id IN (:types)")
                ->setParameter("types", $types);
        }

        if(!empty($statuses)) {
            $queryBuilder
                ->andWhere("join_status.id IN (:statuses)")
                ->setParameter("statuses", $statuses);
        }

        if(!empty($requesters)) {
            $queryBuilder
                ->andWhere("join_createdBy.id IN (:requesters)")
                ->setParameter("requesters", $requesters);
        }

        if(!empty($dropLocations)) {
            $queryBuilder
                ->andWhere("join_dropLocation.id IN (:dropLocations)")
                ->setParameter("dropLocations", $dropLocations);
        }

        if(!empty($emergencies)) {
            $queryBuilder
                ->andWhere("production_request.emergency IN (:emergencies)")
                ->setParameter("emergencies", $emergencies);
        }

        if($manufacturingOrderNumber) {
            $queryBuilder
                ->andWhere("production_request.manufacturingOrderNumber LIKE :manufacturingOrderNumber")
                ->setParameter("manufacturingOrderNumber", "%$manufacturingOrderNumber%");
        }

        if($productArticleCode) {
            $queryBuilder
                ->andWhere("production_request.productArticleCode LIKE :productArticleCode")
                ->setParameter("productArticleCode", "%$productArticleCode%");
        }

        if($hasAttachments) {
            $subAttachmentQueryBuilder = $this->getEntityManager()
                ->createQueryBuilder()
                ->from(Attachment::class, "attachment")
                ->select("attachment.id")
                ->andWhere("attachment MEMBER OF production_request.attachments")
                ->getQuery()
                ->getDQL();

            $queryBuilder
                ->andWhere("FIRST($subAttachmentQueryBuilder) IS NOT NULL");
        }

        $queryBuilder = QueryBuilderHelper::joinTranslations($queryBuilder, $language, $defaultLanguage, ["status", "type"]);

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Statut[] $statuses
     * @return ProductionRequest[]
     */
    public function findByStatusCodesAndExpectedAt(array $filters, array $statuses, DateTime $start, DateTime $end): array {
        if (empty($statuses)) {
            return [];
        }

        $startStr = $start->format('Y-m-d');
        $endStr = $end->format('Y-m-d');

        $queryBuilder = $this->createQueryBuilder('production_request');

        $expr = $queryBuilder->expr();

        $daysWorkedSubQuery = $this->getEntityManager()->createQueryBuilder()
            ->select("sub_daysWorked.day")
            ->from(WorkedDay::class, "sub_daysWorked")
            ->andWhere("sub_daysWorked.worked = 1")
            ->getQuery()
            ->getDQL();

        $workFreeDaysSubQuery = $this->getEntityManager()->createQueryBuilder()
            ->select("sub_workFreeDay.day")
            ->from(WorkFreeDay::class, "sub_workFreeDay")
            ->getQuery()
            ->getDQL();

        $queryBuilder = $queryBuilder
            ->innerJoin('production_request.status', 'join_status')
            ->andWhere($expr->andX(
                "LOWER(DAYNAME(production_request.expectedAt)) IN ($daysWorkedSubQuery)",
                "DATE_FORMAT(production_request.expectedAt, '%Y-%m-%d') NOT IN ($workFreeDaysSubQuery)",
            ))
            ->andWhere('production_request.expectedAt BETWEEN :start AND :end')
            ->andWhere('join_status IN (:statuses)')
            ->setParameter('statuses', $statuses)
            ->setParameter('start', $startStr)
            ->setParameter('end', $endStr)
            ->orderBy("
                IF(
                    production_request.emergency IS NOT NULL AND production_request.emergency <> '',
                    1,
                    0
                )
            ", Criteria::DESC)
            ->addOrderBy("production_request.expectedAt", Criteria::ASC)
            ->addOrderBy("production_request.createdAt", Criteria::ASC);

        foreach ($filters as $filter) {
            if ($filter['field'] === FiltreSup::FIELD_OPERATORS) {
                $users = explode(',', $filter['value']);
                $queryBuilder
                    ->join('production_request.createdBy', 'filter_createdBy')
                    ->andWhere('filter_createdBy.id IN (:users)')
                    ->setParameter('users', $users);
            } else if ($filter['field'] === FiltreSup::FIELD_MULTIPLE_TYPES) {
                $types = explode(",", $filter["value"]);
                $queryBuilder
                    ->join('production_request.type', 'filter_type')
                    ->andWhere('filter_type.id IN (:types)')
                    ->setParameter('types', $types);
            } else if ($filter['field'] === 'statuses-filter') {
                $statuses = explode(",", $filter["value"]);
                $queryBuilder
                    ->join('production_request.status', 'filter_status')
                    ->andWhere('filter_status.id IN (:statuses)')
                    ->setParameter('statuses', $statuses);
            } else if ($filter['field'] === FiltreSup::FIELD_REQUEST_NUMBER) {
                $queryBuilder
                    ->andWhere('production_request.number = :number')
                    ->setParameter('number', $filter['value']);
            }
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function findRequestToTreatByUserAndTypes(?Utilisateur $requester, int $limit, array $types = []): array {
        $qb = $this->createQueryBuilder("production_request");

        if($requester) {
            $qb
                ->andWhere("production_request.createdBy = :createdBy")
                ->setParameter("createdBy", $requester);
        }

        if(!empty($types)) {
            $qb
                ->andWhere("production_request.type IN (:types)")
                ->setParameter("types", $types);
        }

        return $qb
            ->innerJoin("production_request.status", "join_status")
            ->andWhere('join_status.state != :statusState')
            ->addOrderBy('join_status.state', Criteria::ASC)
            ->addOrderBy('production_request.createdAt', Criteria::ASC)
            ->setParameter('statusState', Statut::TREATED)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByDates(DateTime $dateMin,
                                 DateTime $dateMax,
                                 bool $separateType,
                                 array $productionStatusesFilter = [],
                                 array $productionTypesFilter = [],
                                 string $date = "createdAt"): array|int
    {
        $associatedFieldLabelWithName = [
            "creationDate" => "createdAt",
            "expectedDate" => "expectedAt",
            "treatmentDate" => "treatedAt",
        ];

        $qb = $this->createQueryBuilder('production_request')
            ->select('COUNT(production_request) ' . ($separateType ? ' AS count' : ''))
            ->join('production_request.type','join_type')
            ->andWhere("production_request.$associatedFieldLabelWithName[$date] BETWEEN :dateMin AND :dateMax")
            ->setParameter('dateMin', $dateMin)
            ->setParameter('dateMax', $dateMax);

        if ($separateType) {
            $qb
                ->groupBy('join_type.id')
                ->addSelect('join_type.label AS typeLabel');
        }

        if (!empty($productionStatusesFilter)) {
            $qb
                ->andWhere('production_request.status IN (:productionStatuses)')
                ->setParameter('productionStatuses', $productionStatusesFilter);
        }

        if (!empty($productionTypesFilter)) {
            $qb
                ->andWhere('production_request.type IN (:productionTypes)')
                ->setParameter('productionTypes', $productionTypesFilter);
        }
        return $separateType ? $qb->getQuery()->getResult() : $qb->getQuery()->getSingleScalarResult();
    }

    public function getOlderDateToTreat(array $types = [],
                                        array $statuses = []): ?DateTime {
        if (!empty($types) && !empty($statuses)) {
            $res = $this
                ->createQueryBuilder('production_request')
                ->select('production_request.createdAt AS date')
                ->innerJoin('production_request.status', 'status')
                ->innerJoin('production_request.type', 'type')
                ->andWhere('status IN (:statuses)')
                ->andWhere('type IN (:types)')
                ->andWhere('status.state = :treatedState')
                ->addOrderBy('production_request.createdAt', 'ASC')
                ->setParameter('statuses', $statuses)
                ->setParameter('types', $types)
                ->setParameter('treatedState', Statut::NOT_TREATED)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            return $res["date"] ?? null;
        }
        else {
            return null;
        }
    }
}
