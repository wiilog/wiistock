<?php

namespace App\EventListener\Exception;

use App\Exceptions\FormException;
use App\Service\ExceptionLoggerService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsEventListener(priority: 2)]
class ExceptionLoggerListener {

    public function __construct(
        private ExceptionLoggerService $exceptionLoggerService,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void {
        if ($event->getThrowable() instanceof FormException
            || $event->getThrowable() instanceof AccessDeniedException) {
            return;
        }

        $this->exceptionLoggerService->sendLog($event->getThrowable());
    }

}
