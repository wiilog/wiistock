<?php

namespace App\EventListener\Exception;

use App\Exceptions\FormException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

#[AsEventListener(priority: 1)]
class FormExceptionListener {

    public function __invoke(ExceptionEvent $event): void {
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
            "message" => $message,
            "data" => $data ?? null,
        ], $exception->getCode() ?: Response::HTTP_OK));
    }

}
