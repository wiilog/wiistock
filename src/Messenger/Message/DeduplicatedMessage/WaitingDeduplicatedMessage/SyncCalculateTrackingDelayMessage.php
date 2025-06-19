<?php

namespace App\Messenger\Message\DeduplicatedMessage\WaitingDeduplicatedMessage;

class SyncCalculateTrackingDelayMessage extends CalculateTrackingDelayMessage {
    public const MAX_SYNC_MESSAGES = 5;
}
