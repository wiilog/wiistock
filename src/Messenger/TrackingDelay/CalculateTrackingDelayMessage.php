<?php

namespace App\Messenger\TrackingDelay;

use App\Messenger\MessageInterface;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\UniqueWaitingMessage;

class CalculateTrackingDelayMessage extends UniqueWaitingMessage implements MessageInterface {

    public function __construct(private string $packCode) {}

    public function getPackCode(): string {
        return $this->packCode;
    }

    public function getUniqueKey(): string {
        return $this->packCode;
    }

    public function normalize(): array {
        return [
            "packCode" => $this->getPackCode(),
        ];
    }
}
