<?php

namespace App\EventListener;

use App\Exceptions\FormException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class FormExceptionListener {

    public function onKernelException(ExceptionEvent $event) {
        $exception = $event->getThrowable();
        if ($exception instanceof FormException) {
            $message = $exception->getMessage();
            $data = $exception->getData();
        }
        else {
            return;
        }

        $event->allowCustomResponseCode();
        $event->setResponse(new JsonResponse([
            "success" => false,
            "msg" => $message,
            "data" => $data ?? null,
        ], $exception->getCode() ?: Response::HTTP_OK));
    }

}
