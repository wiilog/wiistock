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

    /**
     * @param array $locations
     * @param array $naturesFilter
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
    public function getPackIntelOnLocations(array $locations, array $naturesFilter = []): array {
        $queryBuilder = $this->createPacksOnLocationsQueryBuilder($locations, $naturesFilter)
            ->select('nature.id as natureId')
            ->addSelect('nature.label as natureLabel')
            ->addSelect('firstTracking.datetime AS firstTrackingDateTime')
            ->addSelect('lastTracking.datetime AS lastTrackingDateTime')
            ->addSelect('currentLocation.id AS currentLocationId')
            ->addSelect('currentLocation.label AS currentLocationLabel')
            ->addSelect('colis.code AS code');

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
    public function countPacksOnLocations(array $locations, array $onDateBracket = []): int {
        return $this
            ->createPacksOnLocationsQueryBuilder($locations, [], $onDateBracket)
            ->select('COUNT(colis.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param array $locations
     * @param array $naturesFilter
     * @param array $onDateBracket ['minDate' => DateTime, 'maxDate' => DateTime]|[]
     * @return mixed
     * @throws DBALException
     */
    private function createPacksOnLocationsQueryBuilder(array $locations, array $naturesFilter = [], array $onDateBracket = []): QueryBuilder {
        $entityManager = $this->getEntityManager();
        $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);
        $firstTrackingForColis = $mouvementTracaRepository->getFirstIdForPacksOnLocations($locations, $onDateBracket);
        $lastTrackingForColis = $mouvementTracaRepository->getIdForPacksOnLocations($locations, $onDateBracket);

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
