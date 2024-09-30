<?php

namespace App\Messenger\TrackingDelay;

use App\Entity\Pack;
use App\Service\TrackingDelayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CalculateTrackingDelayHandler {

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TrackingDelayService   $trackingDelayService,
    ) {}

    public function __invoke(CalculateTrackingDelayMessage $message): void {
        $packCode = $message->getPackCode();

        $packRepository = $this->entityManager->getRepository(Pack::class);
        $pack = $packRepository->findOneBy(["code" => $packCode]);

        $this->trackingDelayService->updateTrackingDelay($this->entityManager, $pack);

        $this->entityManager->flush();
    }
}
