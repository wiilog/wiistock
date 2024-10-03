<?php

namespace App\Service;

use App\Entity\Pack;
use App\Entity\Tracking\TrackingDelay;
use App\Entity\Tracking\TrackingEvent;
use App\Entity\Tracking\TrackingMovement;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Generator;

class TrackingDelayService {

    public function __construct(
        private TrackingMovementService $trackingMovementService,
        private DateTimeService         $dateTimeService,
    ) {}

    /**
     * Persist a new TrackingDelay, update if the TrackingDelay exists or remove it for the given pack.
     *
     * A pack could have a TrackingDelay if it's a basic one (method Pack::isBasicUnit)
     * and if its nature has a trackingDelay (method Nature::getTrackingDelay).
     *
     * @see Pack::isBasicUnit()
     * @see Nature::getTrackingDelay()
     */
    public function updateTrackingDelay(EntityManagerInterface $entityManager,
                                        Pack                   $pack): void {

        $isBasicUnit = $pack->isBasicUnit();

        $natureTrackingDelay = $isBasicUnit
            ? $pack->getNature()?->getTrackingDelay()
            : null;
        $packHasTrackingDelay = isset($natureTrackingDelay);

        $trackingDelay = $pack->getTrackingDelay();

        if ($packHasTrackingDelay) {
            [
                "lastTrackingEvent" => $lastTrackingEvent,
                "elapsedTime" => $calculatedElapsedTime,
            ] = $this->calculatePackElapsedTime($entityManager, $pack);
        }

        if (!isset($calculatedElapsedTime)) {
            if (isset($trackingDelay)) {
                $pack->setTrackingDelay(null);
                $entityManager->remove($trackingDelay);
            }
        }
        else {
            $this->persistTrackingDelay($entityManager, $pack, $calculatedElapsedTime, $lastTrackingEvent ?? null);
        }
    }

    private function persistTrackingDelay(EntityManagerInterface $entityManager,
                                          Pack                   $pack,
                                          int                    $elapsedTime,
                                          ?TrackingEvent         $lastTrackingEvent): void {

        $trackingDelay = $pack->getTrackingDelay();
        if (!isset($trackingDelay)) {
            $trackingDelay = new TrackingDelay();
            $trackingDelay->setPack($pack);
        }

        $now = new DateTime();
        $treatmentRemainingSeconds = $pack->getNature()->getTrackingDelay() - $elapsedTime;
        $treatmentRemainingInterval = $this->dateTimeService->convertSecondsToDateInterval($treatmentRemainingSeconds);
        $limitTreatmentDate = $treatmentRemainingSeconds > 0
            ? $this->dateTimeService->addWorkedIntervalToDateTime($entityManager, $now, $treatmentRemainingInterval)
            : null;

        $trackingDelay
            ->setElapsedTime($elapsedTime)
            ->setCalculatedAt($now)
            ->setLastTrackingEvent($lastTrackingEvent)
            ->setLimitTreatmentDate($limitTreatmentDate ?: $trackingDelay->getLimitTreatmentDate());

        $entityManager->persist($trackingDelay);
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
                /** @var TrackingMovement $trackingEvent */
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

    /**
     * TODO WIIS-11974: add new function here
     * We calculate the new tracking delay only if the new trackingMovement is a picking or a drop and if
     *    - elapsed time was stopped and restart by the last movement,
     *    - Or the last movement was a START one.
     */
    public function shouldCalculateTrackingDelay(TrackingMovement $trackingMovement,
                                                 ?TrackingEvent    $previousTrackingEvent,
                                                 ?TrackingEvent    $nextTrackingEvent): bool {
        return (

            ($trackingMovement->isDrop() || $trackingMovement->isPicking())
         /*   && (
                ($previousTrackingEvent === TrackingEvent::PAUSE && $nextTrackingEvent !== TrackingEvent::PAUSE)
                || ($previousTrackingEvent !== TrackingEvent::PAUSE && $nextTrackingEvent)
            )*/
        );
    }


    /**
     * @return array{
     *     timerStartedAt?: DateTime,
     *     timerStoppedAt?: DateTime,
     * }
     */
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
                ?: $pack->getFirstTracking()?->getDatetime();

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