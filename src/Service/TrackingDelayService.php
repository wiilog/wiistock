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
use Symfony\Contracts\Service\Attribute\Required;

class TrackingDelayService {

    #[Required]
    public TrackingMovementService $trackingMovementService;

    #[Required]
    public DateTimeService $dateTimeService;

    /**
     * @param array{force?: boolean} $options
     */
    public function persistTrackingDelay(EntityManagerInterface $entityManager,
                                         Pack                   $pack,
                                         array                  $options = []): void {

        $force = $options["force"] ?? false;

        $trackingDelay = $pack->getTrackingDelay();

        if (!($trackingDelay?->canRecalculateOnNewTracking())
            && !$force) {
            return;
        }



        [
            "lastTrackingEvent" => $lastTrackingEvent,
            "elapsedTime" => $calculatedElapsedTime,
        ] = $this->calculatePackElapsedTime($entityManager, $pack);

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

            $now = new DateTime();
            $treatmentRemainingSeconds = $pack->getNature()->getTrackingDelay() - $calculatedElapsedTime;
            $treatmentRemainingInterval = $this->dateTimeService->convertSecondsToDateInterval($treatmentRemainingSeconds);
            $limitTreatmentDate = $treatmentRemainingSeconds > 0
                ? $this->dateTimeService->addWorkedIntervalToDateTime($entityManager, $now, $treatmentRemainingInterval)
                : null;

            $trackingDelay
                ->setElapsedTime($calculatedElapsedTime)
                ->setCalculatedAt($now)
                ->setLastTrackingEvent($lastTrackingEvent)
                ->setLimitTreatmentDate($limitTreatmentDate ?: $trackingDelay->getLimitTreatmentDate());

            $entityManager->persist($trackingDelay);
        }

    }

    /**
     * @return array{
     *     lastTrackingEvent: TrackingEvent|null,
     *     elapsedTime: int|null,
     * }
     */
    public function calculatePackElapsedTime(EntityManagerInterface $entityManager,
                                             Pack                   $pack): array {

        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);

        // In seconds
        $calculatedElapsedTime = null;

        $isBasicUnit = $pack->isBasicUnit();

         $natureTrackingDelay = $isBasicUnit
             ? $pack?->getNature()?->getTrackingDelay()
             : null;
        $packHasTrackingDelay = isset($natureTrackingDelay);

        if (!$packHasTrackingDelay) {
            return [
                "elapsedTime" => null,
                "lastTrackingEvent" => null,
            ];
        }

        [
            "timerStartedAt" => $timerStartedAt,
            "timerStoppedAt" => $timerStoppedAt,
        ] = $this->getTimerData($pack);

        // Store tracking event of the second movement of an interval
        // ==> To get the tracking event which finish the delay calculation
        $lastTrackingEvent = null;

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

                // We define a start and a stop for calculate time in an interval,
                // and we restart until the end of TrackingEvents array
                foreach ($trackingEvents as $trackingEvent) {
                    $lastTrackingEvent = null;
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

                        $lastTrackingEvent = $trackingEvent->getEvent();

                        if ($lastTrackingEvent === TrackingEvent::STOP) {
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

        return [
            "elapsedTime" => $calculatedElapsedTime ?? null,
            "lastTrackingEvent" => $lastTrackingEvent,
        ];
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
                && $this->trackingMovementService->compareMovements($lastStart, $lastStop) === -1) {
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
