<?php

namespace App\Repository;

use App\Entity\Nature;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;

/**
 * Class LocationClusterRepository
 * @package App\Repository
 */
class LocationClusterRepository extends EntityRepository {
    public function getPacksOnCluster(string $code, array $naturesFilter): array {
        $queryBuilder = $this->createQueryBuilder('cluster')
            ->select('nature.id as natureId')
            ->addSelect('nature.label as natureLabel')
            ->addSelect('firstDrop.datetime AS firstTrackingDateTime')
            ->addSelect('lastTracking.datetime AS lastTrackingDateTime')
            ->addSelect('currentLocation.id AS currentLocationId')
            ->addSelect('currentLocation.label AS currentLocationLabel')
            ->addSelect('pack.code AS code')

            ->join('cluster.locationClusterRecords', 'record')
            ->join('record.pack', 'pack')
            ->join('record.firstDrop', 'firstDrop')
            ->join('record.lastTracking', 'lastTracking')
            ->join('lastTracking.emplacement', 'currentLocation')
            ->leftJoin('pack.nature', 'nature')
            ->where('record.active = true')
            ->andWhere('cluster.code = :code')
            ->setParameter('code', $code);

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

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
