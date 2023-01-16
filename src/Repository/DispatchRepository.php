<?php

namespace App\Repository;

use App\Entity\AverageRequestTime;
use App\Entity\Dispatch;
use App\Entity\FiltreSup;
use App\Entity\FreeField;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Helper\QueryBuilderHelper;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;
use App\Service\VisibleColumnService;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;

/**
 * @method Dispatch|null find($id, $lockMode = null, $lockVersion = null)
 * @method Dispatch|null findOneBy(array $criteria, array $orderBy = null)
 * @method Dispatch[]    findAll()
 * @method Dispatch[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DispatchRepository extends EntityRepository
{
    public function findByParamAndFilters(InputBag $params, $filters, Utilisateur $user, VisibleColumnService $visibleColumnService, array $options = []) {
        $qb = $this->createQueryBuilder('dispatch')
            ->groupBy('dispatch.id');

        $countTotal = QueryBuilderHelper::count($qb, 'dispatch');

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statut':
                    $value = explode(',', $filter['value']);
					$qb
						->join('dispatch.statut', 'filter_status')
						->andWhere('filter_status.id in (:statut)')
						->setParameter('statut', $value);
					break;
                case FiltreSup::FIELD_MULTIPLE_TYPES:
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('dispatch.type', 'filter_type')
                        ->andWhere('filter_type.id in (:filter_type_value)')
                        ->setParameter('filter_type_value', $value);
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

                    $qb->andWhere("dispatch.emergency IN (:filter_emergencies_value)")
                        ->setParameter("filter_emergencies_value", $value);
                    break;
                case 'dateMin':
                    $qb->andWhere('dispatch.creationDate >= :filter_dateMin_value')
                        ->setParameter('filter_dateMin_value', $filter['value'] . ' 00.00.00');
                    break;
                case 'dateMax':
                    $qb->andWhere('dispatch.creationDate <= :filter_dateMax_value')
                        ->setParameter('filter_dateMax_value', $filter['value'] . ' 23:59:59');
                    break;
                case 'destination':
                    $qb->andWhere('dispatch.destination LIKE :filter_destination_value')
                        ->setParameter("filter_destination_value", '%' . $filter['value'] . '%');
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
                        "endDate" => "DATE_FORMAT(dispatch.endDate, '%e/%m/%Y') LIKE :search_value",
                        "type" => "search_type.label LIKE :search_value",
                        "requester" => "search_requester.username LIKE :search_value",
                        "receivers" => "search_receivers.username LIKE :search_value",
                        "number" => "dispatch.number LIKE :search_value",
                        "locationFrom" => "search_locationFrom.label LIKE :search_value",
                        "locationTo" => "search_locationTo.label LIKE :search_value",
                        "status" => "search_statut.nom LIKE :search_value",
                        "destination" => "dispatch.destination LIKE :search_value",
                    ];

                    $visibleColumnService->bindSearchableColumns($conditions, 'dispatch', $qb, $user, $search);

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
                        $qb = QueryBuilderHelper::joinTranslations($qb, $options['language'], $options['defaultLanguage'], 'statut', $order);
                    } else if ($column === 'requester') {
                        $qb
                            ->leftJoin('dispatch.requester', 'sort_requester')
                            ->orderBy('sort_requester.username', $order);
                    } else if ($column === 'type') {
                        $qb = QueryBuilderHelper::joinTranslations($qb, $options['language'], $options['defaultLanguage'], 'type', $order);
                    } else if ($column === 'locationFrom') {
                        $qb
                            ->leftJoin('dispatch.locationFrom', 'sort_locationFrom')
                            ->orderBy('sort_locationFrom.label', $order);
                    } else if ($column === 'locationTo') {
                        $qb
                            ->leftJoin('dispatch.locationTo', 'sort_locationTo')
                            ->orderBy('sort_locationTo.label', $order);
                    } else {
                        $freeFieldId = VisibleColumnService::extractFreeFieldId($column);
                        if(is_numeric($freeFieldId)) {
                            /** @var FreeField $freeField */
                            $freeField = $this->getEntityManager()->getRepository(FreeField::class)->find($freeFieldId);
                            if($freeField->getTypage() === FreeField::TYPE_NUMBER) {
                                $qb->orderBy("CAST(JSON_EXTRACT(dispatch.freeFields, '$.\"$freeFieldId\"') AS SIGNED)", $order);
                            } else {
                                $qb->orderBy("JSON_EXTRACT(dispatch.freeFields, '$.\"$freeFieldId\"')", $order);
                            }
                        } else if (property_exists(Dispatch::class, $column)) {
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

    public function countByEmplacement($emplacementId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(d)
            FROM App\Entity\Dispatch d
            WHERE d.locationFrom = :emplacementId
            OR d.locationTo = :emplacementId"
        )->setParameter('emplacementId', $emplacementId);

        return $query->getSingleScalarResult();
    }

    /**
     * @param $date
     * @return mixed|null
     */
    public function getLastNumberByDate(string $date): ?string {
        $result = $this->createQueryBuilder('dispatch')
            ->select('dispatch.number')
            ->where('dispatch.number LIKE :value')
            ->orderBy('dispatch.creationDate', 'DESC')
            ->addOrderBy('dispatch.number', 'DESC')
            ->setParameter('value', Dispatch::NUMBER_PREFIX . '-' . $date . '%')
            ->getQuery()
            ->execute();
        return $result ? $result[0]['number'] : null;
    }

    public function getMobileDispatches(?Utilisateur $user = null, ?Dispatch $dispatch = null)
    {
        $queryBuilder = $this->createQueryBuilder('dispatch');
        $queryBuilder
            ->select('dispatch_requester.username AS requester')
            ->addSelect('dispatch.id AS id')
            ->addSelect('dispatch.number AS number')
            ->addSelect('dispatch.startDate AS startDate')
            ->addSelect('dispatch.endDate AS endDate')
            ->addSelect('dispatch.emergency AS emergency')
            ->addSelect('locationFrom.label AS locationFromLabel')
            ->addSelect('locationTo.label AS locationToLabel')
            ->addSelect('dispatch.destination AS destination')
            ->addSelect('type.label AS typeLabel')
            ->addSelect('type.id AS typeId')
            ->addSelect('status.id AS statusId')
            ->addSelect('status.nom AS statusLabel')
            ->join('dispatch.requester', 'dispatch_requester')
            ->leftJoin('dispatch.locationFrom', 'locationFrom')
            ->leftJoin('dispatch.locationTo', 'locationTo')
            ->join('dispatch.type', 'type')
            ->join('dispatch.statut', 'status');

        if ($dispatch) {
            $queryBuilder
                ->andWhere("dispatch = :dispatch")
                ->setParameter("dispatch", $dispatch);
        } else {
            $queryBuilder
                ->andWhere('type.id IN (:dispatchTypeIds)')
                ->andWhere('status.needsMobileSync = true')
                ->andWhere('status.state IN (:untreatedStates)')
                ->setParameter('dispatchTypeIds', $user->getDispatchTypeIds())
                ->setParameter('untreatedStates', [Statut::NOT_TREATED, Statut::PARTIAL]);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function findRequestToTreatByUser(?Utilisateur $requester, int $limit) {
        $qb = $this->createQueryBuilder("dispatch");

        if($requester) {
            $qb
                ->andWhere("dispatch.requester = :requester")
                ->setParameter("requester", $requester);
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

    public function iterateByDates(DateTime $dateMin,
                                   DateTime $dateMax): iterable {
        $dateMax = $dateMax->format('Y-m-d H:i:s');
        $dateMin = $dateMin->format('Y-m-d H:i:s');

        $queryBuilder = $this->createQueryBuilder('dispatch')
            ->andWhere('dispatch.creationDate BETWEEN :dateMin AND :dateMax')

            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);

        return $queryBuilder
            ->getQuery()
            ->toIterable();
    }

    /**
     * Assoc array [dispatch number => nb packs]
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return array [dispatch number => nb packs]
     */
    public function getNbPacksByDates(DateTime $dateMin,
                                      DateTime $dateMax): array {
        $dateMax = $dateMax->format('Y-m-d H:i:s');
        $dateMin = $dateMin->format('Y-m-d H:i:s');

        $queryBuilder = $this->createQueryBuilder('dispatch')
            ->addSelect('dispatch.number AS number')
            ->addSelect('COUNT(join_dispatchPack.id) AS nbPacks')

            ->leftJoin('dispatch.dispatchPacks', 'join_dispatchPack')

            ->andWhere('dispatch.creationDate BETWEEN :dateMin AND :dateMax')

            ->groupBy('dispatch.number')

            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ]);

        $res = $queryBuilder
            ->getQuery()
            ->getResult();

        return Stream::from($res)
            ->reduce(function (array $carry, array $row) {
                $carry[$row['number']] = $row['nbPacks'];
                return $carry;
            }, []);
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
                                        array $statuses = []): ?DateTime {
        if (!empty($types) && !empty($statuses)) {
            $res = $this
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
                ->setParameter('treatedStates', [Statut::PARTIAL, Statut::NOT_TREATED])
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            return $res['date'] ?? null;
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
}
