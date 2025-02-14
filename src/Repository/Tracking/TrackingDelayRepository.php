<?php

namespace App\Repository\Tracking;

use App\Entity\Emplacement;
use App\Entity\Nature;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingDelay;
use App\Entity\Tracking\TrackingEvent;
use Doctrine\ORM\EntityRepository;
use WiiCommon\Helper\Stream;

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
        $queryBuilder = $this->getEntityManager()
            ->createQueryBuilder()
            ->from(Pack::class, 'pack')
            ->select('tracking_delay')
            ->join('pack.currentTrackingDelay', 'tracking_delay')
            ->leftJoin('pack.nature', 'join_nature')
            ->leftJoin('pack.lastOngoingDrop', 'join_last_ongoing_drop')
            ->leftJoin('join_last_ongoing_drop.emplacement', 'join_location')
            ->setMaxResults($limit);

        if (!empty($natures)) {
            $queryBuilder
                ->andWhere('join_nature.id IN (:natures)')
                ->setParameter('natures', $natures);
        }

        if (!empty($locations)) {
            $queryBuilder
                ->andWhere('join_location.id IN (:locations)')
                ->setParameter('locations', $locations);
        }

        if (!empty($events)) {
            $expr = $queryBuilder->expr();
            $orX = $expr->orX();

            if (in_array(null, $events, true)) {
                $orX->add('tracking_delay.lastTrackingEvent IS NULL');
            }

            $filteredEvents = Stream::from($events)
                ->filter(static fn($event) => $event !== null)
                ->toArray();
            if (!empty($filteredEvents)) {
                $orX->add('tracking_delay.lastTrackingEvent IN (:events)');
                $queryBuilder->setParameter('events', $filteredEvents);
            }

            $queryBuilder->andWhere($orX);
        }

        return $queryBuilder
            ->getQuery()
            ->toIterable();
    }
}
