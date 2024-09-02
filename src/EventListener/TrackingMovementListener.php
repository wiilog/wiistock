<?php


namespace App\EventListener;

use App\Entity\CategorieCL;
use App\Entity\LocationCluster;
use App\Entity\LocationClusterMeter;
use App\Entity\LocationClusterRecord;
use App\Entity\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\OperationHistory\LogisticUnitHistoryRecord;
use App\Entity\Pack;
use App\Entity\TrackingMovement;
use App\Entity\Type;
use App\Service\FormatService;
use App\Service\FreeFieldService;
use App\Service\MailerService;
use App\Service\PackService;
use App\Service\TrackingMovementService;
use App\Service\TranslationService;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class TrackingMovementListener implements EventSubscriber
{
    #[Required]
    public MailerService $mailerService;

    #[Required]
    public TrackingMovementService $trackingMovementService;

    #[Required]
    public FreeFieldService $freeFieldService;

    #[Required]
    public TranslationService $translation;

    #[Required]
    public RouterInterface $router;

    /**
     * @var TrackingMovement[]
     */
    private array $flushedTackingMovements = [];

    private ?Type $trackingMovementType = null;

    public function __construct(private readonly PackService $packService, private readonly FormatService $formatService)
    {
    }

    public function getSubscribedEvents(): array {
        return [
            'preRemove',
            'onFlush',
            'postFlush',
        ];
    }

    #[AsEventListener(event: 'preRemove')]
    public function preRemove(TrackingMovement $movementToDelete,
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
        $this->flushedTackingMovements = Stream::from($args->getObjectManager()->getUnitOfWork()->getScheduledEntityInsertions())
            ->filter(static fn($entity) => $entity instanceof TrackingMovement)
            ->toArray();

        foreach ($this->flushedTackingMovements as $trackingMovement) {
            $pack = $trackingMovement->getPack();
            if(isset($pack) && $pack->isBasic()) {
                if($trackingMovement->isDrop()) {
                    $nature = $trackingMovement->getEmplacement()?->getNewNatureOnDrop();
                }
                else if ($trackingMovement->isPicking()) {
                    $nature = $trackingMovement->getEmplacement()?->getNewNatureOnPick();
                }

                if(isset($nature)) {
                    $oldNature = $pack->getNature();
                    $user = $trackingMovement->getOperateur();
                    $pack->setNature($nature);
                    $unitOfWork = $args->getObjectManager()->getUnitOfWork();
                    $unitOfWork->recomputeSingleEntityChangeSet(
                        $args->getObjectManager()->getClassMetadata(Pack::class),
                        $pack
                    );

                    $message =
                        'Ancienne nature : ' . $this->formatService->nature($oldNature,'-') . '
                        Nouvelle nature : ' . $this->formatService->nature($nature,'-')
                    ;

                    $historyRecord = $this->packService->persistLogisticUnitHistoryRecord(
                        $unitOfWork,
                        $pack,
                        $message,
                        $trackingMovement->getDatetime(),
                        $user,
                        "Mise a jour automatique de la nature",
                        $trackingMovement->getEmplacement(),
                    );
                    $unitOfWork->computeChangeSet($args->getObjectManager()->getClassMetadata(LogisticUnitHistoryRecord::class), $historyRecord);
                }
            }
        }

    }

    #[AsEventListener(event: 'postFlush')]
    public function postFlush(PostFlushEventArgs $args): void {
        foreach ($this->flushedTackingMovements ?? [] as $trackingMovement) {
            if ($trackingMovement->isDrop()) {
                $location = $trackingMovement->getEmplacement();
                if ($location && $location->isSendEmailToManagers()) {
                    $managers = $location->getManagers();
                    if (!$managers->isEmpty()) {
                        if(!isset($this->trackingMovementType)) {
                            $objectManager = $args->getObjectManager();
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
                            $this->translation->translate('Général', null, 'Header', 'Wiilog', false) . MailerService::OBJECT_SERPARATOR . "Dépose d'unité logistique sur un emplacement dont vous êtes responsable",
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
    }

    public function treatPackLinking(TrackingMovement $movementToDelete,
                                     EntityManager $entityManager): void {
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);

        $pack = $movementToDelete->getPack();

        $lastDropToUpdate = (
            !$pack->getLastDrop()
            || $pack->getLastDrop()->getId() === $movementToDelete->getId()
        );

        $firstTrackingToUpdate = (
            $pack->getFirstTracking()
            && $pack->getFirstTracking()->getId() === $movementToDelete->getId()
        );

        $lastTrackingToUpdate = (
            $pack->getLastTracking()
            && $pack->getLastTracking()->getId() === $movementToDelete->getId()
        );

        $lastStartToUpdate = (
            $pack->getLastStart()
            && $pack->getLastStart()->getId() === $movementToDelete->getId()
        );

        $lastStopToUpdate = (
            $pack->getLastStop()
            && $pack->getLastStop()->getId() === $movementToDelete->getId()
        );

        $firstTracking = $firstTrackingToUpdate
            ? $trackingMovementRepository->findFistTrackingByPack($pack, $movementToDelete)
            : null;

        $lastTracking = ($lastTrackingToUpdate || $lastDropToUpdate)
            ? $trackingMovementRepository->findLastByPack("tracking", $pack, $movementToDelete)
            : null;

        $lastStart = $lastStartToUpdate
            ? $trackingMovementRepository->findLastByPack("start", $pack, $movementToDelete)
            : null;

        $lastStop = $lastStopToUpdate
            ? $trackingMovementRepository->findLastByPack("stop", $pack, $movementToDelete)
            : null;

        // set movements

        if ($firstTrackingToUpdate) {
            $pack->setFirstTracking($firstTracking);
        }

        if ($lastTrackingToUpdate) {
            $pack->setLastTracking($lastTracking);
        }

        if ($lastDropToUpdate && $lastTracking?->isDrop()) {
            $pack->setLastDrop($lastTracking);
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

        $entityManager->flush($firstDropRecords->toArray());
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
                        foreach ($pack->getTrackingMovements('ASC') as $packTrackingMovement) {
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

        $entityManager->flush($lastTrackingRecords->toArray());
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

                $entityManager->flush($meters->toArray());
            }
        }
    }
}
