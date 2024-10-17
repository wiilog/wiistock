<?php

namespace App\Messenger;

use App\Service\ExceptionLoggerService;
use Throwable;

abstract class LoggedHandler
{
    public function __construct(private ExceptionLoggerService $loggerService) {}

    protected function handle(mixed $message): void {
        try {
            $this->process($message);
        }
        catch(Throwable $exception) {
            $this->loggerService->sendLog($exception);
        }
    }
    abstract protected function process(mixed $message): void;

}
