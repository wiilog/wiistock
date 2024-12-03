<?php

namespace App\Repository;

use App\Entity\LocationCluster;
use App\Entity\LocationClusterRecord;
use App\Entity\Tracking\Pack;
use Doctrine\ORM\EntityRepository;

/**
 * Class LocationClusterRecordRepository
 * @package App\Repository
 */
class LocationClusterRecordRepository extends EntityRepository {

    public function findOneByPackAndCluster(LocationCluster $cluster, Pack $pack): ?LocationClusterRecord {
        return $this->createQueryBuilder('record')
            ->andWhere('record.pack = :pack')
            ->andWhere('record.locationCluster = :cluster')
            ->setMaxResults(1)
            ->setParameters([
                'pack' => $pack,
                'cluster' => $cluster,
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }

}
