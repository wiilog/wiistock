<?php

namespace App\Messenger\Message\DeduplicatedMessage;

use App\Messenger\Message\MessageInterface;
use App\Messenger\Middleware\DeduplicationMiddleware;

/**
 * Message which define the unique key which let system to deduplicate it in the queue
 * @see DeduplicationMiddleware
 */
interface DeduplicatedMessageInterface extends MessageInterface {

    public function getUniqueKey(): string;

}
