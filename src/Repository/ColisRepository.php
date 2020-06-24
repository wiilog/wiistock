<?php

namespace App\Repository;

use App\Entity\Arrivage;
use App\Entity\Colis;
use App\Entity\MouvementTraca;
use App\Entity\Nature;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;

/**
 * @method Colis|null find($id, $lockMode = null, $lockVersion = null)
 * @method Colis|null findOneBy(array $criteria, array $orderBy = null)
 * @method Colis[]    findAll()
 * @method Colis[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ColisRepository extends EntityRepository
{

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
            INSERT INTO colis (code, last_drop_id) VALUES ('${code}', '${id}')
        ";
        $connection = $this->getEntityManager()->getConnection();
        $connection->executeQuery($sqlQuery, []);
    }

    public function updateByIds(array $ids, int $mvtId)
    {
        $queryBuilder = $this->createQueryBuilder('colis');
        $queryBuilderExpr = $queryBuilder->expr();
        $queryBuilder
            ->update(Colis::class, 'colis')
            ->set('colis.lastDrop', $mvtId)
            ->where(
                $queryBuilderExpr->in('colis.id', ':ids')
            )
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }

    /**
     * @param array $locations
     * @param array $naturesFilter
     * @param int|null $limit
     * @return array Array of [
     *      'natureId' => int,
     *      'natureLabel' => string,
     *      'firstTrackingDateTime' => DateTime,
     *      'lastTrackingDateTime' => DateTime,
     *      'currentLocationId' => int,
     *      'currentLocationLabel' => string
     *      'code' => string
     * ]
     * @throws DBALException
     */
    public function getPackIntelOnLocations(array $locations, array $naturesFilter = [], ?int $limit = null): array
    {
        $queryBuilder = $this->createPacksOnLocationsQueryBuilder($locations, $naturesFilter)
            ->select('nature.id as natureId')
            ->addSelect('nature.label as natureLabel')
            ->addSelect('firstTracking.datetime AS firstTrackingDateTime')
            ->addSelect('lastTracking.datetime AS lastTrackingDateTime')
            ->addSelect('currentLocation.id AS currentLocationId')
            ->addSelect('currentLocation.label AS currentLocationLabel')
            ->addSelect('colis.code AS code');
        if (isset($limit)) {
            $queryBuilder
                ->orderBy('firstTracking.datetime', 'ASC')
                ->setMaxResults($limit);
        }
        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array $locations
     * @param array $onDateBracket
     * @return int
     * @throws DBALException
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countPacksOnLocations(array $locations, array $onDateBracket = []): int
    {
        return $this
            ->createPacksOnLocationsQueryBuilder($locations, [], $onDateBracket)
            ->select('COUNT(colis.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }


    public function getDropsOnLocationsForDateBracket(array $locations,
                                                      array $natures,
                                                      array $dateBracket,
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
     * @throws DBALException
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countColisByArrivageAndNature(array $onDateBracket = [])
    {
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
            ->setParameter('dateFrom', $onDateBracket[0])
            ->setParameter('dateTo', $onDateBracket[1]);

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


    /**
     * @param array $locations
     * @param array $naturesFilter
     * @param array $onDateBracket ['minDate' => DateTime, 'maxDate' => DateTime]|[]
     * @return mixed
     * @throws DBALException
     */
    private function createPacksOnLocationsQueryBuilder(array $locations, array $naturesFilter = [], array $onDateBracket = []): QueryBuilder
    {
        $entityManager = $this->getEntityManager();
        $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);
        $firstTrackingForColis = $mouvementTracaRepository->getFirstIdForPacksOnLocations($locations, $onDateBracket);
        $lastTrackingForColis = $mouvementTracaRepository->getForPacksOnLocations($locations, $onDateBracket);

        $queryBuilder = $this
            ->createQueryBuilder('colis')
            ->join('colis.nature', 'nature')
            ->join(MouvementTraca::class, 'firstTracking', 'WITH', 'firstTracking.id IN (:firstTrackingIds) AND firstTracking.colis = colis.code')
            ->join(MouvementTraca::class, 'lastTracking', 'WITH', 'lastTracking.id IN (:lastTrackingIds) AND lastTracking.colis = colis.code')
            ->join('lastTracking.emplacement', 'currentLocation')
            ->setParameter('firstTrackingIds', $firstTrackingForColis, Connection::PARAM_STR_ARRAY)
            ->setParameter('lastTrackingIds', $lastTrackingForColis, Connection::PARAM_STR_ARRAY);

        if (!empty($naturesFilter)) {
            $queryBuilder
                ->andWhere('nature.id IN (:naturesFilter)')
                ->setParameter(
                    'naturesFilter',
                    array_map(function ($nature) {
                        return ($nature instanceof Nature)
                            ? $nature->getId()
                            : $nature;
                    }, $naturesFilter),
                    Connection::PARAM_STR_ARRAY
                );
        }

        return $queryBuilder;
    }
}
