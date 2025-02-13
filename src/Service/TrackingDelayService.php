<?php

namespace App\Service;

use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\Nature;
use App\Entity\Statut;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingDelay;
use App\Entity\Tracking\TrackingDelayRecord;
use App\Entity\Tracking\TrackingEvent;
use App\Entity\Tracking\TrackingMovement;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;


class TrackingDelayService {

    public function __construct(
        private TrackingMovementService $trackingMovementService,
        private DateTimeService         $dateTimeService,
        private CacheService            $cacheService,
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
        $calculatedDelayInheritCurrent = $data['calculatedDelayInheritCurrent'] ?? null;
        $calculatedElapsedTime = $data['elapsedTime'] ?? null;
        $records = $data['records'] ?? [];

        if (!isset($calculatedElapsedTime)) {
            $pack->getCurrentTrackingDelay()
                ?->setStoppedByMovementEdit(true);
        }
        else {
            $trackingDelay = $pack->getCurrentTrackingDelay();
            if ($calculatedDelayInheritCurrent && $trackingDelay) {
                $trackingDelay->addRecords($records);
            }
            else {
                $trackingDelay = $this->createTrackingDelay(
                    $pack,
                    $calculatedElapsedTime,
                    $lastTrackingEvent,
                    $limitTreatmentDate,
                    $records
                );

                $pack->setLastTrackingDelay($trackingDelay);
                $entityManager->persist($trackingDelay);
            }
        }
    }

    /**
     * @param TrackingDelayRecord[] $records
     */
    private function createTrackingDelay(Pack           $pack,
                                         int            $elapsedTime,
                                         ?TrackingEvent $lastTrackingEvent,
                                         ?DateTime      $limitTreatmentDate,
                                         array          $records): TrackingDelay {
        $now = new DateTime();

        $trackingDelay = new TrackingDelay();
        $trackingDelay
            ->setPack($pack)
            ->setElapsedTime($elapsedTime)
            ->setCalculatedAt($now)
            ->setLastTrackingEvent($lastTrackingEvent)
            ->setLimitTreatmentDate($limitTreatmentDate)
            ->setRecords($records);

        return $trackingDelay;
    }

    /**
     * Return array of data calculated in the function:
     *  - The elapsed time of the pack to be treated,
     *  - the limit treatment date of the pack according to its nature
     *  - lastTrackingEvent: the event of the last movement on the pack
     *
     * @return null|array{
     *     lastTrackingEvent?: TrackingEvent|null,
     *     limitTreatmentDate?: DateTime|null,
     *     elapsedTime?: int|null,
     * }
     */
    private function calculateTrackingDelayData(EntityManagerInterface $entityManager,
                                                Pack                   $pack): array|null {
        if (!$pack->shouldHaveTrackingDelay()) {
            return null;
        }

        [
            "timerStartedAt" => $timerStartedAt,
            "timerStoppedAt" => $timerStoppedAt,
            "timerStartedBy" => $timerStartedBy,
        ] = $this->getTimerData($pack);

        // Store tracking event of the second movement of an interval
        // ==> To get the tracking event which finish the delay calculation
        $lastTrackingEvent = null;
        $calculatedRecords = [];

        if (!isset($timerStartedAt)) {
            return null;
        }

        // date for calculate sum of all found worked intervals
        $calculationDate = new DateTime();
        $calculationDateInit = clone $calculationDate;

        // begin limitTreatmentDate on date which start the timer
        // we will increment it with all worked intervals found (nature tracking delay as max)
        $natureTrackingDelay = $pack->getNature()?->getTrackingDelay();
        $remainingNatureDelay = $natureTrackingDelay;
        $limitTreatmentDate = clone $timerStartedAt;

        $segments = $this->iteratePackTrackingDelaySegmentsBetween(
            $entityManager,
            $pack,
            $timerStartedAt,
            $timerStoppedAt,
            $timerStartedBy
        );

        $previousRecords = $pack->getCurrentTrackingDelay()?->getRecords()?->toArray() ?: [];
        $previousRecordsCursor = -1;
        $calculatedDelayInheritCurrent = !empty($previousRecords);
        $previousCurrentRecord = null;

        $getRemainingDelay = static function (int      $delay,
                                              DateTime $begin,
                                              DateTime $end) {
            $intervalTime = $end->getTimestamp() - $begin->getTimestamp();
            return [
                "delay"        => $delay - $intervalTime,
                "intervalTime" => $intervalTime,
            ];
        };

        foreach ($segments as $segment) {
            [
                "start" => $segmentStart,
                "end" => $segmentEnd,
            ] = $segment;

            $lastTrackingEvent = $segmentEnd?->getTrackingEvent();

            $workedInterval = $this->dateTimeService->getWorkedPeriodBetweenDates($entityManager, $segmentStart->getDate(), $segmentEnd->getDate());
            $calculationDate->add($workedInterval);

            $oldRemainingNatureDelay = $remainingNatureDelay ?? $natureTrackingDelay;
            ["delay" => $remainingNatureDelay] = $getRemainingDelay($natureTrackingDelay, $calculationDateInit, $calculationDate);

            // set remaining time before check push records in calculated records array
            $segmentStart->setRemainingTrackingDelay($oldRemainingNatureDelay);
            $segmentEnd->setRemainingTrackingDelay($remainingNatureDelay);

            $this->pushTrackingDelayRecord($calculatedRecords, $segmentStart, $calculatedDelayInheritCurrent, $previousRecords, $previousRecordsCursor, $previousCurrentRecord);
            $this->pushTrackingDelayRecord($calculatedRecords, $segmentEnd, $calculatedDelayInheritCurrent, $previousRecords, $previousRecordsCursor, $previousCurrentRecord);

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
        [
            "delay"        => $remainingNatureDelay,
            "intervalTime" => $calculatedElapsedTime,
        ] = $getRemainingDelay($natureTrackingDelay, $calculationDateInit, $calculationDate);

        if ($remainingNatureDelay > 0) {
            $remainingNatureDelayInterval = $this->dateTimeService->convertSecondsToDateInterval($remainingNatureDelay);
            $limitTreatmentDate = $this->dateTimeService->addWorkedPeriodToDateTime($entityManager, $limitTreatmentDate, $remainingNatureDelayInterval);
        }

        return [
            "elapsedTime" => $calculatedElapsedTime ?? null,
            "limitTreatmentDate" => $limitTreatmentDate ?? null,
            "lastTrackingEvent" => $lastTrackingEvent,
            "calculatedDelayInheritCurrent" => $calculatedDelayInheritCurrent,
            "records" => $calculatedRecords,
        ];
    }

    /**
     * Set of all tracking segments between two dates.
     * We get list of tracking movement which affect tracking elapsed time of a Pack.
     * Then we map it as date an ordered.
     * Each couple dates make this "tracking segment".
     *
     * @param "movement"|"arrival"|"truckArrival"|null $startedBy
     *
     * @return iterable<array{
     *     start: TrackingDelayRecord,
     *     end: TrackingDelayRecord
     * }>
     */
    private function iteratePackTrackingDelaySegmentsBetween(EntityManagerInterface $entityManager,
                                                             Pack                   $pack,
                                                             DateTime               $start,
                                                             ?DateTime              $end,
                                                             ?string                $startedBy): iterable {

        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);

        $trackingEvents = $trackingMovementRepository->iterateEventTrackingMovementBetween($pack, $start, $end);

        $firstTracking = $trackingEvents->current();

        if ($firstTracking) {
            /** @var TrackingDelayRecord|null $intervalStartRecord */
            $intervalStartRecord = null;

            /** @var TrackingDelayRecord|null $intervalEndRecord */
            $intervalEndRecord = null;

            if ($firstTracking->getEvent() !== TrackingEvent::START) {
                $intervalStartRecord = $this->createTrackingDelayRecord([
                    "date" => $start,
                    "type" => match($startedBy) {
                        "arrival"      => $this->cacheService->getEntity($entityManager, Statut::class, CategorieStatut::class, TrackingDelayRecord::TYPE_ARRIVAL),
                        "truckArrival" => $this->cacheService->getEntity($entityManager, Statut::class, CategorieStatut::class, TrackingDelayRecord::TYPE_TRUCK_ARRIVAL),
                        default        => null,
                    },
                ]);
            }

            // We define a start and a stop for calculate time in an interval,
            // and we restart until the end of TrackingEvents array
            /** @var TrackingMovement $trackingEvent */
            foreach ($trackingEvents as $trackingEvent) {
                $lastTrackingEvent = null;
                if ($intervalStartRecord) {
                    $intervalEndRecord = $this->createTrackingDelayRecord($trackingEvent);
                }
                else {
                    if ($trackingEvent->getEvent() === TrackingEvent::STOP) {
                        break;
                    }
                    $intervalStartRecord = $this->createTrackingDelayRecord($trackingEvent);
                }

                if ($intervalStartRecord && $intervalEndRecord) {
                    $lastTrackingEvent = $trackingEvent->getEvent();
                    yield [
                        "start" => $intervalStartRecord,
                        "end"   => $intervalEndRecord,
                    ];

                    $intervalStartRecord = null;
                    $intervalEndRecord = null;

                    if ($lastTrackingEvent === TrackingEvent::STOP) {
                        break;
                    }
                }
            }

            if ($intervalStartRecord && !$intervalEndRecord) {
                $calculateDelayUntilNow = !isset($end);

                $intervalEndRecord = $this->createTrackingDelayRecord([
                    "date" => !$calculateDelayUntilNow ? $end : new DateTime("now"),
                    "now"  => $calculateDelayUntilNow,
                ]);

                yield [
                    "start" => $intervalStartRecord,
                    "end"   => $intervalEndRecord,
                ];

                $intervalStartRecord = null;
                $intervalEndRecord = null;
            }
        }
        else { // no movements
            $calculateDelayUntilNow = !isset($end);
            $intervalStartRecord = $this->createTrackingDelayRecord([
                "date" => $start,
            ]);
            $intervalEndRecord = $this->createTrackingDelayRecord([
                "date" => !$calculateDelayUntilNow ? $end : new DateTime("now"),
                "now"  => $calculateDelayUntilNow,
            ]);

            yield [
                "start" => $intervalStartRecord,
                "end"   => $intervalEndRecord,
            ];

            $intervalStartRecord = null;
            $intervalEndRecord = null;
        }
    }

    /**
     * We calculate the new tracking delay only if
     *    - the tracking pack should have a tracking delay
     *    - AND the new trackingMovement is a picking or a drop
     *    - AND the elapsed time was stopped and restart by the last movement OR the last movement was a START one.
     */
    public function shouldCalculateTrackingDelay(TrackingMovement $tracking): bool {
        $nextType = $tracking->calculateTrackingDelayData["nextType"] ?? null;
        $previousTrackingEvent = $tracking->calculateTrackingDelayData["previousTrackingEvent"] ?? null;
        $nextTrackingEvent = $tracking->calculateTrackingDelayData["nextTrackingEvent"] ?? null;
        return (
            $tracking->getPack()?->shouldHaveTrackingDelay()
            && in_array($nextType, [TrackingMovement::TYPE_PRISE, TrackingMovement::TYPE_DEPOSE])
            && (
                ($previousTrackingEvent === TrackingEvent::PAUSE && $nextTrackingEvent !== TrackingEvent::PAUSE)
                || ($previousTrackingEvent !== TrackingEvent::PAUSE && $nextTrackingEvent)
            )
        );
    }


    /**
     * @return array{
     *     timerStartedAt?: DateTime,
     *     timerStartedBy?: "movement"|"arrival"|"truckArrival",
     *     timerStoppedAt?: DateTime,
     *     timerStoppedBy?: "movement"|"arrival"|"truckArrival",
     * }
     */
    private function getTimerData(Pack $pack): array {
        $lastStart = $pack->getLastStart();
        $lastStop = $pack->getLastStop();

        if ($lastStart) {
            $timerStartedAt = $lastStart->getDatetime();

            if ($lastStop
                && $this->trackingMovementService->compareMovements($lastStart, $lastStop) === TrackingMovementService::COMPARE_A_BEFORE_B) {
                $timerStoppedAt = $lastStop->getDatetime();
            }
        }
        else {
            $arrival = $pack->getArrivage();
            $truckArrival = $arrival?->getTruckArrival();

            $arrivalCreatedAt = $arrival?->getDate();
            $truckArrivalCreatedAt = $truckArrival?->getCreationDate();

            $timerStartedAt = $truckArrivalCreatedAt ?: $arrivalCreatedAt;

            if ($timerStartedAt) {
                $timerStartedBy = $truckArrivalCreatedAt ? "arrival" : "truckArrival";
            }
            else {
                // if any truck arrival or logistic unit arrival
                // we get the first tracking movement on pack

                $firstAction = $pack->getFirstAction();
                // if the first tracking was not on a stop location (like a picking on stop location)
                // we get as timerStartedAt the date of the tracking
                // because we know that the pack has never been pick and that the timer has never been started
                if (!($firstAction?->getEmplacement()?->isStopTrackingTimerOnDrop())) {
                    $timerStartedAt = $firstAction?->getDatetime();
                }
            }

            if ($timerStartedAt
                && $lastStop
                && $timerStartedAt <= $lastStop->getDatetime()) {
                $timerStoppedAt = $lastStop->getDatetime();
            }
        }

        return [
            "timerStartedAt" => $timerStartedAt ?? null,
            "timerStartedBy" => $timerStartedBy ?? "movement",
            "timerStoppedAt" => $timerStoppedAt ?? null,
            "timerStoppedBy" => $timerStoppedBy ?? "movement",
        ];
    }

    /**
     * @param TrackingMovement|array{
     *     pack?: Pack,
     *     location?: Emplacement,
     *     trackingEvent?: TrackingEvent,
     *     type?: Statut|string,
     *     date: DateTime,
     *     newNature?: Nature,
     *     now?: bool,
     * } $tracking
     * @return TrackingDelayRecord
     */
    private function createTrackingDelayRecord(TrackingMovement|array $tracking): TrackingDelayRecord {
        /** @var array{
         *      pack?: Pack,
         *      location?: Emplacement,
         *      trackingEvent?: TrackingEvent,
         *      type?: Statut,
         *      date: DateTime,
         *      newNature?: Nature,
         *      now?: bool,
         * } $recordArray */

        if ($tracking instanceof TrackingMovement) {
            $recordArray = [
                "pack" => $tracking->getPack(),
                "location" => $tracking->getEmplacement(),
                "trackingEvent" => $tracking->getEvent(),
                "date" => $tracking->getDatetime(),
                "type" => $tracking->getType(),
                "newNature" => $tracking->getNewNature(),
            ];
        }
        else {
            $recordArray = $tracking;
        }

        return (new TrackingDelayRecord())
            ->setLocation($recordArray["location"] ?? null)
            ->setPack($recordArray["pack"] ?? null)
            ->setNewNature($recordArray["newNature"] ?? null)
            ->setType($recordArray["type"] ?? null)
            ->setTrackingEvent($recordArray["trackingEvent"] ?? null)
            ->setDate($recordArray["date"] ?? null)
            ->setNow($recordArray["now"] ?? false);
    }

    /**
     * @param TrackingDelayRecord[] $records
     */
    private function pushTrackingDelayRecord(array                &$records,
                                             TrackingDelayRecord  $record,
                                             bool                 &$calculatedDelayInheritCurrent,
                                             array                  $previousRecords,
                                             int                &$previousRecordsCursor,
                                             ?TrackingDelayRecord &$previousRecord): void {

        $previousRecord = $calculatedDelayInheritCurrent && $previousRecord
            ? ($previousRecords[++$previousRecordsCursor] ?? null)
            : null;

        if (!$record->isNow()) {
            if ($calculatedDelayInheritCurrent
                && $previousRecord
                && !$record->equals($previousRecord)) {
                $calculatedDelayInheritCurrent = false;
            }

            if (!$calculatedDelayInheritCurrent
                || !$previousRecord) {
                $records[] = $record;
            }
        }
    }
}
