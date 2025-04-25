<?php

namespace App\Service\WorkPeriod;

use App\Entity\WorkPeriod\WorkedDay;
use App\Entity\WorkPeriod\WorkFreeDay;
use App\Service\Cache\CacheNamespaceEnum;
use App\Service\Cache\CacheService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use WiiCommon\Helper\Stream;

class WorkPeriodService {

    private array $cache = [];

    public function __construct(
        private CacheService $cacheService
    ) {}

    public function get(EntityManagerInterface $entityManager,
                        WorkPeriodItem         $item): mixed {
        $cacheKey = $this->getCacheKey($item);

        if (isset($this->cache[$item->value])) {
            return $this->cache[$item->value];
        }

        $value = $this->cacheService->get(
            CacheNamespaceEnum::WORK_PERIOD,
            $cacheKey,
            fn () => $this->getDatabaseValue($entityManager, $item)
        );

        $this->cache[$item->value] = $value;
        return $value;
    }

    private function getCacheKey(WorkPeriodItem $item): string {
        return match ($item) {
            WorkPeriodItem::WORK_FREE_DAYS => "workFreeDay",
            WorkPeriodItem::WORKED_DAYS => "workedDay",

            // If we add new WorkPeriodItem
            default => throw new Exception("Not implemented yet")
        };
    }

    private function getDatabaseValue(EntityManagerInterface $entityManager,
                                      WorkPeriodItem         $item): mixed {
        switch ($item) {
            case WorkPeriodItem::WORKED_DAYS:
                $workedDayRepository = $entityManager->getRepository(WorkedDay::class);
                $workedDays = $workedDayRepository->findAll();
                $value = Stream::from($workedDays)
                    ->keymap(static function(WorkedDay $dayWorked) {
                        $timesArray = $dayWorked->getTimesArray();
                        return $dayWorked->isWorked() && !empty($timesArray)
                            ? [
                                $dayWorked->getDay(),
                                $timesArray,
                            ]
                            : null;
                    })
                    ->toArray();
                break;
            case WorkPeriodItem::WORK_FREE_DAYS:
                $workFreeDayRepository = $entityManager->getRepository(WorkFreeDay::class);
                $value = $workFreeDayRepository->getWorkFreeDaysToDateTime();
                break;

            // If we add new WorkPeriodItem
            default:
                throw new Exception("Not implemented yet");
        }

        return $value;
    }

    /**
     * @param array{onlyDayCheck?: boolean} $options
     */
    public function isOnWorkPeriod(EntityManagerInterface $entityManager,
                                   DateTime               $dateTime,
                                   array                  $options = []): bool {

        $onlyDayCheck = $options['onlyDayCheck'] ?? false;
        $workedDays = $this->get($entityManager, WorkPeriodItem::WORKED_DAYS);
        $dayLabel = strtolower($dateTime->format('l'));

        if (!isset($workedDays[$dayLabel])
            || $this->isWorkFreeDay($entityManager, $dateTime)) {
            return false;
        }

        if ($onlyDayCheck) {
            return true;
        }

        $hourSegments = $workedDays[$dayLabel];
        foreach ($hourSegments as $hourSegment) {
            [$startSegmentStr, $endSegmentStr] = $hourSegment;

            [$startSegmentHour, $startSegmentMinute] = explode(":", $startSegmentStr);
            $startSegment = (clone $dateTime)->setTime($startSegmentHour, $startSegmentMinute);

            [$endSegmentHour, $endSegmentMinute] = explode(":", $endSegmentStr);
            $endSegment = (clone $dateTime)->setTime($endSegmentHour, $endSegmentMinute);

            if ($startSegment <= $dateTime
                && $endSegment >= $dateTime) {
                return true;
            }
        }

        return false;
    }

    public function isWorkFreeDay(EntityManagerInterface $entityManager,
                                  DateTime $day): bool {
        $comparisonFormat = 'Y-m-d';
        $workFreeDays = $this->get($entityManager, WorkPeriodItem::WORK_FREE_DAYS);

        $formattedDay = $day->format($comparisonFormat);

        foreach ($workFreeDays as $workFreeDay) {
            $currentFormattedDay = $workFreeDay->format($comparisonFormat);
            if ($formattedDay < $currentFormattedDay) {
                continue;
            }

            return $currentFormattedDay === $formattedDay;
        }

        return false;
    }

    public function clearCaches(): void {
        $this->cache = [];
        $this->cacheService->delete(CacheNamespaceEnum::WORK_PERIOD);
    }
}
