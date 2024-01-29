<?php


namespace App\EventListener;


use AllowDynamicProperties;
use App\Entity\CategorieCL;
use App\Entity\LocationCluster;
use App\Entity\LocationClusterMeter;
use App\Entity\LocationClusterRecord;
use App\Entity\TrackingMovement;
use App\Service\FreeFieldService;
use App\Service\MailerService;
use App\Service\TrackingMovementService;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Service\Attribute\Required;


#[AllowDynamicProperties] class TrackingMovementListener implements EventSubscriber
{
    #[Required]
    public MailerService $mailerService;

    #[Required]
    public TrackingMovementService $trackingMovementService;

    #[Required]
    public FreeFieldService $freeFieldService;

    public function getSubscribedEvents(): array {
        return [
            'preRemove',
            'onFlush',
            'postFlush',
        ];
    }

    /**
     * @param TrackingMovement $movementToDelete
     * @param LifecycleEventArgs $lifecycleEventArgs
     * @throws ORMException
     * @throws OptimisticLockException
     */
    #[AsEventListener(event: 'preRemove')]
    public function preRemove(TrackingMovement $movementToDelete,
                              LifecycleEventArgs $lifecycleEventArgs): void
    {
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

        $pack = $movementToDelete->getPack();
        if ($pack) {
            $pack->removeTrackingMovement($movementToDelete);
        }
    }

    #[AsEventListener(event: 'onFlush')]
    public function onFlush(OnFlushEventArgs $args): void {
        $this->entityInsertBuffer = $args->getObjectManager()->getUnitOfWork()->getScheduledEntityInsertions();
    }

    #[AsEventListener(event: 'postFlush')]
    public function postFlush(PostFlushEventArgs $args): void {
        foreach ($this->entityInsertBuffer ?? [] as $entity) {
            if ($entity instanceof TrackingMovement) {
                if ($entity->isDrop()) {
                    $location = $entity->getEmplacement();
                    if ($location->isSendEmailToManagers()) {
                        $managers = $location?->getManagers();
                        if ($managers) {
                            $freeFields = $this->freeFieldService->getFilledFreeFieldArray(
                                $args->getObjectManager(),
                                $entity,
                                ["freeFieldCategoryLabel" => CategorieCL::MVT_TRACA],
                                null,
                            );

                            $this->mailerService->sendMail(
                                "FOLLOW GT // Dépose d'unité logistique sur un emplacement dont vous êtes responsable",
                                [
                                    'name' => 'mails/contents/mailDropLuOnLocation.html.twig',
                                    'context' => [
                                        'trackingMovement' => $entity,
                                        'location' => $location,
                                        'from' => $this->trackingMovementService->getFromColumnData($entity),
                                        'freeFields' => $freeFields,
                                    ],
                                ],
                                $managers->toArray()
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * @param EntityManager $entityManager
     * @param TrackingMovement $movementToDelete
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function treatPackLinking(TrackingMovement $movementToDelete,
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
     * @param TrackingMovement $movementToDelete
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function treatFirstDropRecordLinking(TrackingMovement $movementToDelete,
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
                if ($pack) {
                    foreach ($pack->getTrackingMovements() as $packTrackingMovement) {
                        if ($packTrackingMovement === $movementToDelete) {
                            break;
                        }
                        else if ($packTrackingMovement->isDrop()) {
                            $replacedTracking = $packTrackingMovement;
                        }
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
     * @param TrackingMovement $movementToDelete
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function treatLastTrackingRecordLinking(array $recordIdsToIgnore,
                                                    TrackingMovement $movementToDelete,
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
                    if ($pack) {
                        // get last taking
                        foreach ($pack->getTrackingMovements('ASC') as $packTrackingMovement) {
                            if ($packTrackingMovement === $movementToDelete) {
                                break;
                            }
                            $replacedTracking = $packTrackingMovement;
                        }
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
     * @param TrackingMovement $trackingMovement
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function treatLocationClusterMeterLinking(TrackingMovement $trackingMovement,
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
