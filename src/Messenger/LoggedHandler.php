<?php

namespace App\Messenger;

use App\Service\ExceptionLoggerService;
use Exception;
use ReflectionClass;
use Throwable;

abstract class LoggedHandler {

    public function __construct(
        private ExceptionLoggerService $loggerService
    ) {
    }

    protected function handle(MessageInterface $message): void {
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

    abstract protected function process(MessageInterface $message): void;

}
