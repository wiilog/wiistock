<?php

namespace App\EventListener;

use App\Exception\JsonException;
use App\Service\ExceptionLoggerService;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class ExceptionLoggerListener {

    private $exceptionLoggerService;

    public function __construct(ExceptionLoggerService $exceptionLoggerService) {
        $this->exceptionLoggerService = $exceptionLoggerService;
    }

    public function onKernelException(ExceptionEvent $event) {
        if($event->getThrowable() instanceof JsonException) {
            return;
        }

        $this->exceptionLoggerService->sendLog($event->getThrowable(), $event->getRequest());
    }

}
