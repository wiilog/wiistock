<?php

namespace App\Messenger\Message\DeduplicatedMessage\WaitingDeduplicatedMessage;

use App\Messenger\Handler\WaitingDeduplicatedHandler;
use App\Messenger\Message\DeduplicatedMessage\DeduplicatedMessageInterface;


/**
 * Message which will be pushed in a waiting queue if a message with the same uniqueKey is already being processed.
 * @see WaitingDeduplicatedHandler
 */
abstract class WaitingDeduplicatedMessage implements DeduplicatedMessageInterface {

    private int $waitingTimes = 0;

    public function getWaitingTimes(): int {
        return $this->waitingTimes;
    }

    public function incrementWaitingTimes(): self {
        $this->waitingTimes++;
        return $this;
    }
}
