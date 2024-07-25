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

class ScheduledTaskService {

    public const MAX_ONGOING_SCHEDULED_TASKS = 10;
    private const CACHE_KEY = "scheduled";

    #[Required]
    public ScheduleRuleService $scheduleRuleService;

    #[Required]
    public CacheService $cacheService;

    public function canSchedule(EntityManagerInterface $entityManager,
                                string                 $class): bool {
        $this->validateClass($class);
        return $this->countNextTasksToExecute($entityManager, $class) < self::MAX_ONGOING_SCHEDULED_TASKS;
    }

    public function launchScheduledTasks(EntityManagerInterface $entityManager,
                                         string                 $class,
                                         callable               $teatTask): int {

        $this->validateClass($class);

        [
            "tasks" => $tasks,
            "cacheExists" => $cacheExists,
        ] = $this->getTasksToExecute($entityManager, $class, new DateTime("now"));

        if (!empty($tasks) || !$cacheExists) {
            // refresh cache for next execution before executing current exports
            // OR refresh new cache if it does not exist
            $this->saveTasksCache($entityManager, $class, new DateTime("now +1 minute"));

            foreach ($tasks as $export) {
                $teatTask($export);
            }
        }

        return 0;
    }

    public function deleteCache(string $class): void {
        $this->validateClass($class);

        $cacheCollectionKey = $this->getCacheCollection($class);

        $this->cacheService->delete($cacheCollectionKey, self::CACHE_KEY);
    }

    /**
     * @return array{tasks: ScheduledTask[], cacheExists: boolean}
     */
    private function getTasksToExecute(EntityManagerInterface $entityManager,
                                       string                 $class,
                                       DateTime               $dateToExecute): array {
        $this->validateClass($class);

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

        return [
            "tasks" => $tasksToExecuteNow,
            "cacheExists" => isset($cache),
        ];
    }

    private function saveTasksCache(EntityManagerInterface $entityManager,
                                    string                 $class,
                                    DateTime               $from): void {
        $this->validateClass($class);

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
        $this->validateClass($class);

        return match ($class) {
            Import::class               => CacheService::COLLECTION_IMPORTS,
            Export::class               => CacheService::COLLECTION_EXPORTS,
            PurchaseRequestPlan::class  => CacheService::COLLECTION_PURCHASE_REQUEST_PLANS,
            InventoryMissionPlan::class => CacheService::COLLECTION_INVENTORY_MISSION_PLANS,
            default                     => throw new Exception("Not implemented yet")
        };
    }

    /**
     * @return array<string, ScheduledTask[]>
     */
    private function getTasksGroupedByCacheDateKey(EntityManagerInterface $entityManager,
                                                   string                 $class,
                                                   DateTime               $from): array {

        $this->validateClass($class);

        /** @var ScheduledTaskRepository $repository */
        $repository = $entityManager->getRepository($class);

        // get only tasks to execute on current minute
        return Stream::from($repository->findScheduled())
            ->keymap(function(ScheduledTask $task) use ($from, $class) {
                $nextExecutionTime = $this->calculateTaskNextExecution($task, $from);
                return $nextExecutionTime
                    ? [
                        $this->getCacheDateKey($nextExecutionTime),
                        $task
                    ]
                    : null;
            }, true)
            ->toArray();
    }

    public function calculateTaskNextExecution(ScheduledTask $scheduledTask,
                                               DateTime      $from): ?DateTime {

        $rule = $scheduledTask->getScheduleRule();

        if (!$rule) {
            return null;
        }

        if ($rule->getFrequency() === ScheduleRule::ONCE
            && !$scheduledTask->getLastRun()) {
            return null;
        }

        return $this->scheduleRuleService->calculateNextExecution($rule, $from);
    }

    private function countNextTasksToExecute(EntityManagerInterface $entityManager,
                                             string                 $class): int {
        $this->validateClass($class);

        /** @var ScheduledTaskRepository $scheduledTaskRepository */
        $scheduledTaskRepository = $entityManager->getRepository($class);

        $now = new DateTime();

        return Stream::from($scheduledTaskRepository->findScheduled())
            ->filter(fn(ScheduledTask $scheduledTask) => $this->calculateTaskNextExecution($scheduledTask, $now))
            ->count();
    }

    private function validateClass(string $class): void {
        $refClass = new ReflectionClass($class);
        $refScheduledTask = new ReflectionClass(ScheduledTask::class);

        if (!$refClass->isSubclassOf($refScheduledTask)) {
            throw new Exception("Invalid class. Should implement " . ScheduledTask::class);
        }
    }

}
