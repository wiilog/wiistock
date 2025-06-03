<?php

namespace App\Messenger\Message\DeduplicatedMessage\WaitingDeduplicatedMessage;

class CalculateTrackingDelayMessage extends WaitingDeduplicatedMessage {

    public function __construct(
        private string $packCode,
    ) {
    }

    public function getPackCode(): string {
        return $this->packCode;
    }

    public function getUniqueKey(): string {
        $classCode = str_replace("\\", "_", get_class($this));
        return "{$classCode}_{$this->packCode}";
    }

    public function normalize(): array {
        return [
            "packCode" => $this->getPackCode(),
        ];
    }
}
