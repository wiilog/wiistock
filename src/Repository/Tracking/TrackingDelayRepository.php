<?php

namespace App\Repository\Tracking;

use App\Entity\Emplacement;
use App\Entity\Nature;
use App\Entity\Tracking\TrackingDelay;
use App\Entity\Tracking\TrackingEvent;
use Doctrine\ORM\EntityRepository;

class TrackingDelayRepository extends EntityRepository {
    /**
     * @param Nature[] $natures
     * @param Emplacement[] $locations
     * @param TrackingEvent[] $events
     * @param int $limit request limit to display value when the limit is reached
     * @return iterable<TrackingDelay>
     */
    public function iterateTrackingDelayByFilters(array $natures,
                                                  array $locations,
                                                  array $events,
                                                  int   $limit): iterable
    {
        // TODO WIIS-11930 attention si plusieurs tracking delay par pack
        $queryBuilder = $this->createQueryBuilder('tracking_delay')
            ->leftJoin('tracking_delay.pack', 'join_pack')
            ->leftJoin('join_pack.nature', 'join_nature')
            ->leftJoin('join_pack.lastOngoingDrop', 'join_last_ongoing_drop')
            ->leftJoin('join_last_ongoing_drop.emplacement', 'join_location');

        if (!empty($natures)) {
            $queryBuilder->andWhere('join_nature.id IN (:natures)')
                ->setParameter('natures', $natures);
        }

        if (!empty($locations)) {
            $queryBuilder->andWhere('join_location.id IN (:locations)')
                ->setParameter('locations', $locations);
        }

        if (empty($events)) {
            return $queryBuilder->getQuery()->toIterable();
        }

        if (in_array(null, $events, true)) {
            $filteredEvents = array_filter($events, fn($event) => $event !== null);

            if (empty($filteredEvents)) {
                $queryBuilder->andWhere('tracking_delay.lastTrackingEvent IS NULL');
            } else {
                $queryBuilder->andWhere(
                    '(tracking_delay.lastTrackingEvent IN (:events) OR tracking_delay.lastTrackingEvent IS NULL)'
                )->setParameter('events', $filteredEvents);
            }
        } else {
            $queryBuilder->andWhere('tracking_delay.lastTrackingEvent IN (:events)')
                ->setParameter('events', $events);
        }

        if ($limit) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder
            ->getQuery()
            ->toIterable();
    }
}
