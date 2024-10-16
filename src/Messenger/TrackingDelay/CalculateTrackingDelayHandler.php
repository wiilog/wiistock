<?php

namespace App\Messenger\TrackingDelay;

use App\Entity\Pack;
use App\Service\ExceptionLoggerService;
use App\Service\TrackingDelayService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CalculateTrackingDelayHandler {

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TrackingDelayService   $trackingDelayService,
        private ExceptionLoggerService $loggerService,
    ) {}

    public function __invoke(CalculateTrackingDelayMessage $message): void {
        $packCode = $message->getPackCode();

        $packRepository = $this->entityManager->getRepository(Pack::class);
        $pack = $packRepository->findOneBy(["code" => $packCode]);

        throw new \RuntimeException('Tracking delay not implemented');
        try {
            $this->trackingDelayService->updateTrackingDelay($this->entityManager, $pack);
        }
        catch (Exception $error) {
            $this->loggerService->sendLog($error);
        }
        $this->entityManager->flush();
    }
}
