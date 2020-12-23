<?php

namespace App\Repository;

use App\Entity\Arrivage;
use App\Entity\Pack;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;

/**
 * @method Pack|null find($id, $lockMode = null, $lockVersion = null)
 * @method Pack|null findOneBy(array $criteria, array $orderBy = null)
 * @method Pack[]    findAll()
 * @method Pack[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PackRepository extends EntityRepository
{

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
     * @return Arrivage[]|null
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countByDates(DateTime $dateMin, DateTime $dateMax)
    {
        return $this->createQueryBuilder('colis')
            ->join('colis.arrivage', 'arrivage')
            ->where('arrivage.date BETWEEN :dateMin AND :dateMax')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ])
            ->select('COUNT(arrivage)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return Arrivage[]|null
     */
    public function getByDates(DateTime $dateMin, DateTime $dateMax)
    {
        return $this->createQueryBuilder('pack')
            ->select('pack')
            ->leftJoin('pack.lastTracking', 'm')
            ->where(
                'm.datetime BETWEEN :dateMin AND :dateMax'
            )
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ])
            ->getQuery()
            ->getResult();
    }

    /**
     * @return int|mixed|string
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countAll()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(p)
            FROM App\Entity\Pack p"
        );
        return $query->getSingleScalarResult();
    }

    /**
     * @param array|null $params
     * @param array|null $filters
     * @return array
     * @throws Exception
     */
    public function findByParamsAndFilters($params, $filters)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->from('App\Entity\Pack', 'pack');
        $countTotal = $this->countAll();
        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'emplacement':
                    $emplacementValue = explode(':', $filter['value']);
                    $qb
                        ->join('pack.lastTracking', 'mFilter0')
                        ->join('mFilter0.emplacement', 'e')
                        ->andWhere('e.label = :location')
                        ->setParameter('location', $emplacementValue[1] ?? $filter['value']);
                    break;
                case 'dateMin':
                    $qb
                        ->join('pack.lastTracking', 'mFilter1')
                        ->andWhere('mFilter1.datetime >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb
                        ->join('pack.lastTracking', 'mFilter2')
                        ->andWhere('mFilter2.datetime <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
                case 'colis':
                    $qb
                        ->andWhere('pack.code LIKE :colis')
                        ->setParameter('colis', '%' . $filter['value'] . '%');
                    break;
                case 'numArrivage':
                    $qb
                        ->join('pack.arrivage', 'a')
                        ->andWhere('a.numeroArrivage LIKE :arrivalNumber')
                        ->setParameter('arrivalNumber', '%' . $filter['value'] . '%');
                    break;
                case 'type':
                    $qb
                        ->join('pack.arrivage', 'a_type')
                        ->join('a_type.type','type')
                        ->andWhere('type.label LIKE :types')
                        ->setParameter('types', '%' . $filter['value'] . '%');
                    break;
                case 'natures':
                    $natures = explode(',', $filter['value']);
                    $qb
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
                    $qb
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
                    $column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];
                    if ($column === 'packLocation') {
                        $qb
                            ->leftJoin('pack.lastTracking', 'm3')
                            ->leftJoin('m3.emplacement', 'e3')
                            ->orderBy('e3.label', $order);
                    } else if ($column === 'packNature') {
                        $qb
                            ->leftJoin('pack.nature', 'n3')
                            ->orderBy('n3.label', $order);
                    } else if ($column === 'packLastDate') {
                        $qb
                            ->leftJoin('pack.lastTracking', 'm3')
                            ->orderBy('m3.datetime', $order);
                    } else if ($column === 'packOrigin') {
                        $qb
                            ->leftJoin('pack.arrivage', 'arrivage3')
                            ->orderBy('arrivage3.numeroArrivage', $order);
                    } else if ($column === 'arrivageType') {
                        $qb
                            ->leftJoin('pack.arrivage', 'arrivage3')
                            ->orderBy('arrivage3.type', $order);
                    } else {
                        $qb
                            ->orderBy('pack.' . $column, $order);
                    }
                    $orderId = ($column === 'datetime')
                        ? $order
                        : 'DESC';
                    $qb->addOrderBy('pack.id', $orderId);
                }
            }
        }
        $qb
            ->select('count(pack)');
        // compte éléments filtrés
        $countFiltered = $qb->getQuery()->getSingleScalarResult();

        $qb
            ->select('pack');

        if ($params) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

        $query = $qb->getQuery();
        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    public function getIdsByCode(string $code)
    {
        $queryBuilder = $this->createQueryBuilder('colis');
        $queryBuilderExpr = $queryBuilder->expr();
        return $queryBuilder
            ->select('colis.id')
            ->where(
                $queryBuilderExpr->like('colis.code', "'" . $code . "'")
            )
            ->getQuery()
            ->execute();
    }

    /**
     * @param array $mvt
     * @throws DBALException
     */
    public function createFromMvt(array $mvt)
    {
        $code = $mvt['colis'];
        $id = $mvt['id'];
        $sqlQuery = "
            INSERT INTO pack (code, last_drop_id) VALUES ('${code}', '${id}')
        ";
        $connection = $this->getEntityManager()->getConnection();
        $connection->executeQuery($sqlQuery, []);
    }

    public function updateByIds(array $ids, int $mvtId)
    {
        $arrayColisId = implode(',', array_map(function(array $idsSub) {
            return $idsSub['id'];
        }, $ids));
        $sqlQuery = "
            UPDATE pack SET last_drop_id = ${mvtId} WHERE id IN (${arrayColisId})
        ";
        $connection = $this->getEntityManager()->getConnection();
        $connection->executeQuery($sqlQuery, []);
    }

    /**
     * @param array $locations
     * @param array $natures
     * @param bool $isCount
     * @param string $field
     * @param int|null $limit
     * @param int|null $start
     * @param string $order
     * @param bool $onlyLate
     * @return int|mixed|string
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getCurrentPackOnLocations(array $locations,
                                              array $natures,
                                              bool $isCount = true,
                                              string $field = 'colis.id',
                                              ?int $limit = null,
                                              ?int $start = null,
                                              string $order = 'desc',
                                              bool $onlyLate = false)
    {
        $queryBuilder = $this->createQueryBuilder('colis');
        $queryBuilderExpr = $queryBuilder->expr();
        $queryBuilder
            ->select($isCount ? ($queryBuilderExpr->count($field)) : $field)
            ->leftJoin('colis.nature', 'nature')
            ->join('colis.lastDrop', 'lastDrop')
            ->join('lastDrop.emplacement', 'emplacement');

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
        $queryBuilder
            ->orderBy('lastDrop.datetime', $order);
        if ($onlyLate) {
            $queryBuilder
                ->andWhere(
                    $queryBuilderExpr->isNotNull('emplacement.dateMaxTime')
                );
        }
        if ($start) {
            $queryBuilder
                ->setFirstResult($start);
        }
        if ($limit) {
            $queryBuilder
                ->setMaxResults($limit);
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
            ->groupBy('nature.id')
            ->addGroupBy('arrivage.id')
            ->where(
                $queryBuilderExpr->between('arrivage.date', ':dateFrom', ':dateTo')
            )
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
}
