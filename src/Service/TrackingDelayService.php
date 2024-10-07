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
        $data = $this->calculateTrackingDelayData($entityManager, $pack);

        $lastTrackingEvent = $data['lastTrackingEvent'] ?? null;
        $limitTreatmentDate = $data['limitTreatmentDate'] ?? null;
        $calculatedElapsedTime = $data['elapsedTime'] ?? null;

        if (!isset($calculatedElapsedTime)) {
            $trackingDelay = $pack->getTrackingDelay();
            if (isset($trackingDelay)) {
                $pack->setTrackingDelay(null);
                $entityManager->remove($trackingDelay);
            }
        }
        else {
            $this->persistTrackingDelay(
                $entityManager,
                $pack,
                $calculatedElapsedTime,
                $lastTrackingEvent ?? null,
                $limitTreatmentDate ?? null
            );
        }
    }

    private function persistTrackingDelay(EntityManagerInterface $entityManager,
                                          Pack                   $pack,
                                          int                    $elapsedTime,
                                          ?TrackingEvent         $lastTrackingEvent,
                                          ?DateTime              $limitTreatmentDate): void {

        $trackingDelay = $pack->getTrackingDelay();
        if (!isset($trackingDelay)) {
            $trackingDelay = new TrackingDelay();
            $trackingDelay->setPack($pack);
        }

        $now = new DateTime();

        $trackingDelay
            ->setElapsedTime($elapsedTime)
            ->setCalculatedAt($now)
            ->setLastTrackingEvent($lastTrackingEvent)
            ->setLimitTreatmentDate($limitTreatmentDate);

        $entityManager->persist($trackingDelay);
    }

    /**
     * @return null|array{
     *     lastTrackingEvent?: TrackingEvent|null,
     *     limitTreatmentDate?: DateTime|null,
     *     elapsedTime?: int|null,
     * }
     */
    private function calculateTrackingDelayData(EntityManagerInterface $entityManager,
                                                Pack                   $pack): array|null {

        $isBasicUnit = $pack->isBasicUnit();

        $natureTrackingDelay = $isBasicUnit
            ? $pack->getNature()?->getTrackingDelay()
            : null;

        $packHasTrackingDelay = isset($natureTrackingDelay);

        if (!$packHasTrackingDelay) {
            return null;
        }

        [
            "timerStartedAt" => $timerStartedAt,
            "timerStoppedAt" => $timerStoppedAt,
        ] = $this->getTimerData($pack);

        // Store tracking event of the second movement of an interval
        // ==> To get the tracking event which finish the delay calculation
        $lastTrackingEvent = null;

        $lastStop = $pack->getLastStop();

        $packHasStopEvent = (
            isset($timerStartedAt)
            && $lastStop
            && $lastStop->getDatetime() > $timerStartedAt
        );

        if (!isset($timerStartedAt)
            || $packHasStopEvent) {
            return null;
        }

        // date for calculate sum of all found worked intervals
        $calculationDate = new DateTime();
        $calculationDate2 = clone $calculationDate;

        // begin limitTreatmentDate on date which start the timer
        // we will increment it with all worked intervals found (nature tracking delay as max)
        $remainingNatureDelay = $natureTrackingDelay;
        $limitTreatmentDate = clone $timerStartedAt;

        $segments = $this->iteratePackTrackingSegmentsBetween($entityManager, $pack, $timerStartedAt, $timerStoppedAt);

        foreach ($segments as $segment) {
            [
                "start" => $intervalStart,
                "end" => $intervalEnd,
            ] = $segment;

            $workedInterval = $this->dateTimeService->getWorkedPeriodBetweenDates($entityManager, $intervalStart, $intervalEnd);
            $calculationDate->add($workedInterval);

            // increment limit treatment date while nature tracking delay is positive
            if ($remainingNatureDelay > 0) {
                $elapsedSeconds = floor($this->dateTimeService->convertDateIntervalToMilliseconds($workedInterval) / 1000);

                if ($remainingNatureDelay > $elapsedSeconds) {
                    $remainingNatureDelay -= $elapsedSeconds;
                    $limitTreatmentDate->add($workedInterval);
                }
                else {
                    $remainingDelay = $this->dateTimeService->convertSecondsToDateInterval($remainingNatureDelay);
                    $limitTreatmentDate->add($remainingDelay);
                    $remainingNatureDelay = 0;
                }
            }
        }

        // If the pack tracking delay is positive, then the limit treatment date is in the future
        // We calculate it now
        // Else the limit treatment date is already the final one
        $calculatedElapsedTime = $calculationDate->getTimestamp() - $calculationDate2->getTimestamp();
        $remainingNatureDelay = $natureTrackingDelay - $calculatedElapsedTime;
        if ($remainingNatureDelay > 0) {
            $remainingNatureDelayInterval = $this->dateTimeService->convertSecondsToDateInterval($remainingNatureDelay);
            $limitTreatmentDate = $this->dateTimeService->addWorkedIntervalToDateTime($entityManager, $limitTreatmentDate, $remainingNatureDelayInterval);
        }

        return [
            "elapsedTime" => $calculatedElapsedTime ?? null,
            "limitTreatmentDate" => $limitTreatmentDate ?? null,
            "lastTrackingEvent" => $lastTrackingEvent,
        ];
    }

    /**
     * Set of all tracking segments between two dates.
     * We get list of tracking movement which affect tracking elapsed time of a Pack.
     * Then we map it as date an ordered.
     * Each couple dates make this "tracking segment".
     *
     * @return Generator<array{
     *     start: DateTime,
     *     end: DateTime
     * }>
     */
    private function iteratePackTrackingSegmentsBetween(EntityManagerInterface $entityManager,
                                                        Pack                   $pack,
                                                        DateTime               $start,
                                                        ?DateTime              $end): iterable {

        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);

        $trackingEvents = $trackingMovementRepository->iterateEventTrackingMovementBetween($pack, $start, $end);

        $firstTracking = $trackingEvents->current();

        if ($firstTracking) {
            $intervalStart = null;
            $intervalEnd = null;
            if ($firstTracking->getEvent() !== TrackingEvent::START) {
                $intervalStart = clone $start;
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
                    yield [
                        "start" => $intervalStart,
                        "end" => $intervalEnd,
                    ];

                    $intervalStart = null;
                    $intervalEnd = null;

                    $lastTrackingEvent = $trackingEvent->getEvent();

                    if ($lastTrackingEvent === TrackingEvent::STOP) {
                        break;
                    }
                }
            }

            if ($intervalStart && !$intervalEnd) {
                $intervalEnd = $end ? (clone $end) : new DateTime("now");
                yield [
                    "start" => $intervalStart,
                    "end" => $intervalEnd,
                ];

                $intervalStart = null;
                $intervalEnd = null;
            }
        }
        else { // no movements
            $intervalStart = $start;
            $intervalEnd = $end ?? new DateTime();

            yield [
                "start" => $intervalStart,
                "end" => $intervalEnd,
            ];

            $intervalStart = null;
            $intervalEnd = null;
        }
    }

    /**
     * TODO WIIS-11974: add new function here
     * We calculate the new tracking delay only if the new trackingMovement is a picking or a drop and if
     *    - elapsed time was stopped and restart by the last movement,
     *    - Or the last movement was a START one.
     */
    public function shouldCalculateTrackingDelay(Pack           $pack,
                                                 ?TrackingEvent $previousTrackingEvent,
                                                 ?TrackingEvent $nextTrackingEvent): bool {
        return (
            true
            /*$pack
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
                ?: $pack->getFirstAction()?->getDatetime();

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
