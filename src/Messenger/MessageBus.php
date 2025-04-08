<?php

namespace App\Messenger;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Envelope;
use \Symfony\Component\Messenger\MessageBus as SymfonyMessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use WiiCommon\Helper\Stream;

/**
 * Extends symfony default message bus
 * Add a header for deduplicated message to deduplicate them on amqp queue level.
 * Injected as MessageBusInterface in all application
 */
#[AsAlias(MessageBusInterface::class)]
class MessageBus extends SymfonyMessageBus {

    public function dispatch(object $message,
                             array  $stamps = []): Envelope {

        if ($message instanceof DeduplicatedMessageInterface) {
            $stamps = Stream::from($stamps)
                ->prepend(
                    AmqpStamp::createWithAttributes([
                        'headers' => [
                            'x-deduplication-header' => $message->getUniqueKey(),
                        ]
                    ])
                )
                ->toArray();
        }

        return parent::dispatch($message, $stamps);
    }
}
