<?php

namespace App\Service;

use App\Entity\ScheduledTask\Export;
use App\Entity\ScheduledTask\Import;
use App\Entity\ScheduledTask\InventoryMissionPlan;
use App\Entity\ScheduledTask\PurchaseRequestPlan;
use App\Entity\ScheduledTask\ScheduledTask;
use App\Entity\ScheduledTask\ScheduleRule;
use App\Repository\ScheduledTask\ScheduledTaskRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use ReflectionClass;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class ScheduledTaskService
{

    public const MAX_ONGOING_SCHEDULED_TASKS = 5;
    private const CACHE_KEY = "scheduled";

    #[Required]
    public ScheduleRuleService $scheduleRuleService;

    #[Required]
    public CacheService $cacheService;

    public function canSchedule(EntityManagerInterface $entityManager,
                                string                 $class): bool {
        $refClass = new ReflectionClass($class);
        $refScheduledTask = new ReflectionClass(ScheduledTask::class);

        if (!$refClass->isSubclassOf($refScheduledTask)) {
            throw new Exception("Invalid class. Should implement " . ScheduledTask::class);
        }

        /** @var ScheduledTaskRepository $scheduledTaskRepository */
        $scheduledTaskRepository = $entityManager->getRepository($class);

        return count($scheduledTaskRepository->findScheduled()) < self::MAX_ONGOING_SCHEDULED_TASKS;
    }

    public function launchScheduledTasks(EntityManagerInterface $entityManager,
                                         string                 $class,
                                         callable               $teatTask): int {

        $exports = $this->getTasksToExecute($entityManager, $class, new DateTime("now"));

        if (!empty($exports)) {
            // refresh cache for next execution before executing current exports
            $this->saveTasksCache($entityManager, $class, new DateTime("now +1 minute"));

            foreach ($exports as $export) {
                $teatTask($export);
            }
        }

        return 0;
    }

    public function deleteCache(string $class): void {
        $cacheCollectionKey = $this->getCacheCollection($class);

        $this->cacheService->delete($cacheCollectionKey, self::CACHE_KEY);
    }

    /**
     * @return ScheduledTask[]
     */
    private function getTasksToExecute(EntityManagerInterface $entityManager,
                                       string                 $class,
                                       DateTime               $dateToExecute): array {
        $cacheCollectionKey = $this->getCacheCollection($class);

        $cache = $this->cacheService->get($cacheCollectionKey, self::CACHE_KEY);

        // ExportRepository or ImportRepository
        $repository = $entityManager->getRepository($class);

        $cacheKey = $this->getCacheDateKey($dateToExecute);

        if (isset($cache)) {
            $taskIds = $cache[$cacheKey] ?? [];
            $tasksToExecuteNow = !empty($taskIds)
                ? $repository->findBy(["id" => $taskIds])
                : [];
        }
        else {
            $allTasks = $this->getTasksGroupedByCacheDateKey($entityManager, $class, $dateToExecute);
            $tasksToExecuteNow = $allTasks[$cacheKey] ?? [];
        }

        return $tasksToExecuteNow;
    }

    private function saveTasksCache(EntityManagerInterface $entityManager,
                                    string                 $class,
                                    DateTime               $from): void {

        $cacheCollection = $this->getCacheCollection($class);

        /** @var array<string, ScheduledTask[]> $tasks */
        $tasks = $this->getTasksGroupedByCacheDateKey($entityManager, $class, $from);

        // transform array<string, ScheduledTask[]> => array<string, int[]>
        /** @var array<string, int[]> $tasks */
        $serializedTasks = Stream::from($tasks)
            ->keymap(static fn(array $tasksOnCacheKey, string $key) => [
                $key,
                Stream::from($tasksOnCacheKey)
                    ->map(static fn(ScheduledTask $task) => $task->getId())
                    ->toArray()
            ])
            ->toArray();

        $this->cacheService->set($cacheCollection, self::CACHE_KEY, $serializedTasks);
    }

    private function getCacheDateKey(DateTimeInterface $dateTime): string {
        return $dateTime->format("Y-m-d-H-i");
    }

    private function getCacheCollection(string $class): string {
        return match ($class) {
            Import::class => CacheService::COLLECTION_IMPORTS,
            Export::class => CacheService::COLLECTION_EXPORTS,
            default => throw new Exception("Not implemented yet")
        };
    }

    /**
     * @return array<string, ScheduledTask[]>
     */
    private function getTasksGroupedByCacheDateKey(EntityManagerInterface $entityManager,
                                                   string                 $class,
                                                   DateTime               $from): array {
        // ExportRepository or ImportRepository
        $repository = $entityManager->getRepository($class);

        // get only tasks to execute on current minute
        return Stream::from($repository->findScheduled())
            ->keymap(function(ScheduledTask $task) use ($from) {
                $nextExecutionTime = $this->scheduleRuleService->calculateNextExecution($task->getScheduleRule(), $from);
                return $nextExecutionTime
                    ? [
                        $this->getCacheDateKey($nextExecutionTime),
                        $task
                    ]
                    : null;
            }, true)
            ->toArray();
    }

}
