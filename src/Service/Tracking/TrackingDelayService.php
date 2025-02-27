<?php

namespace App\Service\Tracking;

use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\Nature;
use App\Entity\Statut;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingDelay;
use App\Entity\Tracking\TrackingDelayRecord;
use App\Entity\Tracking\TrackingEvent;
use App\Entity\Tracking\TrackingMovement;
use App\Service\CacheService;
use App\Service\DateTimeService;
use DateTime;
use Doctrine\Common\Collections\Order;
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
    public function updatePackTrackingDelay(EntityManagerInterface $entityManager,
                                            Pack                   $pack): void {
        $data = $this->calculateTrackingDelayData($entityManager, $pack);

        $lastTrackingEvent = $data['lastTrackingEvent'] ?? null;
        $limitTreatmentDate = $data['limitTreatmentDate'] ?? null;
        $affectedTrackingDelay = $data['affectedTrackingDelay'] ?? null;
        $calculatedElapsedTime = $data['elapsedTime'] ?? null;
        $records = $data['records'] ?? [];

        if (!isset($calculatedElapsedTime)) {
            $pack->setCurrentTrackingDelay(null);
        }
        else {
            if (!$affectedTrackingDelay) {
                $trackingDelay = new TrackingDelay();
                $trackingDelay->setPack($pack);

                $entityManager->persist($trackingDelay);
            }
            else {
                $trackingDelay = $affectedTrackingDelay;
            }

            $this->updateTrackingDelay(
                $trackingDelay,
                $calculatedElapsedTime,
                $lastTrackingEvent,
                $limitTreatmentDate,
                $records,
                !$affectedTrackingDelay
            );

            // after updateTrackingDelay
            $pack->setCurrentTrackingDelay(
                $trackingDelay->getLastTrackingEvent() !== TrackingEvent::STOP
                    ? $trackingDelay
                    : null
            );
        }
    }

    /**
     * @param TrackingDelayRecord[] $records
     */
    private function updateTrackingDelay(TrackingDelay  $trackingDelay,
                                         int            $elapsedTime,
                                         ?TrackingEvent $lastTrackingEvent,
                                         ?DateTime      $limitTreatmentDate,
                                         array          $records,
                                         bool           $replaceRecords = true): void {
        $now = new DateTime();

        $trackingDelay
            ->setElapsedTime($elapsedTime)
            ->setCalculatedAt($now)
            ->setLastTrackingEvent($lastTrackingEvent)
            ->setLimitTreatmentDate($limitTreatmentDate);

        if ($replaceRecords) {
            $trackingDelay->setRecords($records);
        }
        else {
            $trackingDelay->addRecords($records);
        }
    }

    /**
     * Return array of data calculated in the function:
     *  - The elapsed time of the pack to be treated,
     *  - the limit treatment date of the pack according to its nature
     *  - lastTrackingEvent: the event of the last movement on the pack
     *  - calculatedDelayInheritCurrent: If TRUE then records array contains records in addition
     *    of the records already existing in database. If FALSE then the calculated elapsedTime can replace existing one in database
     *    and records array contains some record which can replace some records already existing in database
     *  - records: Records representing the returned elapsedTime
     *
     * @return null|array{
     *     lastTrackingEvent: TrackingEvent|null,
     *     limitTreatmentDate: DateTime|null,
     *     elapsedTime: int|null,
     *     calculatedDelayInheritCurrent: boolean,
     *     records: TrackingDelayRecord[],
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

        $trackingDelayRepository = $entityManager->getRepository(TrackingDelay::class);

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

        $lastTrackingDelay = $trackingDelayRepository->findOneBy(
            ["pack" => $pack],
            ["calculatedAt" => Order::Descending->value, "id" => Order::Descending->value]
        );

        $previousRecords = $lastTrackingDelay?->getRecords()?->toArray() ?: [];
        $previousRecordsCursor = -1;
        $calculatedDelayInheritLast = true;
        $previousCurrentRecord = null;

        foreach ($segments as $segment) {
            [
                "start" => $segmentStart,
                "end" => $segmentEnd,
            ] = $segment;

            $lastTrackingEvent = $segmentEnd?->getTrackingEvent();

            $workedInterval = $this->dateTimeService->getWorkedPeriodBetweenDates($entityManager, $segmentStart->getDate(), $segmentEnd->getDate());
            $calculationDate->add($workedInterval);

            $oldRemainingNatureDelay = $remainingNatureDelay ?? $natureTrackingDelay;
            ["delay" => $remainingNatureDelay] = $this->dateTimeService->subtractDelay($natureTrackingDelay, $calculationDateInit, $calculationDate);

            // set remaining time before check push records in calculated records array
            $segmentStart->setRemainingTrackingDelay($oldRemainingNatureDelay);
            $segmentEnd->setRemainingTrackingDelay($remainingNatureDelay);

            $this->pushTrackingDelayRecord($calculatedRecords, $segmentStart, $calculatedDelayInheritLast, $previousRecords, $previousRecordsCursor, $previousCurrentRecord);
            $this->pushTrackingDelayRecord($calculatedRecords, $segmentEnd, $calculatedDelayInheritLast, $previousRecords, $previousRecordsCursor, $previousCurrentRecord);

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
        ] = $this->dateTimeService->subtractDelay($natureTrackingDelay, $calculationDateInit, $calculationDate);

        if ($remainingNatureDelay > 0) {
            $remainingNatureDelayInterval = $this->dateTimeService->convertSecondsToDateInterval($remainingNatureDelay);
            $limitTreatmentDate = $this->dateTimeService->addWorkedPeriodToDateTime($entityManager, $limitTreatmentDate, $remainingNatureDelayInterval);
        }

        return [
            "elapsedTime" => $calculatedElapsedTime ?? null,
            "limitTreatmentDate" => $limitTreatmentDate ?? null,
            "lastTrackingEvent" => $lastTrackingEvent,
            "affectedTrackingDelay" => $calculatedDelayInheritLast
                ? $lastTrackingDelay
                : null,
            "records" => $calculatedRecords,
        ];
    }

    /**
     * Set of all tracking segments between two dates.
     * We get list of tracking movement which affect tracking elapsed time of a Pack.
     * Then we map it as date an ordered.
     * Each couple dates make this "tracking segment".
     *
     * @param TrackingTimerEvent|null $startedBy
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
                                                             ?TrackingTimerEvent    $startedBy): iterable {

        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);

        $trackingEvents = $trackingMovementRepository->iterateEventTrackingMovementBetween($pack, $start, $end);

        $firstTracking = $trackingEvents->current();

        $firstRecordType = match($startedBy) {
            TrackingTimerEvent::ARRIVAL       => $this->cacheService->getEntity($entityManager, Statut::class, CategorieStatut::TRACKING_DELAY_RECORD, TrackingDelayRecord::TYPE_ARRIVAL),
            TrackingTimerEvent::TRUCK_ARRIVAL => $this->cacheService->getEntity($entityManager, Statut::class, CategorieStatut::TRACKING_DELAY_RECORD, TrackingDelayRecord::TYPE_TRUCK_ARRIVAL),
            default                           => null,
        };

        if ($firstTracking) {
            /** @var TrackingDelayRecord|null $intervalStartRecord */
            $intervalStartRecord = null;

            /** @var TrackingDelayRecord|null $intervalEndRecord */
            $intervalEndRecord = null;

            if ($firstTracking->getEvent() !== TrackingEvent::START) {
                $intervalStartRecord = $this->createTrackingDelayRecord([
                    "date" => $start,
                    "type" => $firstRecordType
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
        else { // no tracking movement which edit the delay
            $calculateDelayUntilNow = !isset($end);
            $intervalStartRecord = $this->createTrackingDelayRecord([
                "date" => $start,
                "type" => $firstRecordType
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
     *     timerStartedAt: DateTime|null,
     *     timerStartedBy: TrackingTimerEvent|null,
     *     timerStoppedAt: DateTime|null,
     *     timerStoppedBy: TrackingTimerEvent|null,
     * }
     */
    private function getTimerData(Pack $pack): array {
        $lastStart = $pack->getLastStart();
        $lastStop = $pack->getLastStop();

        if ($lastStart) {
            $timerStartedAt = $lastStart->getDatetime();
            $timerStartedBy = TrackingTimerEvent::MOVEMENT;

            if ($lastStop
                && $this->trackingMovementService->compareMovements($lastStart, $lastStop) === TrackingMovementService::COMPARE_A_BEFORE_B) {
                $timerStoppedAt = $lastStop->getDatetime();
                $timerStoppedBy = TrackingTimerEvent::MOVEMENT;
            }
        }
        else {
            $arrival = $pack->getArrivage();
            $truckArrival = $arrival?->getTruckArrival();

            $arrivalCreatedAt = $arrival?->getDate();
            $truckArrivalCreatedAt = $truckArrival?->getCreationDate();

            $timerStartedAt = $truckArrivalCreatedAt ?: $arrivalCreatedAt;

            if ($timerStartedAt) {
                $timerStartedBy = $truckArrivalCreatedAt ? TrackingTimerEvent::ARRIVAL : TrackingTimerEvent::TRUCK_ARRIVAL;
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
                    $timerStartedBy = TrackingTimerEvent::MOVEMENT;
                }
            }

            if ($timerStartedAt
                && $lastStop
                && $timerStartedAt <= $lastStop->getDatetime()) {
                $timerStoppedAt = $lastStop->getDatetime();
                $timerStoppedBy = TrackingTimerEvent::MOVEMENT;
            }
        }

        return [
            "timerStartedAt" => $timerStartedAt ?? null,
            "timerStartedBy" => $timerStartedBy ?? null,
            "timerStoppedAt" => $timerStoppedAt ?? null,
            "timerStoppedBy" => $timerStoppedBy ?? null,
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
                                             array                $previousRecords,
                                             int                  &$previousRecordsCursor,
                                             ?TrackingDelayRecord &$previousRecord): void {

        $previousRecord = $calculatedDelayInheritCurrent
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
