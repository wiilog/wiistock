<?php

namespace App\Service\Tracking;

use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\Nature;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingDelay;
use App\Entity\Tracking\TrackingDelayRecord;
use App\Entity\Tracking\TrackingEvent;
use App\Entity\Tracking\TrackingMovement;
use App\Service\Cache\CacheService;
use App\Service\DateTimeService;
use App\Service\SettingsService;
use App\Service\Tracking\PackService;
use DateTime;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\EntityManagerInterface;


class TrackingDelayService {

    public function __construct(
        private SettingsService         $settingsService,
        private TrackingMovementService $trackingMovementService,
        private DateTimeService         $dateTimeService,
        private CacheService            $cacheService,
        private PackService             $packService,
    ) {}

    /**
     * Persist a new TrackingDelay, update if the TrackingDelay exists or remove it for the given pack.
     *
     * A pack could have a TrackingDelay if it's a basic one (method Pack::isBasicUnit)
     * and if its nature has a trackingDelay (method Nature::getTrackingDelay).
     *
     * @see Pack::shouldHaveTrackingDelay()
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
            $trackingDelay = null;
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
            $trackingDelay = $trackingDelay->getLastTrackingEvent() !== TrackingEvent::STOP
                ? $trackingDelay
                : null;
        }

        $pack->setCurrentTrackingDelay($trackingDelay);
        $this->updateGroupTrackingDelay($entityManager, $pack, $trackingDelay);
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
     *     affectedTrackingDelay: TrackingDelay|null,
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

        // set first limit treatment date to t0 + nature tracking delay
        $natureTrackingDelayInterval = $this->dateTimeService->secondsToDateInterval($natureTrackingDelay);
        $limitTreatmentDate = $natureTrackingDelayInterval
            ? $this->dateTimeService->addWorkedPeriodToDateTime($entityManager, $timerStartedAt, $natureTrackingDelayInterval)
            : (clone $timerStartedAt);

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
        $previousSegmentEnd = null;

        foreach ($segments as $segment) {
            [
                "start" => $segmentStart,
                "end" => $segmentEnd,
            ] = $segment;

            $lastTrackingEvent = $segmentEnd->getTrackingEvent();

            $workedInterval = $this->dateTimeService->getWorkedPeriodBetweenDates($entityManager, $segmentStart->getDate(), $segmentEnd->getDate());
            $calculationDate->add($workedInterval);

            $startRemainingTrackingDelay = $endRemainingTrackingDelay ?? $natureTrackingDelay;
            ["delay" => $endRemainingTrackingDelay] = $this->dateTimeService->subtractDelay($natureTrackingDelay, $calculationDateInit, $calculationDate);

            // set remaining time before check push records in calculated records array
            $segmentStart->setRemainingTrackingDelay($startRemainingTrackingDelay);
            $segmentEnd->setRemainingTrackingDelay($endRemainingTrackingDelay);

            $this->pushTrackingDelayRecord($calculatedRecords, $segmentStart, $calculatedDelayInheritLast, $previousRecords, $previousRecordsCursor, $previousCurrentRecord);
            $this->pushTrackingDelayRecord($calculatedRecords, $segmentEnd, $calculatedDelayInheritLast, $previousRecords, $previousRecordsCursor, $previousCurrentRecord);

            // increment limit treatment date while nature tracking delay is positive
            if ($remainingNatureDelay > 0) {
                // shift limit treatment date with pause interval
                $pauseInterval = $previousSegmentEnd
                    ? $this->dateTimeService->getWorkedPeriodBetweenDates($entityManager, $previousSegmentEnd->getDate(), $segmentStart->getDate())
                    : null;
                $pauseElapsedSeconds = $pauseInterval
                    ? floor($this->dateTimeService->convertDateIntervalToMilliseconds($pauseInterval) / 1000)
                    : 0;
                if ($pauseElapsedSeconds) {
                    $limitTreatmentDate->add($pauseInterval);
                }

                $elapsedSeconds = floor($this->dateTimeService->convertDateIntervalToMilliseconds($workedInterval) / 1000);

                if ($remainingNatureDelay > $elapsedSeconds) {
                    $remainingNatureDelay -= $elapsedSeconds;
                }
                else {
                    $remainingNatureDelay = 0;
                }
            }

            $previousSegmentEnd = $segmentEnd;
        }

        ["intervalTime" => $calculatedElapsedTime] = $this->dateTimeService->subtractDelay($natureTrackingDelay, $calculationDateInit, $calculationDate);

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
                    "type" => $firstRecordType,
                    "trackingEvent" => TrackingEvent::START, // display START for each first movement
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
     *  * (case 1) the tracking pack should have a tracking delay
     *    - AND the new trackingMovement is a picking or a drop
     *    - AND the elapsed time was stopped and restart by the last movement OR the last movement was a START one.
     *  * OR (case 2) if tracking is a GROUP or UNGROUP movement then we recalculate the tracking delay of the group
     */
    public function getPackThatRequireTrackingDelay(TrackingMovement $tracking): ?Pack {
        $nextType = $tracking->calculateTrackingDelayData["nextType"] ?? null;
        $previousTrackingEvent = $tracking->calculateTrackingDelayData["previousTrackingEvent"] ?? null;
        $nextTrackingEvent = $tracking->calculateTrackingDelayData["nextTrackingEvent"] ?? null;

        $pack = $tracking->getPack();
        $group = $tracking->getPack();

        // case 1
        if ($tracking->getPack()?->shouldHaveTrackingDelay()
            && in_array($nextType, [TrackingMovement::TYPE_PRISE, TrackingMovement::TYPE_DEPOSE])
            && (
                ($previousTrackingEvent === TrackingEvent::PAUSE && $nextTrackingEvent !== TrackingEvent::PAUSE)
                || ($previousTrackingEvent !== TrackingEvent::PAUSE && $nextTrackingEvent)
            )) {
            return $pack;
        }
        // case 2
        else if (in_array($nextType, [TrackingMovement::TYPE_GROUP, TrackingMovement::TYPE_UNGROUP])
                && $tracking->getPackGroup()) {
            return $tracking->getPackGroup();
        }

        // case 3: there is no calculation to do
        return null;
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

            if ($truckArrival) {
                $timerStartedAt = $truckArrival->getCreationDate();
                $timerStartedBy = TrackingTimerEvent::TRUCK_ARRIVAL;
            }
            else if ($arrival) {
                $timerStartedAt = $arrival->getDate();
                $timerStartedBy = TrackingTimerEvent::ARRIVAL;
            }

            if (!isset($timerStartedAt)
                || !isset($timerStartedBy)) {
                // if not set
                $timerStartedBy = null;
                $timerStartedAt = null;

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


    /**
     * Update tracking delay and nature of the group of given pack if it's defined.
     * We ONLY update it if :
     *  * pack has group defined
     *  * setting Setting::GROUP_GET_CHILD_TRACKING_DELAY checked
     *  * case 1: this is the first tracking delay calculated for a child of this group
     *  * case 2: current pack is already the group pack with the shortest trackingDelay
     *  * case 3: new tracking delay is null AND current pack was the group pack with the shortest tracking delay
     *
     */
    private function updateGroupTrackingDelay(EntityManagerInterface $entityManager,
                                              Pack                   $pack,
                                              ?TrackingDelay         $trackingDelay): void {
        $group = $pack->getGroup();
        if ($group && $this->settingsService->getValue($entityManager, Setting::GROUP_GET_CHILD_TRACKING_DELAY) == 1) {
            $currentGroupTrackingDelay = $group->getCurrentTrackingDelay();
            if ($trackingDelay) {
                // case 1
                if (!$currentGroupTrackingDelay
                    || $currentGroupTrackingDelay->getElapsedTime() > $trackingDelay->getElapsedTime()) {
                    $group
                        ->setCurrentTrackingDelay($trackingDelay)
                        ->setNature($pack->getNature());
                }
                // case 2
                else if ($currentGroupTrackingDelay->getPack()->getId() === $pack->getId()) {
                    // recheck all pack to test the pack with the shortest tracking delay
                    $packSetGroupTrackingDelay = $this->packService->getChildPackWithShortestDelay($group) ?? $pack;
                    $group
                        ->setCurrentTrackingDelay($packSetGroupTrackingDelay->getCurrentTrackingDelay())
                        ->setNature($packSetGroupTrackingDelay->getNature());
                }
            }
            // case 3
            else if ($currentGroupTrackingDelay
                && $currentGroupTrackingDelay->getPack()->getId() === $pack->getId()) {
                // recheck all pack to test the pack with the shortest tracking delay
                // null tracking delay can be set if any child has a tracking delay
                $packSetGroupTrackingDelay = $this->packService->getChildPackWithShortestDelay($group);
                $group
                    ->setCurrentTrackingDelay($packSetGroupTrackingDelay?->getCurrentTrackingDelay())
                    ->setNature($packSetGroupTrackingDelay?->getNature());
            }
        }
    }
}
