<?php

namespace App\Service;

use App\Entity\Pack;
use App\Entity\Tracking\TrackingDelay;
use App\Entity\Tracking\TrackingEvent;
use App\Entity\Tracking\TrackingMovement;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use JetBrains\PhpStorm\ArrayShape;

readonly class TrackingDelayService {

    public function __construct(private DateTimeService $dateTimeService) {}

    /**
     * @param array{force?: boolean, stopTime?: boolean} $options
     */
    public function persistTrackingDelay(EntityManagerInterface $entityManager,
                                         Pack                   $pack,
                                         array                  $options = []): void {

        $force = $options["force"] ?? false;
        $stopTime = $options["stopTime"] ?? false;

        $trackingDelay = $pack->getTrackingDelay();

//        if ($trackingDelay?->isTimeStopped() && !$force) {
//            return;
//        }

        $calculatedElapsedTime = $this->calculatePackElapsedTime($entityManager, $pack);

        if (!isset($calculatedElapsedTime)) {
            if (isset($trackingDelay)) {
                $pack->setTrackingDelay(null);
                $entityManager->remove($trackingDelay);
            }
        }
        else {
            if (!isset($trackingDelay)) {
                $trackingDelay = new TrackingDelay();
                $trackingDelay->setPack($pack);
            }

            $trackingDelay
                ->setElapsedTime($calculatedElapsedTime)
                ->setCalculatedAt(new DateTime());

            $entityManager->persist($trackingDelay);
        }

    }

    public function calculatePackElapsedTime(EntityManagerInterface $entityManager,
                                             Pack                   $pack): ?int {

        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);

        // In seconds
        $calculatedElapsedTime = null;

        // TODO WIIS-11846 getter in Pack
        // $isBasicUnit = $pack->isBasicUnit();
        $isBasicUnit = true;

         $natureTrackingDelay = $isBasicUnit
             ? $pack?->getNature()?->getTrackingDelay()
             : null;
        $packHasTrackingDelay = isset($natureTrackingDelay);

        if (!$packHasTrackingDelay) {
            return null;
        }

        [
            "timerStartedAt" => $timerStartedAt,
            "timerStoppedAt" => $timerStoppedAt,
        ] = $this->getTimerData($pack);

        if (isset($timerStartedAt)) {
            $calculationDate = new DateTime();
            $calculationDate2 = clone $calculationDate;

            /** @var Generator<TrackingMovement> $trackingEvents */
            $trackingEvents = $trackingMovementRepository->iterateEventTrackingMovementBetween($pack, $timerStartedAt, $timerStoppedAt);

            $firstTracking = $trackingEvents->current();

            if ($firstTracking) {
                $intervalStart = null;
                $intervalEnd = null;
                if ($firstTracking->getEvent() !== TrackingEvent::START) {
                    $intervalStart = clone $timerStartedAt;
                }

                // We define a start and a stop for calculate time in an interval
                // and we restart until the end of TrackingEvents array
                foreach ($trackingEvents as $trackingEvent) {
                    if ($intervalStart) {
                        $intervalEnd = clone $trackingEvent->getDatetime();
                    }
                    else {
                        if ($trackingEvent->getEvent() === TrackingEvent::STOP) {
                            break;
                        }
                        $intervalStart = clone $trackingEvent->getDatetime();
                    }

                    if ($intervalStart && $intervalEnd) {
                        $interval = $this->dateTimeService->getWorkedPeriodBetweenDates($entityManager, $intervalStart, $intervalEnd);
                        $calculationDate->add($interval);

                        $intervalStart = null;
                        $intervalEnd = null;

                        if ($trackingEvent->getEvent() === TrackingEvent::STOP) {
                            break;
                        }
                    }
                }

                if ($intervalStart && !$intervalEnd) {
                    $intervalEnd = $timerStoppedAt ? (clone $timerStoppedAt) : new DateTime("now");
                    $interval = $this->dateTimeService->getWorkedPeriodBetweenDates($entityManager, $intervalStart, $intervalEnd);
                    $calculationDate->add($interval);

                    $intervalStart = null;
                    $intervalEnd = null;
                }
            }
            else { // no movements
                $intervalStart = $timerStartedAt;
                $intervalEnd = $timerStoppedAt ?? new DateTime();

                $elapsedInterval = $this->dateTimeService->getWorkedPeriodBetweenDates($entityManager, $intervalStart, $intervalEnd);
                $calculationDate->add($elapsedInterval);

                $intervalStart = null;
                $intervalEnd = null;
            }

            $calculatedElapsedTime = $calculationDate->getTimestamp() - $calculationDate2->getTimestamp();
        }

        return $calculatedElapsedTime ?? null;
    }

    #[ArrayShape([
        "timerStartedAt" => DateTime::class|null,
        "timerStoppedAt" => DateTime::class|null,
    ])]
    private function getTimerData(Pack $pack): array {
        $lastStart = $pack->getLastStart();
        $lastStop = $pack->getLastStop();

        if ($lastStart) {
            $timerStartedAt = $lastStart->getDatetime();

            if ($lastStop
                && (
                    $timerStartedAt < $lastStop->getDatetime()
                    || (
                        $timerStartedAt == $lastStop->getDatetime()
                        && (
                            $lastStart->getOrderIndex() < $lastStop->getOrderIndex()
                            || ($lastStart->getOrderIndex() === $lastStop->getOrderIndex() && $lastStart->getId() < $lastStop->getId())
                        )
                    )
                )) {
                $timerStoppedAt = $lastStop->getDatetime();
            }
        }
        else {
            $arrival = $pack->getArrivage();
            $truckArrival = $arrival?->getTruckArrival();

            $arrivalCreatedAt = $arrival?->getDate();
            $truckArrivalCreatedAt = $truckArrival?->getCreationDate();

            $timerStartedAt = $truckArrivalCreatedAt
                ?: $arrivalCreatedAt
                ?: $pack->getFirstTracking();

            if ($timerStartedAt
                && $lastStop
                && $timerStartedAt <= $lastStop->getDatetime()) {
                $timerStoppedAt = $lastStop->getDatetime();
            }
        }

        return [
            "timerStartedAt" => $timerStartedAt ?? null,
            "timerStoppedAt" => $timerStoppedAt ?? null,
        ];
    }
}
