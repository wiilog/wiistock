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
     * @param MouvementTraca $trackingMovement
     * @throws Exception
     */
    public function preRemove(MouvementTraca $trackingMovement) {
        $pack = $trackingMovement->getPack();

        $trackingMovements = $pack->getTrackingMovements();

        $newLastTracking = null;
        $newLastDrop = null;
        foreach ($trackingMovements as $savedTrackingMovement) {
            if ($trackingMovement !== $savedTrackingMovement) {
                if (!isset($newLastTracking)) {
                    $newLastTracking = $trackingMovement;
                }

                if ($trackingMovement->isDrop()) {
                    $newLastDrop = $trackingMovement;
                }
            }

            if (isset($newLastTracking) && isset($newLastDrop)) {
                break;
            }
        }

        $linkedLastDrop = $trackingMovement->getLinkedPackLastDrop();
        $linkedLastTracking = $trackingMovement->getLinkedPackLastTracking();

        if ($linkedLastDrop) {
            $linkedLastDrop->setLastDrop($newLastDrop);
        }

        if ($linkedLastTracking) {
            $linkedLastTracking->setLastTracking($newLastTracking);
        }

        $firstDropsRecordIds = $trackingMovement->getFirstDropsRecords()
            ->map(function (LocationClusterRecord $record) {
                return $record->getId();
            })
            ->toArray();
        $this->treatFirstDropRecord($trackingMovement);
        $this->treatLastTrackingRecord($firstDropsRecordIds, $trackingMovement);

        $this->treatLocationClusterMeter($trackingMovement);

        $this->entityManager->flush();
    }

    private function treatFirstDropRecord(MouvementTraca $trackingMovement): void {
        $pack = $trackingMovement->getPack();
        $firstDropRecords = $trackingMovement->getFirstDropsRecords();
        /** @var LocationClusterRecord $firstDropRecords */
        foreach ($firstDropRecords as $firstDropRecord) {
            // record inactive OR firstDrop == lastTracking (one movement on cluster)
            if (!$firstDropRecord->isActive()
                || $trackingMovement === $firstDropRecord->getLastTracking()) {
                $firstDropRecord->setFirstDrop(null);
                $this->entityManager->remove($firstDropRecord);
            }
            else {
                $replacedTracking = null;
                // get next drop on cluster
                foreach ($pack->getTrackingMovements() as $packTrackingMovement) {
                    if ($packTrackingMovement === $trackingMovement) {
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
                                             MouvementTraca $trackingMovement): void {
        $pack = $trackingMovement->getPack();
        $lastTrackingRecords = $trackingMovement->getLastTrackingRecords();
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
                        if ($packTrackingMovement === $trackingMovement) {
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
