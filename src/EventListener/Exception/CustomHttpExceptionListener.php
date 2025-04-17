<?php

namespace App\EventListener\Exception;

use App\Exceptions\CustomHttpException\UnauthorizedSleepingStockException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Twig\Environment as Twig_Environment;


#[AsEventListener]
class CustomHttpExceptionListener {

    public function __construct(
        private Twig_Environment $twig,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void {
        $exception = $event->getThrowable();

        if (!($exception instanceof UnauthorizedSleepingStockException)) {
            return;
        }

        $template = match (get_class($event->getThrowable())) {
            UnauthorizedSleepingStockException::class => 'errors/unauthorized-sleeping-stock.html.twig',
            default => null,
        };

        if (!$template) {
            return;
        }

        $event->setResponse(new Response($this->twig->render($template)));
    }

}
