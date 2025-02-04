<?php

namespace App\Repository\Tracking;

use Doctrine\ORM\EntityRepository;

class TrackingDelayRepository extends EntityRepository {
    public function findByFilters(array $natures,
                                  array $locations,
                                  array $events,
                                  int   $limit) {
        $queryBuilder = $this->createQueryBuilder('tracking_delay')
            ->leftJoin('tracking_delay.pack', 'join_pack')
            ->leftJoin('join_pack.nature', 'join_nature')
            ->leftJoin('join_pack.lastOngoingDrop', 'join_last_ongoing_drop')
            ->leftJoin('join_last_ongoing_drop.emplacement', 'join_location')
//            ->andWhere('tracking_delay.lastTrackingEvent IN (:events)')
            ->andWhere('join_nature.id IN (:natures)')
            ->andWhere('join_location.id IN (:locations)')
            ->setParameters([
//                'events' => $events,
                'natures' => $natures,
                'locations' => $locations,
            ]);

        if ($limit) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
