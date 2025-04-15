<?php

namespace App\Messenger\Message;

/**
 * Interface implemented by all application messages.
 */
interface MessageInterface {

    /**
     * Give ability to normalize the message.
     * @return array
     */
    public function normalize(): array;

}
