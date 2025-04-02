<?php

namespace App\Messenger\TrackingDelay;

use App\Messenger\DeduplicatedMessageInterface;

class CalculateTrackingDelayMessage implements DeduplicatedMessageInterface {

    public function __construct(
        private string $packCode,
    ) {
    }

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
