<?php

namespace App\Service;

use App\Entity\ScheduledTask\Export;
use App\Entity\ScheduledTask\Import;
use App\Entity\ScheduledTask\InventoryMissionPlan;
use App\Entity\ScheduledTask\PurchaseRequestPlan;
use App\Entity\ScheduledTask\ScheduledTask;
use App\Entity\ScheduledTask\ScheduleRule;
use App\Entity\ScheduledTask\SleepingStockPlan;
use App\Repository\ScheduledTask\ScheduledTaskRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class ScheduledTaskService {

    /**
     * Max ongoing scheduled tasks for each type.
     * Example: max 10 scheduled exports, max 10 scheduled import,...
     */
    public const MAX_ONGOING_SCHEDULED_TASKS = 10;

    /**
     * Key in cache for each collection of scheduled tasks.
     */
    private const CACHE_KEY = "scheduled";

    #[Required]
    public ScheduleRuleService $scheduleRuleService;

    #[Required]
    public CacheService $cacheService;

    /**
     * @return bool True if the max isn't reached after creation of a new tasks of type $class
     */
    public function canSchedule(EntityManagerInterface $entityManager,
                                string                 $class): bool {
        $this->validateClass($class);

        /** @var ScheduledTaskRepository $scheduledTaskRepository */
        $scheduledTaskRepository = $entityManager->getRepository($class);

        return count($scheduledTaskRepository->findScheduled()) < self::MAX_ONGOING_SCHEDULED_TASKS;
    }

    /**
     * Action includes 3 steps:
     *  1. Get tasks to launch on the current run (on current minute): call to method ScheduledTaskService::getTasksToExecute
     *  2. Calculate next cache for the next executions (current minute + 1): call to method ScheduledTaskService::saveTasksCache
     *  3. Run tasks we get in step 1: call to given treatTask anonymous function.
     *
     * @param string $class Type of the scheduled tasks to launch. Should implement ScheduledTasks interface.
     * @param callable(ScheduledTask, DateTime): void $teatTask Action to call for each task to launch
     * @return int Command result: 0 if success
     */
    public function launchScheduledTasks(EntityManagerInterface $entityManager,
                                         string                 $class,
                                         callable               $teatTask): int {

        $this->validateClass($class);

        $now = new DateTime("now");


        // Step 1: get tasks
        [
            "tasks" => $tasks,
            "cacheExists" => $cacheExists,
        ] = $this->getTasksToExecute($entityManager, $class, $now);

        // Step 2:
        // refresh cache for next execution before executing current exports
        // OR refresh new cache if it does not exist
        if (!empty($tasks) || !$cacheExists) {
            $this->saveTasksCache($entityManager, $class, new DateTime("now +1 minute"));
        }

        // Step 3: execute tasks
        foreach ($tasks as $task) {
            $teatTask($task, $now);
        }

        return Command::SUCCESS;
    }

    /**
     * Delete schedule cache given ScheduleTask implementation
     */
    public function deleteCache(string $class): void {
        $this->validateClass($class);

        $cacheCollectionKey = $this->getCacheCollection($class);

        $this->cacheService->delete($cacheCollectionKey, self::CACHE_KEY);
    }

    /**
     * Return tasks entities presents in cache on the key of $dateToExecute.
     *
     * If cache does not exist (file cache/<tasks>/<CACHE_KEY> does not exist):
     *      Then we return array as {tasks: [], cacheExists: false}.
     *      Else cacheExists attribute is set to true.
     *
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
        $cacheExists = isset($cache);

        if ($cacheExists) {
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
            "cacheExists" => $cacheExists,
        ];
    }

    /**
     * Write in application cache the next tasks to execute for the given $class.
     * Format of the generate file: PHP serialisation of an assoc array<string, int[]> which associate cache date key of the task
     * to array of task ids
     */
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

    /**
     * Calculate an array corresponding to all tasks to be executed in the future.
     * All tasks are grouped by a key which is defined by ScheduledTaskService::getCacheDateKey
     *
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

    /**
     * Calculate next execution date for given task.
     * Result will be greater or equal to given from date.
     * If no result found null returned.
     * @return null|DateTime Result datetime
     */
    public function calculateTaskNextExecution(ScheduledTask $scheduledTask,
                                               DateTime      $from): ?DateTime {

        $rule = $scheduledTask->getScheduleRule();

        if (!$rule) {
            return null;
        }

        if ($rule->getFrequency() === ScheduleRule::ONCE
            && $scheduledTask->getLastRun()) {
            return null;
        }

        return $this->scheduleRuleService->calculateNextExecution($rule, $from);
    }

    /**
     * Check entry class to make sure that it is implement interface ScheduleTask.
     */
    private function validateClass(string $class): void {
        $refClass = new ReflectionClass($class);
        $refScheduledTask = new ReflectionClass(ScheduledTask::class);

        if (!$refClass->isSubclassOf($refScheduledTask)) {
            throw new Exception("Invalid class. Should implement " . ScheduledTask::class);
        }
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
            SleepingStockPlan::class    => CacheService::COLLECTION_SLEEPING_STOCK_PLANS,
            default                     => throw new Exception("Not implemented yet")
        };
    }
}
