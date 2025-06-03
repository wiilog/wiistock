<?php

namespace App\Messenger\Handler;

use App\Messenger\Message\MessageInterface;
use App\Service\ExceptionLoggerService;
use Exception;
use ReflectionClass;
use Throwable;


/**
 * Handler which implements catch of any errors in child, and send it to the logger.
 */
abstract class LoggedHandler {

    public function __construct(
        private ExceptionLoggerService $loggerService
    ) {
    }

    protected final function handle(MessageInterface $message): void {
        try {
            $this->process($message);
        }
        catch(Throwable $exception) {
            // add message in sent exception
            $reflexMessage = new ReflectionClass($message);
            $serializedMessage = $reflexMessage->getName() . " " . json_encode($message->normalize());
            $sentException = new Exception($serializedMessage, 0, $exception);

            $this->loggerService->sendLog($sentException);
        }
    }

    /**
     * Method implemented by child handlers to process the message
     */
    abstract protected function process(MessageInterface $message): void;

}
