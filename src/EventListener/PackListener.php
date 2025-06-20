<?php


namespace App\EventListener;

use App\Entity\Nature;
use App\Entity\Tracking\Pack;
use App\Messenger\Message\DeduplicatedMessage\WaitingDeduplicatedMessage\AsyncCalculateTrackingDelayMessage;
use App\Messenger\Message\DeduplicatedMessage\WaitingDeduplicatedMessage\SyncCalculateTrackingDelayMessage;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;
use WiiCommon\Helper\Stream;

class PackListener implements EventSubscriber {

    /**
     * @var Pack[]
     */
    private array $insertedPacks = [];

    public static bool $needSyncTrackingDelayProcessing = false;

    public function __construct(private MessageBusInterface $messageBus) {}

    #[AsEventListener(event: "preUpdate")]
    public function preUpdate(Pack               $pack,
                               PreUpdateEventArgs $lifecycleEventArgs): void {
        if ($lifecycleEventArgs->hasChangedField("nature")) {
            /** @var Nature|null $oldNature */
            $oldNature = $lifecycleEventArgs->getOldValue("nature");

            /** @var Nature|null $newNature */
            $newNature = $lifecycleEventArgs->getNewValue("nature");

            $oldNatureTrackingDelay = $oldNature?->getTrackingDelay();
            $newNatureTrackingDelay = $newNature?->getTrackingDelay();

            if ($oldNatureTrackingDelay !== $newNatureTrackingDelay) {
                $this->messageBus->dispatch(new AsyncCalculateTrackingDelayMessage($pack->getCode()));
            }
        }
    }
    #[AsEventListener(event: "onFlush")]
    public function onFlush(OnFlushEventArgs $args): void {
        $this->insertedPacks = Stream::from($args->getObjectManager()->getUnitOfWork()->getScheduledEntityInsertions())
            ->filter(static fn(mixed $entity) => $entity instanceof Pack)
            ->toArray();
    }

    #[AsEventListener(event: "postFlush")]
    public function postFlush(): void {
        $needSyncProcess = self::$needSyncTrackingDelayProcessing;
        foreach ($this->insertedPacks as $pack) {
            /**
             * Same condition is used in @see ArrivageController::postArrivalTrackingMovements please keep it in sync!
             */
            if ($pack?->shouldHaveTrackingDelay()) {
                $message = match($needSyncProcess) {
                    true => new SyncCalculateTrackingDelayMessage($pack->getCode()),
                    default => new AsyncCalculateTrackingDelayMessage($pack->getCode()),
                };

                $this->messageBus->dispatch($message);
            }
        }
        $this->insertedPacks = [];
    }

    public function getSubscribedEvents(): array {
        return [
            "preUpdate",
            "onFlush",
            "postFlush",
        ];
    }
}
