<?php


namespace App\EventListener;

use App\Entity\CategorieCL;
use App\Entity\LocationCluster;
use App\Entity\LocationClusterMeter;
use App\Entity\LocationClusterRecord;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Type;
use App\Messenger\Message\DeduplicatedMessage\WaitingDeduplicatedMessage\CalculateTrackingDelayMessage;
use App\Service\FreeFieldService;
use App\Service\MailerService;
use App\Service\Tracking\TrackingDelayService;
use App\Service\Tracking\TrackingMovementService;
use App\Service\TranslationService;
use Doctrine\Common\Collections\Order;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\RouterInterface;

class TrackingMovementListener implements EventSubscriber
{

    /**
     * @var TrackingMovement[]
     */
    private array $flushedTrackingMovementsInserted = [];
    private array $packCodesToRecalculateTrackingDelay = [];

    private ?Type $trackingMovementType = null;

    public function __construct(
        private MessageBusInterface     $messageBus,
        private MailerService           $mailerService,
        private TrackingMovementService $trackingMovementService,
        private FreeFieldService        $freeFieldService,
        private TranslationService      $translation,
        private RouterInterface         $router,
        private TrackingDelayService    $trackingDelayService,
    ) { }

    public function getSubscribedEvents(): array {
        return [
            'preRemove',
            'onFlush',
            'postFlush',
        ];
    }

    #[AsEventListener(event: 'preRemove')]
    public function preRemove(TrackingMovement   $movementToDelete,
                              PreRemoveEventArgs $lifecycleEventArgs): void
    {
        $entityManager = $lifecycleEventArgs->getObjectManager();

        $firstDropsRecordIds = $movementToDelete->getFirstDropsRecords()
            ->map(static fn (LocationClusterRecord $record) => $record->getId())
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
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        $flushedTrackingMovementsDeleted = $unitOfWork->getScheduledCollectionDeletions();
        $flushedTrackingMovementsInserted = $unitOfWork->getScheduledEntityInsertions();

        $this->packCodesToRecalculateTrackingDelay = [];
        $this->flushedTrackingMovementsInserted = [];

        foreach($flushedTrackingMovementsDeleted as $entity) {
            if ($entity instanceof TrackingMovement) {
                $pack = $entity->getPack();
                $packCode = $pack->getCode();
                if ($packCode) {
                    $this->packCodesToRecalculateTrackingDelay[$packCode] = true;
                }
            }
        }

        foreach($flushedTrackingMovementsInserted as $entity) {
            if ($entity instanceof TrackingMovement) {
                $this->flushedTrackingMovementsInserted[] = $entity;

                $shouldCalculateTrackingDelay = $this->trackingDelayService->shouldCalculateTrackingDelay($entity);

                if ($shouldCalculateTrackingDelay) {
                    $pack = $entity->getPack();
                    $packCode = $pack->getCode();
                    if ($packCode) {
                        $this->packCodesToRecalculateTrackingDelay[$packCode] = true;
                    }
                }
            }
        }
    }

    #[AsEventListener(event: 'postFlush')]
    public function postFlush(PostFlushEventArgs $args): void {
        $objectManager = $args->getObjectManager();
        foreach ($this->flushedTrackingMovementsInserted ?? [] as $trackingMovement) {
            if ($trackingMovement->isDrop()) {
                $location = $trackingMovement->getEmplacement();
                if ($location && $location->isSendEmailToManagers()) {
                    $managers = $location->getManagers();
                    if (!$managers->isEmpty()) {
                        if(!isset($this->trackingMovementType)) {
                            $typeRepository = $objectManager->getRepository(Type::class);
                            $this->trackingMovementType = $typeRepository->findOneByLabel(Type::LABEL_MVT_TRACA);
                        }
                        $freeFields = $this->freeFieldService->getFilledFreeFieldArray(
                            $args->getObjectManager(),
                            $trackingMovement,
                            [
                                "freeFieldCategoryLabel" => CategorieCL::MVT_TRACA,
                                "type" => $this->trackingMovementType
                            ],
                            null,
                        );

                        $this->mailerService->sendMail(
                            $objectManager,
                            $this->translation->translate('Général', null, 'Header', 'Wiilog', false) . MailerService::OBJECT_SEPARATOR . "Dépose d'unité logistique sur un emplacement dont vous êtes responsable",
                            [
                                'name' => 'mails/contents/mailDropLuOnLocation.html.twig',
                                'context' => [
                                    'trackingMovement' => $trackingMovement,
                                    'location' => $location,
                                    'from' => $this->trackingMovementService->getFromColumnData($trackingMovement),
                                    'freeFields' => $freeFields,
                                ],
                                "urlSuffix" => $this->router->generate("mvt_traca_index", [
                                    "pack" => $trackingMovement->getPack()->getCode(),
                                ]),
                            ],
                            $managers->toArray()
                        );
                    }
                }
            }
        }

        $packCodesToRecalculateTrackingDelay = array_keys($this->packCodesToRecalculateTrackingDelay);
        foreach ($packCodesToRecalculateTrackingDelay as $code) {
            $this->messageBus->dispatch(new CalculateTrackingDelayMessage($code));
        }
    }

    public function treatPackLinking(TrackingMovement $movementToDelete,
                                     EntityManager $entityManager): void {
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);

        $pack = $movementToDelete->getPack();

        if (!$pack) {
            return;
        }

        $lastOngoingDropToUpdate = (
            !$pack->getLastOngoingDrop()
            || $pack->getLastOngoingDrop()->getId() === $movementToDelete->getId()
        );

        $firstActionToUpdate = (
            $pack->getFirstAction()
            && $pack->getFirstAction()->getId() === $movementToDelete->getId()
        );

        $lastDropToUpdate = (
            $pack->getLastDrop()
            && $pack->getLastDrop()->getId() === $movementToDelete->getId()
        );

        $lastPickingToUpdate = (
            $pack->getLastPicking()
            && $pack->getLastPicking()->getId() === $movementToDelete->getId()
        );

        $lastActionToUpdate = (
            $pack->getLastAction()
            && $pack->getLastAction()->getId() === $movementToDelete->getId()
        );

        $lastStartToUpdate = (
            $pack->getLastStart()
            && $pack->getLastStart()->getId() === $movementToDelete->getId()
        );

        $lastStopToUpdate = (
            $pack->getLastStop()
            && $pack->getLastStop()->getId() === $movementToDelete->getId()
        );

        $firstAction = $firstActionToUpdate
            ? $trackingMovementRepository->findFistActionByPack($pack, $movementToDelete)
            : null;

        $lastAction = ($lastActionToUpdate || $lastOngoingDropToUpdate)
            ? $trackingMovementRepository->findLastByPack("action", $pack, $movementToDelete)
            : null;

        $lastStart = $lastStartToUpdate
            ? $trackingMovementRepository->findLastByPack("start", $pack, $movementToDelete)
            : null;

        $lastStop = $lastStopToUpdate
            ? $trackingMovementRepository->findLastByPack("stop", $pack, $movementToDelete)
            : null;

        $lastPicking = $lastPickingToUpdate
            ? $trackingMovementRepository->findLastByPack("picking", $pack, $movementToDelete)
            : null;

        $lastDrop = $lastDropToUpdate
            ? $trackingMovementRepository->findLastByPack("drop", $pack, $movementToDelete)
            : null;

        // set movements

        if ($firstActionToUpdate) {
            $pack->setFirstAction($firstAction);
        }

        if ($lastActionToUpdate) {
            $pack->setLastAction($lastAction);
        }

        if ($lastPickingToUpdate) {
            $pack->setLastPicking($lastPicking);
        }

        if ($lastDropToUpdate) {
            $pack->setLastDrop($lastDrop);
        }

        if ($lastOngoingDropToUpdate && $lastAction?->isDrop()) {
            $pack->setLastOngoingDrop($lastAction);
        }

        if ($lastStartToUpdate) {
            $pack->setLastStart($lastStart);
        }

        if ($lastStopToUpdate) {
            $pack->setLastStop($lastStop);
        }

        $entityManager->getUnitOfWork()->recomputeSingleEntityChangeSet(
            $entityManager->getClassMetadata(Pack::class),
            $pack
        );
    }

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
    }

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
                        foreach ($pack->getTrackingMovements(Order::Ascending) as $packTrackingMovement) {
                            if ($packTrackingMovement === $movementToDelete) {
                                break;
                            }
                            $replacedTracking = $packTrackingMovement;
                        }
                    }

                    if (isset($replacedTracking)){
                        $record->setLastTracking($replacedTracking);
                        if ($replacedTracking->isPicking()) {
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
    }

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
            }
        }
    }
}
