<?php

namespace App\EventListener;

use App\Exceptions\FormException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class FormExceptionListener {

    public function onKernelException(ExceptionEvent $event) {
        $throwable = $event->getThrowable();
        if ($throwable instanceof FormException) {
            $event->allowCustomResponseCode();
            $event->setResponse(new JsonResponse(
                [
                    "success" => false,
                    "msg" => $throwable->getMessage(),
                    "data" => $throwable->getData()
                ],
                $throwable->getCode() ?: Response::HTTP_OK
            ));
        }
    }

}
