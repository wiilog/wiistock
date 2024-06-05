<?php

namespace App\Repository;

use App\Entity\Language;
use App\Entity\LocationCluster;
use App\Entity\Nature;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use WiiCommon\Helper\Stream;

class LocationClusterRepository extends EntityRepository {
    /**
     * @param Nature[] $naturesFilter
     */
    public function getPacksOnCluster(LocationCluster $locationCluster, array $naturesFilter, Language $defaultLanguage): array
    {
        return $this->getClusterQueryBuilder($locationCluster, $naturesFilter, $defaultLanguage)
            ->select('join_nature.id as natureId')
            ->addSelect("COALESCE(join_translation_nature.translation, join_translation_default_nature.translation, join_nature.label) AS natureLabel")
            ->addSelect('join_firstDrop.datetime AS firstTrackingDateTime')
            ->addSelect('join_lastTracking.datetime AS lastTrackingDateTime')
            ->addSelect('join_currentLocation.id AS currentLocationId')
            ->addSelect('join_currentLocation.label AS currentLocationLabel')
            ->addSelect('join_logisticUnit.code AS code')
            ->addSelect('join_logisticUnit.id AS packId')
            ->addSelect('join_logisticUnit.truckArrivalDelay AS truckArrivalDelay')
            ->getQuery()
            ->getResult();
    }

    public function countPacksOnCluster(LocationCluster $locationCluster, array $naturesFilter, Language $defaultLanguage): int
    {
        return $this->getClusterQueryBuilder($locationCluster, $naturesFilter, $defaultLanguage)
            ->select('COUNT(DISTINCT join_logisticUnit.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getClusterQueryBuilder(LocationCluster $locationCluster, array $naturesFilter, Language $defaultLanguage):  QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('cluster')
            ->innerJoin('cluster.locationClusterRecords', 'record')
            ->innerJoin('record.pack', 'join_logisticUnit')
            ->innerJoin('record.firstDrop', 'join_firstDrop')
            ->innerJoin('record.lastTracking', 'join_lastTracking')
            ->innerJoin('join_lastTracking.emplacement', 'join_currentLocation')
            ->andWhere('record.active = true')
            ->andWhere('cluster = :locationCluster')
            ->setParameter('locationCluster', $locationCluster);

        $queryBuilder = QueryBuilderHelper::joinTranslations($queryBuilder, $defaultLanguage, $defaultLanguage, ['nature'], ["alias" => "join_logisticUnit"]);

        if (!empty($naturesFilter)) {
            $queryBuilder
                ->andWhere('join_nature.id IN (:naturesFilter)')
                ->setParameter("naturesFilter", Stream::from($naturesFilter)->map(static fn(Nature $nature) => $nature->getId())->toArray());
        }
        return $queryBuilder;
    }
}
