<?php

namespace App\Messenger\Handler;

use App\Messenger\Message\DeduplicatedMessage\WaitingDeduplicatedMessageWrapper;
use App\Messenger\Message\MessageInterface;
use App\Service\ExceptionLoggerService;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handle message in the waiting queue, it waits the lock released for the current message and then re push it in the original queue.
 */
#[AsMessageHandler(fromTransport: "async_waiting")]
class WaitingHandler extends LoggedHandler implements LockHandlerInterface {

    public function __construct(
        private ExceptionLoggerService $loggerService,
        private MessageBusInterface    $messageBus,
        private LockFactory            $lockFactory,
    ) {
        parent::__construct($this->loggerService);
    }

    public function __invoke(WaitingDeduplicatedMessageWrapper $message): void {
        $this->handle($message);
    }

    /**
     * @param WaitingDeduplicatedMessageWrapper $message Not typed in php to implement LoggedHandler
     */
    protected final function process(MessageInterface $message): void {
        $originalMessage = $message->getMessage();

        $lock = $this->lockFactory->createLock(self::LOCK_PREFIX . '_' . $originalMessage->getUniqueKey());

        // wait for other process finish
        $lock->acquire(true);
        $lock->release();

        // resend original message on the original queue
        $this->messageBus->dispatch($originalMessage);
    }

}
