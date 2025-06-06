<?php

namespace App\Messenger\Handler;

use App\Messenger\Message\DeduplicatedMessage\WaitingDeduplicatedMessage\CalculateTrackingDelayMessage;
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
 * @see CalculateTrackingDelayMessage
 */
#[AsMessageHandler(fromTransport: "async_tracking_delay")]
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

    public function __invoke(CalculateTrackingDelayMessage $message): void {
        $this->handle($message);
    }

    /**
     * @param CalculateTrackingDelayMessage $message Not typed in php to implement LoggedHandler
     */
    protected function processWithLock(MessageInterface $message): void {
        $success = $this->packService->updateTrackingDelayWithPackCode($this->entityManager, $message->getPackCode());
        if ($success) {
            $this->entityManager->flush();
        }
    }
}
