<?php


namespace App\EventListener;


use App\Entity\LocationCluster;
use App\Entity\LocationClusterMeter;
use App\Entity\LocationClusterRecord;
use App\Entity\MouvementTraca;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

class TrackingMovementListener
{

    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
    }

    /**
     * @param MouvementTraca $movementToDelete
     * @throws Exception
     */
    public function preRemove(MouvementTraca $movementToDelete) {
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

        $firstDropsRecordIds = $movementToDelete->getFirstDropsRecords()
            ->map(function (LocationClusterRecord $record) {
                return $record->getId();
            })
            ->toArray();
        $this->treatFirstDropRecord($movementToDelete);
        $this->treatLastTrackingRecord($firstDropsRecordIds, $movementToDelete);

        $this->treatLocationClusterMeter($movementToDelete);
    }

    private function treatFirstDropRecord(MouvementTraca $movementToDelete): void {
        $pack = $movementToDelete->getPack();
        $firstDropRecords = $movementToDelete->getFirstDropsRecords();
        /** @var LocationClusterRecord $firstDropRecords */
        foreach ($firstDropRecords as $firstDropRecord) {
            // record inactive OR firstDrop == lastTracking (one movement on cluster)
            if (!$firstDropRecord->isActive()
                || $movementToDelete === $firstDropRecord->getLastTracking()) {
                $firstDropRecord->setFirstDrop(null);
                $this->entityManager->remove($firstDropRecord);
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
                    $this->entityManager->remove($firstDropRecord);
                }
            }
        }
    }
    private function treatLastTrackingRecord(array $recordIdsToIgnore,
                                             MouvementTraca $movementToDelete): void {
        $pack = $movementToDelete->getPack();
        $lastTrackingRecords = $movementToDelete->getLastTrackingRecords();
        /** @var LocationClusterRecord $record */
        foreach ($lastTrackingRecords as $record) {
            if ($record->getId()
                && !in_array($record->getId(), $recordIdsToIgnore)) {
                if (!$record->isActive()) {
                    $record->setLastTracking(null);
                    $this->entityManager->remove($record);
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
                        $this->entityManager->remove($record);
                    }
                }
            }
        }
    }

    private function treatLocationClusterMeter(MouvementTraca $trackingMovement): void {
        $location = $trackingMovement->getEmplacement();
        if ($trackingMovement->isDrop()
            && $location) {
            /** @var LocationCluster $clusters */
            foreach ($location->getClusters() as $clusters) {
                /** @var LocationClusterMeter $meter */
                foreach ($clusters->getMetersInto() as $meter) {
                    $meter->decreaseDropCounter();
                }
            }
        }
    }
}
