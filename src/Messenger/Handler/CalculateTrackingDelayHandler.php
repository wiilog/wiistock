<?php

namespace App\Messenger\Handler;

use App\Messenger\Message\DeduplicatedMessage\WaitingDeduplicatedMessage\AsyncCalculateTrackingDelayMessage;
use App\Messenger\Message\DeduplicatedMessage\WaitingDeduplicatedMessage\SyncCalculateTrackingDelayMessage;
use App\Messenger\Message\MessageInterface;
use App\Service\ExceptionLoggerService;
use App\Service\Tracking\PackService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;


/**
 * Handle message to calculate tracking delay (CalculateTrackingDelayMessage)
 * We can't calculate tracking delay on same time for the same logistic unit.
 *
 * @see WaitingDeduplicatedHandler
 * @see AsyncCalculateTrackingDelayMessage
 * @see SyncCalculateTrackingDelayMessage
 */
#[AsMessageHandler(fromTransport: "async_tracking_delay")]
#[AsMessageHandler(fromTransport: "sync_tracking_delay")]
class CalculateTrackingDelayHandler extends WaitingDeduplicatedHandler {

    public function __construct(
        ExceptionLoggerService         $loggerService,
        MessageBusInterface            $messageBus,
        LockFactory                    $lockFactory,
        private EntityManagerInterface $entityManager,
        private PackService            $packService,
    ) {
        parent::__construct($loggerService, $messageBus, $lockFactory);
    }

    public function __invoke(AsyncCalculateTrackingDelayMessage | SyncCalculateTrackingDelayMessage $message): void {
        $this->handle($message);
    }

    /**
     * @param AsyncCalculateTrackingDelayMessage|SyncCalculateTrackingDelayMessage $message Not typed in php to implement LoggedHandler
     */
    protected function processWithLock(MessageInterface $message): void {
        $success = $this->packService->updateTrackingDelayWithPackCode($this->entityManager, $message->getPackCode());
        if ($success) {
            $this->entityManager->flush();
        }
    }
}
