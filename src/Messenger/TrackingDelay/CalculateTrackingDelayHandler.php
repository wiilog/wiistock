<?php

namespace App\Messenger\TrackingDelay;

use App\Entity\Tracking\Pack;
use App\Messenger\LoggedHandler;
use App\Messenger\MessageInterface;
use App\Service\ExceptionLoggerService;
use App\Service\Tracking\TrackingDelayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CalculateTrackingDelayHandler extends LoggedHandler
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TrackingDelayService   $trackingDelayService,
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
        $packCode = $message->getPackCode();

        $packRepository = $this->entityManager->getRepository(Pack::class);
        $pack = $packRepository->findOneBy(["code" => $packCode]);

        $this->trackingDelayService->updatePackTrackingDelay($this->entityManager, $pack);
        $this->entityManager->flush();
    }
}
