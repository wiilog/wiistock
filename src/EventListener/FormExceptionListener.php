<?php

namespace App\EventListener;

use App\Exceptions\FormException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class FormExceptionListener {

    public function onKernelException(ExceptionEvent $event) {
        if($event->getThrowable() instanceof FormException) {
            $throwable = $event->getThrowable();

            $event->allowCustomResponseCode();
            $event->setResponse(new JsonResponse(
                [
                    "success" => false,
                    "msg" => $throwable->getMessage(),
                ],
                $throwable->getCode() ?: Response::HTTP_OK
            ));
        }
    }

}
