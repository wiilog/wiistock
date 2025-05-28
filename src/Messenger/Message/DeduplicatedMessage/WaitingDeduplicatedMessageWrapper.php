<?php

namespace App\Messenger\Message\DeduplicatedMessage;


use App\Messenger\Message\DeduplicatedMessage\WaitingDeduplicatedMessage\WaitingDeduplicatedMessage;

/**
 * Message which will wrap a WaitingDeduplicatedMessage in the waiting queue
 * This wrapper message is deduplicated in the waiting queue.
 *
 * @see WaitingDeduplicatedMessage
 */
class WaitingDeduplicatedMessageWrapper implements DeduplicatedMessageInterface {

    public function __construct(
        private WaitingDeduplicatedMessage $message,
    ) {}

    public function getMessage(): WaitingDeduplicatedMessage {
        return $this->message;
    }

    public function normalize(): array {
        return [
            "message" => $this->message->normalize(),
        ];
    }

    public function getUniqueKey(): string {
        return $this->message->getUniqueKey();
    }
}
