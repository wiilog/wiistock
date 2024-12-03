<?php


namespace App\EventListener;

use App\Entity\Nature;
use App\Entity\Tracking\Pack;
use App\Messenger\TrackingDelay\CalculateTrackingDelayMessage;
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
                $this->messageBus->dispatch(new CalculateTrackingDelayMessage($pack->getCode()));
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
        foreach ($this->insertedPacks as $pack) {
            if ($pack?->getNature()?->getTrackingDelay()) {
                $this->messageBus->dispatch(new CalculateTrackingDelayMessage($pack->getCode()));
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
