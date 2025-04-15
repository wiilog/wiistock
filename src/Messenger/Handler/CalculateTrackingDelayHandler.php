<?php

namespace App\Messenger\Handler;

use App\Entity\Tracking\Pack;
use App\Messenger\Message\DeduplicatedMessage\WaitingDeduplicatedMessage\CalculateTrackingDelayMessage;
use App\Messenger\Message\MessageInterface;
use App\Service\ExceptionLoggerService;
use App\Service\Tracking\TrackingDelayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;


/**
 * Handle message to calculate tracking delay (CalculateTrackingDelayMessage)
 * We can't calculate tracking delay on same time for the same logistic unit.
 *
 * @see WaitingDeduplicatedHandler
 * @see CalculateTrackingDelayMessage
 */
#[AsMessageHandler(fromTransport: "async_tracking_delay")]
class CalculateTrackingDelayHandler extends WaitingDeduplicatedHandler {

    public function __construct(
        ExceptionLoggerService         $loggerService,
        MessageBusInterface            $messageBus,
        LockFactory                    $lockFactory,
        private EntityManagerInterface $entityManager,
        private TrackingDelayService   $trackingDelayService,
    ) {
        parent::__construct($loggerService, $messageBus, $lockFactory);
    }

    public function __invoke(CalculateTrackingDelayMessage $message): void {
        $this->handle($message);
    }

    /**
     * @param CalculateTrackingDelayMessage $message Not typed in php to implement LoggedHandler
     */
    protected function processWithLock(MessageInterface $message): void {
        $packCode = $message->getPackCode();

        $packRepository = $this->entityManager->getRepository(Pack::class);
        $pack = $packRepository->findOneBy(["code" => $packCode]);

        $this->trackingDelayService->updatePackTrackingDelay($this->entityManager, $pack);
        $this->entityManager->flush();
    }
}
