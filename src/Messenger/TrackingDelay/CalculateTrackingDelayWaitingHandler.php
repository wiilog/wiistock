<?php

namespace App\Messenger\TrackingDelay;

use App\Messenger\LoggedHandler;
use App\Messenger\MessageInterface;
use App\Service\ExceptionLoggerService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\UniqueWaitingMessage;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(fromTransport: "async_tracking_delay_waiting")]
class CalculateTrackingDelayWaitingHandler extends LoggedHandler
{

    public const MAX_WAITING_TIMES = 4;

    // seconds
    public const WAITING_PERIOD = 3;

    public function __construct(
        private MessageBusInterface    $messageBus,
        private ExceptionLoggerService $loggerService,
    ) {
        parent::__construct($this->loggerService);
    }

    public function __invoke(CalculateTrackingDelayMessage $message): void {
        $this->handle($message);
    }

    /**
     * @param UniqueWaitingMessage $message Not typed in php to implement LoggedHandler
     */
    protected function process(MessageInterface $message): void {
        sleep(self::WAITING_PERIOD);
        $message->incrementWaitingTimes();
        if ($message->getWaitingTimes() <= self::MAX_WAITING_TIMES) {
            $this->messageBus->dispatch($message);
        }
    }
}
