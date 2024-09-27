<?php

namespace App\Messenger\TrackingDelay;

class CalculateTrackingDelayMessage {

    public function __construct(private string $packCode) {}

    public function getPackCode(): string {
        return $this->packCode;
    }
}
