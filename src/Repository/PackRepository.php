<?php

namespace App\Repository;

use App\Entity\Arrivage;
use App\Entity\Pack;
use App\Helper\FormatHelper;
use App\Helper\Stream;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Exception;

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

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @param bool $groupByNature
     * @param array $arrivalStatusesFilter
     * @param array $arrivalTypesFilter
     * @return int|array
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
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
        $iterator =  $this->createQueryBuilder('pack')
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
            ->iterate(null, Query::HYDRATE_ARRAY);

        foreach($iterator as $item) {
            // $item [index => article array]
            yield array_pop($item);
        }
    }

    public function getGroupsByDates(DateTime $dateMin, DateTime $dateMax) {
        $iterator =  $this->createQueryBuilder("parent")
            ->distinct()
            ->select("parent.code AS code")
            ->addSelect("join_nature.label AS nature")
            ->addSelect("COUNT(children.id) AS packs")
            ->addSelect("parent.weight AS weight")
            ->addSelect("parent.volume AS volume")
            ->addSelect("movement.datetime AS lastMvtDate")
            ->addSelect("movement AS fromTo")
            ->addSelect("emplacement.label AS location")
            ->leftJoin("parent.lastTracking", "movement")
            ->leftJoin("movement.emplacement","emplacement")
            ->leftJoin("parent.nature","join_nature")
            ->leftJoin("parent.children","child")
            ->where("movement.datetime BETWEEN :dateMin AND :dateMax")
            ->groupBy("parent")
            ->setParameter("dateMin", $dateMin)
            ->setParameter("dateMax", $dateMax)
            ->getQuery()
            ->iterate(null, Query::HYDRATE_ARRAY);

        foreach($iterator as $item) {
            // $item [index => article array]
            yield array_pop($item);
        }
    }

    /**
     * @return int|mixed|string
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
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

    /**
     * @param array|null $params
     * @param array|null $filters
     * @return array
     * @throws Exception
     */
    public function findByParamsAndFilters($params, $filters, string $mode)
    {
        $queryBuilder = $this->createQueryBuilder('pack');

        if ($mode === self::PACKS_MODE) {
            $queryBuilder->where('pack.groupIteration IS NULL');
            $countTotal = $this->countAllPacks();
        }
        else if ($mode === self::GROUPS_MODE) {
            $queryBuilder->where('pack.groupIteration IS NOT NULL');
            $countTotal = $this->countAllGroups();
        }

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'emplacement':
                    $emplacementValue = explode(':', $filter['value']);
                    $queryBuilder
                        ->join('pack.lastTracking', 'mFilter0')
                        ->join('mFilter0.emplacement', 'e')
                        ->andWhere('e.label = :location')
                        ->setParameter('location', $emplacementValue[1] ?? $filter['value']);
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
            }
        }

        //Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $queryBuilder
                        ->leftJoin('pack.lastTracking', 'm2')
                        ->leftJoin('m2.emplacement', 'e2')
                        ->leftJoin('pack.nature', 'n2')
                        ->leftJoin('pack.arrivage', 'arrivage')
                        ->leftJoin('arrivage.type','arrival_type')
                        ->andWhere("(
                            pack.code LIKE :value OR
                            e2.label LIKE :value OR
                            n2.label LIKE :value OR
                            arrivage.numeroArrivage LIKE :value OR
                            arrival_type.label LIKE :value
						)")
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']] ?? 'id';
                    if ($column === 'packLocation') {
                        $queryBuilder
                            ->leftJoin('pack.lastTracking', 'm3')
                            ->leftJoin('m3.emplacement', 'e3')
                            ->orderBy('e3.label', $order);
                    } else if ($column === 'packNature') {
                        $queryBuilder
                            ->leftJoin('pack.nature', 'n3')
                            ->orderBy('n3.label', $order);
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
        $queryBuilder
            ->select('count(pack)');
        // compte éléments filtrés
        $countFiltered = $queryBuilder->getQuery()->getSingleScalarResult();

        $queryBuilder
            ->select('pack');

        if ($params) {
            if (!empty($params->get('start'))) $queryBuilder->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $queryBuilder->setMaxResults($params->get('length'));
        }

        $query = $queryBuilder->getQuery();
        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    /**
     * @param array $locations
     * @param array $options ['natures' => array, 'isCount' => bool, 'field' => string, 'limit' => int, 'start' => int, 'order' => string, 'onlyLate' => bool]
     * @return int|mixed|string
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
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
            $queryBuilder->setFirstResult($start);
        }

        if ($limit) {
            $queryBuilder->setMaxResults($limit);
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

    /**
     * @param array $onDateBracket
     * @return int|mixed|string
     */
    public function countColisByArrivageAndNature($from, $to) {
        $queryBuilder = $this->createQueryBuilder('colis');
        $queryBuilderExpr = $queryBuilder->expr();
        $queryBuilder
            ->select('count(colis.id) as nbColis')
            ->addSelect('nature.label AS natureLabel')
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
                $natureLabel = $counter['natureLabel'];
                $nbColis = $counter['nbColis'];
                if (!isset($carry[$arrivageId])) {
                    $carry[$arrivageId] = [];
                }
                $carry[$arrivageId][$natureLabel] = intval($nbColis);
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
                $pack['date'] = isset($pack['date']) ? FormatHelper::datetime($pack['date']) : null;
                return $pack;
            })
            ->toArray();
    }
}
