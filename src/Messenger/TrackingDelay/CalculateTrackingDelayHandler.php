<?php

namespace App\Messenger\TrackingDelay;

use App\Messenger\LoggedHandler;
use App\Messenger\MessageInterface;
use App\Service\ExceptionLoggerService;
use App\Service\Tracking\PackService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: "async_tracking_delay")]
class CalculateTrackingDelayHandler extends LoggedHandler
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PackService            $packService,
        private ExceptionLoggerService $loggerService,
    ) {
        parent::__construct($this->loggerService);
    }

    public function __invoke(CalculateTrackingDelayMessage $message): void {
        $this->handle($message);
    }

    /**
     * @param CalculateTrackingDelayMessage $message Not typed in php to implement LoggedHandler
     */
    protected function process(MessageInterface $message): void {
        $success = $this->packService->updateTrackingDelayWithPackCode($this->entityManager, $message->getPackCode());
        if ($success) {
            $this->entityManager->flush();
        }
    }
}
