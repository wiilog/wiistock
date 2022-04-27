<?php

namespace App\EventListener;

use App\Exceptions\FormException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class FormExceptionListener {

    public function onKernelException(ExceptionEvent $event) {
        if($event->getThrowable() instanceof FormException) {
            $exception = $event->getThrowable();
            $event->allowCustomResponseCode();
            $event->setResponse(new JsonResponse([
                "success" => false,
                "msg" => $exception->getMessage(),
            ], $exception->getCode() ?: Response::HTTP_OK));
        }
    }

}
