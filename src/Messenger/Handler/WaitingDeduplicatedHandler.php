<?php

namespace App\Messenger\Handler;

use App\Messenger\Message\DeduplicatedMessage\WaitingDeduplicatedMessage\WaitingDeduplicatedMessage;
use App\Messenger\Message\DeduplicatedMessage\WaitingDeduplicatedMessageWrapper;
use App\Messenger\Message\MessageInterface;
use App\Service\ExceptionLoggerService;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Define process method handler which extends it.
 * We check if an equal message (with uniqueKey) is already being processed then it process the message with the methode processDeduplicated.
 * Else we put it in the waiting queue via the message wrapper.
 * A message can be put in the waiting queue max MAX_WAITING_TIMES times
 */
abstract class WaitingDeduplicatedHandler extends LoggedHandler implements LockHandlerInterface {

    /**
     * Number of time we can put the message in the waiting queue.
     */
    private const MAX_WAITING_TIMES = 4;

    public function __construct(
        private ExceptionLoggerService $loggerService,
        private MessageBusInterface    $messageBus,
        private LockFactory            $lockFactory,
    ) {
        parent::__construct($this->loggerService);
    }

    /**
     * @param WaitingDeduplicatedMessage $message Not typed in php to implement LoggedHandler
     */
    protected final function process(MessageInterface $message): void {
        $lock = $this->lockFactory->createLock(self::LOCK_PREFIX . '_' . $message->getUniqueKey());

        // wait for other process finish
        $acquired = $lock->acquire();

        if ($acquired) {
            $this->processWithLock($message);
            $lock->release();
        }
        else {
            // calculation already in progress => send to waiting queue if the message
            // if the message have already waited long enough then we ignore it else we resend et to the waiting queue
            if ($message->getWaitingTimes() <= self::MAX_WAITING_TIMES) {
                $message->incrementWaitingTimes();
                $this->messageBus->dispatch(new WaitingDeduplicatedMessageWrapper($message));
            }
        }

    }

    /**
     * Method implemented by child handlers to process the message
     */
    protected abstract function processWithLock(MessageInterface $message): void;
}
