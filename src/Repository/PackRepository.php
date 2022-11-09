<?php

namespace App\Repository;

use App\Entity\Emplacement;
use App\Entity\IOT\Sensor;
use App\Entity\LocationGroup;
use App\Entity\Pack;
use App\Entity\Reception;
use App\Entity\ReceptionLine;
use App\Entity\TrackingMovement;
use App\Helper\QueryBuilderHelper;
use DateTimeInterface;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\HttpFoundation\InputBag;
use WiiCommon\Helper\Stream;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use WiiCommon\Helper\StringHelper;

/**
 * @method Pack|null find($id, $lockMode = null, $lockVersion = null)
 * @method Pack|null findOneBy(array $criteria, array $orderBy = null)
 * @method Pack[]    findAll()
 * @method Pack[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PackRepository extends EntityRepository
{

    public const PACKS_MODE = 'packs';
    public const GROUPS_MODE = 'groups';

    private const DtToDbLabels = [
        'packNum' => 'code',
        'packNature' => 'packNature',
        'packLastDate' => 'packLastDate',
        'packOrigin' => 'packOrigin',
        'packLocation' => 'packLocation',
        'quantity' => 'quantity',
        'arrivageType' => 'arrivage'
    ];

    public function countPacksByDates(DateTime $dateMin,
                                      DateTime $dateMax,
                                      bool $groupByNature = false,
                                      array $arrivalStatusesFilter = [],
                                      array $arrivalTypesFilter = [])
    {
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

    public function getPacksByDates(DateTime $dateMin, DateTime $dateMax)
    {
        return $this->createQueryBuilder('pack')
            ->select('pack.code as code')
            ->addSelect('n.label as nature')
            ->addSelect('m.datetime as lastMvtDate')
            ->addSelect('m.id as fromTo')
            ->addSelect('emplacement.label as location')
            ->leftJoin('pack.lastTracking', 'm')
            ->leftJoin('m.emplacement','emplacement')
            ->leftJoin('pack.nature','n')
            ->leftJoin('pack.arrivage', 'arrivage')
            ->where('m.datetime BETWEEN :dateMin AND :dateMax')
            ->andWhere('pack.groupIteration IS NULL')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ])
            ->getQuery()
            ->toIterable();
    }

    public function getGroupsByDates(DateTime $dateMin, DateTime $dateMax) {
        return $this->createQueryBuilder("pack")
            ->select("pack AS group")
            ->addSelect("COUNT(child.id) AS packCounter")
            ->leftJoin("pack.lastTracking", "movement")
            ->leftJoin("pack.children","child")
            ->where("movement.datetime BETWEEN :dateMin AND :dateMax")
            ->andWhere('pack.groupIteration IS NOT NULL')
            ->groupBy('pack')
            ->setParameter("dateMin", $dateMin)
            ->setParameter("dateMax", $dateMax)
            ->getQuery()
            ->getResult();
    }

    public function countAllPacks()
    {
        $queryBuilder = $this->createQueryBuilder('pack')
            ->select('COUNT(pack)')
            ->where('pack.groupIteration IS NULL');
        return $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return int|mixed|string
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countAllGroups()
    {
        $queryBuilder = $this->createQueryBuilder('pack')
            ->select('COUNT(pack)')
            ->where('pack.groupIteration IS NOT NULL');
        return $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByParamsAndFilters(InputBag $params, $filters, string $mode, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('pack')
            ->groupBy('pack.id');

        if ($mode === self::PACKS_MODE) {
            $queryBuilder
                ->leftJoin('pack.article', 'article')
                ->andWhere('article.currentLogisticUnit IS NULL')
                ->andWhere('pack.groupIteration IS NULL');
            $countTotal = QueryBuilderHelper::count($queryBuilder, 'pack');
        }
        else if ($mode === self::GROUPS_MODE) {
            $queryBuilder->where('pack.groupIteration IS NOT NULL');
            $countTotal = QueryBuilderHelper::count($queryBuilder, 'pack');
        }

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'emplacement':
                    $emplacementValue = explode(',', $filter['value']);
                    $queryBuilder
                        ->join('pack.lastTracking', 'mFilter0')
                        ->join('mFilter0.emplacement', 'e')
                        ->andWhere('e.id IN (:location)')
                        ->setParameter('location', $emplacementValue, Connection::PARAM_INT_ARRAY);
                    break;
                case 'dateMin':
                    $queryBuilder
                        ->join('pack.lastTracking', 'mFilter1')
                        ->andWhere('mFilter1.datetime >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $queryBuilder
                        ->join('pack.lastTracking', 'mFilter2')
                        ->andWhere('mFilter2.datetime <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
                case 'colis':
                    $queryBuilder
                        ->andWhere('pack.code LIKE :colis')
                        ->setParameter('colis', '%' . $filter['value'] . '%');
                    break;
                case 'numArrivage':
                    $queryBuilder
                        ->join('pack.arrivage', 'a')
                        ->andWhere('a.numeroArrivage LIKE :arrivalNumber')
                        ->setParameter('arrivalNumber', '%' . $filter['value'] . '%');
                    break;
                case 'type':
                    $queryBuilder
                        ->join('pack.arrivage', 'a_type')
                        ->join('a_type.type','type')
                        ->andWhere('type.label LIKE :types')
                        ->setParameter('types', '%' . $filter['value'] . '%');
                    break;
                case 'natures':
                    $natures = explode(',', $filter['value']);
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
            }
        }

        //Filter search
        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $queryBuilder
                        ->leftJoin('pack.lastTracking', 'm2')
                        ->leftJoin('m2.emplacement', 'e2')
                        ->leftJoin('pack.nature', 'n2')
                        ->leftJoin('pack.arrivage', 'arrivage')
                        ->leftJoin('arrivage.type','arrival_type')
                        ->leftJoin('pack.childArticles', 'child_articles_search')
                        ->andWhere("(
                            pack.code LIKE :value OR
                            e2.label LIKE :value OR
                            n2.label LIKE :value OR
                            arrivage.numeroArrivage LIKE :value OR
                            arrival_type.label LIKE :value OR
                            child_articles_search.barCode LIKE :value
						)")
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = self::DtToDbLabels[$params->all('columns')[$params->all('order')[0]['column']]['data']] ?? 'id';
                    if ($column === 'packLocation') {
                        $queryBuilder
                            ->leftJoin('pack.lastTracking', 'm3')
                            ->leftJoin('m3.emplacement', 'e3')
                            ->orderBy('e3.label', $order);
                    } else if ($column === 'packNature') {
                        $queryBuilder = QueryBuilderHelper::joinTranslations($queryBuilder, $options['language'], $options['defaultLanguage'], 'nature', $order);
                    } else if ($column === 'packLastDate') {
                        $queryBuilder
                            ->leftJoin('pack.lastTracking', 'm3')
                            ->orderBy('m3.datetime', $order);
                    } else if ($column === 'packOrigin') {
                        $queryBuilder
                            ->leftJoin('pack.arrivage', 'arrivage3')
                            ->orderBy('arrivage3.numeroArrivage', $order);
                    } else if ($column === 'arrivageType') {
                        $queryBuilder
                            ->leftJoin('pack.arrivage', 'arrivage3')
                            ->orderBy('arrivage3.type', $order);
                    } else if ($column === 'pairing') {
                        $queryBuilder
                            ->leftJoin('pack.pairings', 'order_pairings')
                            ->orderBy('order_pairings.active', $order);
                    } else {
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
        $countFiltered = QueryBuilderHelper::count($queryBuilder, 'pack');

        $queryBuilder
            ->select('pack');

        if ($params->getInt('start')) $queryBuilder->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $queryBuilder->setMaxResults($params->getInt('length'));

        $query = $queryBuilder->getQuery();
        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    public function getCurrentPackOnLocations(array $locations, array $options = [])
    {
        $natures = $options['natures'] ?? [];
        $isCount = $options['isCount'] ?? true;
        $field = $options['field'] ?? 'colis.id';
        $start = $options['start'] ?? null;
        $limit = $options['limit'] ?? null;
        $order = $options['order'] ?? 'desc';
        $onlyLate = $options['onlyLate'] ?? false;

        $queryBuilder = $this->createQueryBuilder('colis');
        $queryBuilderExpr = $queryBuilder->expr();
        $queryBuilder
            ->select($isCount ? $queryBuilderExpr->count($field) : $field)
            ->leftJoin('colis.nature', 'nature')
            ->leftJoin('colis.arrivage', 'pack_arrival')
            ->join('colis.lastDrop', 'lastDrop')
            ->join('lastDrop.emplacement', 'emplacement')
            ->where('colis.groupIteration IS NULL');

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
                    $queryBuilderExpr->between('lastDrop.datetime', ':dateFrom', ':dateTo')
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

        $queryBuilder->orderBy('lastDrop.datetime', $order);

        if ($onlyLate) {
            $queryBuilder
                ->andWhere(
                    $queryBuilderExpr->isNotNull('emplacement.dateMaxTime')
                );
        }

        if ($start) {
            $queryBuilder->setFirstResult((int) $start);
        }

        if ($limit) {
            $queryBuilder->setMaxResults((int) $limit);
        }

        if ($isCount) {
            return $queryBuilder
                ->getQuery()
                ->getSingleScalarResult();
        }

        return $queryBuilder
            ->getQuery()
            ->execute();
    }

    public function countPacksByArrival(DateTime $from, DateTime $to) {
        $queryBuilder = $this->createQueryBuilder('colis');
        $queryBuilderExpr = $queryBuilder->expr();
        $queryBuilder
            ->select('count(colis.id) as nbColis')
            ->addSelect('nature.id AS natureId')
            ->addSelect('arrivage.id AS arrivageId')
            ->join('colis.nature', 'nature')
            ->join('colis.arrivage', 'arrivage')
            ->where($queryBuilderExpr->between('arrivage.date', ':dateFrom', ':dateTo'))
            ->andWhere('colis.groupIteration IS NULL')
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
                $nbColis = $counter['nbColis'];
                if (!isset($carry[$arrivageId])) {
                    $carry[$arrivageId] = [];
                }
                $carry[$arrivageId][$natureId] = intval($nbColis);
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
                ->addSelect('join_type_last_drop.code AS type')
                ->addSelect('join_location.label AS ref_emplacement')
                ->addSelect('join_last_drop.datetime AS date')
                ->addSelect('join_last_drop.quantity AS quantity')
                ->addSelect('join_nature.id AS nature_id')
                ->join('pack.lastDrop', 'join_last_drop')
                ->leftJoin('pack.nature', 'join_nature')
                ->join('join_last_drop.type', 'join_type_last_drop')
                ->join('join_last_drop.emplacement', 'join_location')
                ->andWhere('pack.groupIteration IS NULL')
                ->andWhere($exprBuilder->in('pack.id', ':packIds'))
                ->setParameter('packIds', $packIds)
                ->getQuery()
                ->getResult()
        )
            ->map(function($pack) {
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

    public function getForSelect(?string $term, ?array $exclude = null, ?bool $withoutArticle = false) {
        if($exclude && !is_array($exclude)) {
            $exclude = [$exclude];
        }

        $qb = $this->createQueryBuilder("pack")
            ->select("pack.id AS id")
            ->addSelect("pack.code AS text")
            ->addSelect("nature.id AS nature_id")
            ->addSelect("nature.label AS nature_label")
            ->addSelect("pack.weight AS weight")
            ->addSelect("pack.volume AS volume")
            ->addSelect("pack.comment AS comment")
            ->addSelect("DATE_FORMAT(last_tracking.datetime, '%d/%m/%Y %H:%i') AS lastMvtDate")
            ->addSelect("last_tracking_location.label AS lastLocation")
            ->addSelect("last_tracking_user.username AS operator")
            ->andWhere("pack.code LIKE :term")
            ->leftJoin("pack.nature", "nature")
            ->leftJoin("pack.lastTracking", "last_tracking")
            ->leftJoin("last_tracking.emplacement", "last_tracking_location")
            ->leftJoin("last_tracking.operateur", "last_tracking_user")
            ->setParameter("term", "%$term%");

        if($exclude) {
            $qb->andWhere("pack.code NOT IN (:exclude)")
                ->setParameter("exclude", $exclude);
        }

        if($withoutArticle) {
            $qb->leftJoin("pack.article", "article")
                ->andWhere("article.id IS NULL");
        }

        return $qb->getQuery()->getResult();
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
            ->addSelect('DATEDIFF(NOW(), IF(dropGroupLocation.id IS NULL, lastDrop.datetime, MIN(movement.datetime))) AS packWaitingDays')

            ->join('pack.lastDrop', 'lastDrop')
            ->join('pack.arrivage', 'arrival')
            ->join('lastDrop.emplacement', 'dropLocation')
            ->leftJoin('dropLocation.locationGroup', 'dropGroupLocation')

            ->join('pack.trackingMovements', 'movement')
            ->join('movement.emplacement', 'movementLocation')
            ->join('movement.type', 'movementType')
            ->leftJoin('movementLocation.locationGroup', 'locationGroup')

            ->andWhere('arrival IS NOT NULL')
            ->andWhere('arrival.destinataire IS NOT NULL')
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
        return intval($this->createQueryBuilder("pack")
            ->select("COUNT(reception)")
            ->join(ReceptionLine::class, "reception_line", Join::WITH, "reception_line.pack = pack")
            ->join("reception_line.reception", "reception")
            ->join("reception.statut", "status")
            ->andWhere("status.code = :ongoing")
            ->setParameter("ongoing", Reception::STATUT_EN_ATTENTE)
            ->getQuery()
            ->getSingleScalarResult()) > 0;
    }

    public function getForSelectFromReception(?string $term, ?int $reception): array {
        return $this->createQueryBuilder("pack")
            ->select("pack.id AS id, pack.code AS text")
            ->join(ReceptionLine::class, "reception_line", Join::WITH, "reception_line.pack = pack")
            ->join("reception_line.reception",  "reception")
            ->andWhere("pack.code LIKE :term")
            ->andWhere("reception.id = :reception")
            ->setParameters([
                "term" => "%$term%",
                "reception" => $reception
            ])
            ->setMaxResults(100)
            ->getQuery()
            ->getArrayResult();
    }

}
