<?php


namespace App\EventListener;


use App\Entity\LocationCluster;
use App\Entity\LocationClusterMeter;
use App\Entity\LocationClusterRecord;
use App\Entity\MouvementTraca;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;


class TrackingMovementListener
{
    /**
     * @param MouvementTraca $movementToDelete
     * @param LifecycleEventArgs $lifecycleEventArgs
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function preRemove(MouvementTraca $movementToDelete,
                              LifecycleEventArgs $lifecycleEventArgs) {
        $entityManager = $lifecycleEventArgs->getEntityManager();

        $firstDropsRecordIds = $movementToDelete->getFirstDropsRecords()
            ->map(function (LocationClusterRecord $record) {
                return $record->getId();
            })
            ->toArray();

        $this->treatPackLinking($movementToDelete, $entityManager);
        $this->treatFirstDropRecordLinking($movementToDelete, $entityManager);
        $this->treatLastTrackingRecordLinking($firstDropsRecordIds, $movementToDelete, $entityManager);
        $this->treatLocationClusterMeterLinking($movementToDelete, $entityManager);
    }

    /**
     * @param EntityManager $entityManager
     * @param MouvementTraca $movementToDelete
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function treatPackLinking(MouvementTraca $movementToDelete,
                                     EntityManager $entityManager): void {

        $pack = $movementToDelete->getPack();

        $trackingMovements = $pack->getTrackingMovements();

        $newLastTracking = null;
        $newLastDrop = null;
        $lastDropToUpdate = (
            !$pack->getLastDrop()
            || $pack->getLastDrop()->getId() === $movementToDelete->getId()
        );

        $lastTrackingToUpdate = (
            $pack->getLastTracking()
            && $pack->getLastTracking()->getId() === $movementToDelete->getId()
        );
        foreach ($trackingMovements as $savedTrackingMovement) {
            if (($movementToDelete !== $savedTrackingMovement)
                && (!isset($newLastTracking))) {
                $newLastTracking = $savedTrackingMovement;
            }

            if (isset($newLastTracking)) {
                break;
            }
        }

        if ($lastDropToUpdate
            && isset($newLastTracking)
            && $newLastTracking->isDrop()) {
            $newLastDrop = $newLastTracking;
        }

        if ($lastDropToUpdate) {
            $pack->setLastDrop($newLastDrop);
        }

        if ($lastTrackingToUpdate) {
            $pack->setLastTracking($newLastTracking);
        }

        $entityManager->flush($pack);
    }

    /**
     * @param EntityManager $entityManager
     * @param MouvementTraca $movementToDelete
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function treatFirstDropRecordLinking(MouvementTraca $movementToDelete,
                                                 EntityManager $entityManager): void {
        $pack = $movementToDelete->getPack();
        $firstDropRecords = $movementToDelete->getFirstDropsRecords();
        /** @var LocationClusterRecord $firstDropRecords */
        foreach ($firstDropRecords as $firstDropRecord) {
            // record inactive OR firstDrop == lastTracking (one movement on cluster)
            if (!$firstDropRecord->isActive()
                || $movementToDelete === $firstDropRecord->getLastTracking()) {
                $firstDropRecord->setFirstDrop(null);
                $entityManager->remove($firstDropRecord);
            }
            else {
                $replacedTracking = null;
                // get next drop on cluster
                foreach ($pack->getTrackingMovements() as $packTrackingMovement) {
                    if ($packTrackingMovement === $movementToDelete) {
                        break;
                    }
                    else if ($packTrackingMovement->isDrop()) {
                        $replacedTracking = $packTrackingMovement;
                    }
                }
                if (isset($replacedTracking)){
                    $firstDropRecord->setFirstDrop($replacedTracking);
                }
                else {
                    $firstDropRecord->setFirstDrop(null);
                    $entityManager->remove($firstDropRecord);
                }
            }
        }

        $entityManager->flush($firstDropRecords->toArray());
    }

    /**
     * @param EntityManager $entityManager
     * @param array $recordIdsToIgnore
     * @param MouvementTraca $movementToDelete
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function treatLastTrackingRecordLinking(array $recordIdsToIgnore,
                                                    MouvementTraca $movementToDelete,
                                                    EntityManager $entityManager): void {
        $pack = $movementToDelete->getPack();
        $lastTrackingRecords = $movementToDelete->getLastTrackingRecords();
        /** @var LocationClusterRecord $record */
        foreach ($lastTrackingRecords as $record) {
            if ($record->getId()
                && !in_array($record->getId(), $recordIdsToIgnore)) {
                if (!$record->isActive()) {
                    $record->setLastTracking(null);
                    $entityManager->remove($record);
                }
                else {
                    $replacedTracking = null;
                    // get last taking
                    foreach ($pack->getTrackingMovements('ASC') as $packTrackingMovement) {
                        if ($packTrackingMovement === $movementToDelete) {
                            break;
                        }
                        $replacedTracking = $packTrackingMovement;
                    }
                    if (isset($replacedTracking)){
                        $record->setLastTracking($replacedTracking);
                        if ($replacedTracking->isTaking()) {
                            $record->setActive(false);
                        }
                    }
                    else {
                        $record->setLastTracking(null);
                        $entityManager->remove($record);
                    }
                }
            }
        }

        $entityManager->flush($lastTrackingRecords->toArray());
    }

    /**
     * @param EntityManager $entityManager
     * @param MouvementTraca $trackingMovement
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function treatLocationClusterMeterLinking(MouvementTraca $trackingMovement,
                                                      EntityManager $entityManager): void {
        $location = $trackingMovement->getEmplacement();
        if ($trackingMovement->isDrop()
            && $location) {
            /** @var LocationCluster $clusters */
            foreach ($location->getClusters() as $clusters) {
                $meters = $clusters->getMetersInto();
                /** @var LocationClusterMeter $meter */
                foreach ($meters as $meter) {
                    $meter->decreaseDropCounter();
                }

                $entityManager->flush($meters->toArray());
            }
        }
    }
}
