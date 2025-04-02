<?php

namespace App\Messenger;

/**
 * Message which define the unique key which let system to deduplicate it in queue
 * @see MessageBus
 */
interface DeduplicatedMessageInterface extends MessageInterface {

    public function getUniqueKey(): string|int;

}
