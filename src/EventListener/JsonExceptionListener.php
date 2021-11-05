<?php

namespace App\EventListener;

use App\Exception\JsonException;
use HttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class JsonExceptionListener {

    public function onKernelException(ExceptionEvent $event) {
        if($event->getThrowable() instanceof JsonException) {
            $event->allowCustomResponseCode();
            $event->setResponse(new JsonResponse([
                "success" => false,
                "msg" => $event->getThrowable()->getMessage(),
            ]));
        }
    }

}
