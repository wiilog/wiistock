<?php

namespace App\Messenger\Middleware;

use App\Messenger\Message\DeduplicatedMessage\DeduplicatedMessageInterface;
use App\Messenger\Message\DeduplicatedMessage\WaitingDeduplicatedMessage\WaitingDeduplicatedMessage;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Messenger middleware which add the deduplication header to all Message implementing DeduplicatedMessageInterface.
 * We don't add the header to WaitingDeduplicatedMessage until the message will be in the waiting queue
 *
 * @see DeduplicatedMessageInterface
 * @see WaitingDeduplicatedMessage
 */
class DeduplicationMiddleware implements MiddlewareInterface {

    public function handle(Envelope $envelope, StackInterface $stack): Envelope {
        $message = $envelope->getMessage();
        if ($message instanceof DeduplicatedMessageInterface
            && !($message instanceof WaitingDeduplicatedMessage)) {
            $envelope = $envelope->with(
                AmqpStamp::createWithAttributes([
                    'headers' => [
                        'x-deduplication-header' => $message->getUniqueKey(),
                    ]
                ])
            );
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
