<?php

namespace App\EventListener;

use App\Exceptions\FormException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class FormExceptionListener {

    public function onKernelException(ExceptionEvent $event) {
        if($event->getThrowable() instanceof FormException) {
            $event->allowCustomResponseCode();
            $event->setResponse(new JsonResponse([
                "success" => false,
                "msg" => $event->getThrowable()->getMessage(),
            ]));
        }
    }

}
