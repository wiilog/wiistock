<?php

namespace App\Messenger;

use App\Messenger\TrackingDelay\CalculateTrackingDelayMessage;
use App\Service\ExceptionLoggerService;
use Exception;

abstract class LoggedHandler
{
    public function __construct(private ExceptionLoggerService $loggerService) {}

    protected function handle(CalculateTrackingDelayMessage $message): void {
        try {
            $this->process($message);
        }
        catch(Exception $exception) {
            $this->loggerService->sendLog($exception);
        }
    }
    abstract function process(CalculateTrackingDelayMessage $message): void;

}
