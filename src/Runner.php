<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks;

use DateTimeImmutable;
use React\EventLoop\LoopInterface;
use Tempcord\Plugins\Tasks\Attributes\Task;
use Tempcord\Plugins\Tasks\Support\CronExpression;
use Tempcord\Plugins\Tasks\Support\TaskStats;
use Tempest\Container\Container;
use Tempest\Log\Logger;
use Throwable;

/**
 * Manages the execution of scheduled tasks
 */
final class Runner
{
    /** @var array<string, mixed> Timer references for cleanup */
    private array $timers = [];

    /** @var array<string, DateTimeImmutable> Last run times for cron tasks */
    private array $lastRunTimes = [];

    /** @var array<string, TaskStats> Task execution statistics */
    private array $stats = [];

    public function __construct(
        private readonly LoopInterface $loop,
        private readonly Logger $logger,
        private readonly Container $container,
    ) {}

    /**
     * Schedule a task for execution
     */
    public function schedule(Task $task): void
    {
        if (!$task->enabled) {
            $this->logger->info("Task '{$task->getName()}' is disabled, skipping");
            return;
        }

        $taskName = $task->getName();
        $this->stats[$taskName] = new TaskStats($taskName);

        if ($task->isInterval()) {
            $this->scheduleIntervalTask($task);
        } else {
            $this->scheduleCronTask($task);
        }

        $this->logger->info("Scheduled task '{$taskName}'", [
            'schedule' => $task->getScheduleDescription(),
            'runOnBoot' => $task->runOnBoot,
        ]);
    }

    /**
     * Schedule an interval-based task
     */
    private function scheduleIntervalTask(Task $task): void
    {
        $taskName = $task->getName();
        $interval = $task->interval;

        // Run immediately if configured
        if ($task->runOnBoot) {
            $this->loop->futureTick(fn() => $this->executeTask($task));
        }

        // Schedule periodic execution
        $timer = $this->loop->addPeriodicTimer($interval, fn() => $this->executeTask($task));
        $this->timers[$taskName] = $timer;
    }

    /**
     * Schedule a cron-based task
     */
    private function scheduleCronTask(Task $task): void
    {
        $taskName = $task->getName();
        $cron = new CronExpression($task->cron);

        // Run immediately if configured
        if ($task->runOnBoot) {
            $this->loop->futureTick(fn() => $this->executeTask($task));
        }

        // Check every minute if cron matches
        $timer = $this->loop->addPeriodicTimer(60, function () use ($task, $cron, $taskName) {
            $now = new DateTimeImmutable();
            $currentMinute = $now->format('Y-m-d H:i');

            // Avoid running twice in the same minute
            $lastRun = $this->lastRunTimes[$taskName] ?? null;
            if ($lastRun !== null && $lastRun->format('Y-m-d H:i') === $currentMinute) {
                return;
            }

            if ($cron->matches($now)) {
                $this->lastRunTimes[$taskName] = $now;
                $this->executeTask($task);
            }
        });

        $this->timers[$taskName] = $timer;
    }

    /**
     * Execute a task
     */
    private function executeTask(Task $task): void
    {
        $taskName = $task->getName();
        $startTime = microtime(true);

        $this->logger->debug("Running task '{$taskName}'");

        try {
            $instance = $this->container->get($task->reflector->getDeclaringClass()->getName());

            $task->reflector->invokeArgs($instance);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->stats[$taskName]->recordSuccess($duration);

            $this->logger->info("Task '{$taskName}' completed", [
                'duration' => "{$duration}ms",
                'runs' => $this->stats[$taskName]->totalRuns,
            ]);
        } catch (Throwable $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->stats[$taskName]->recordFailure($duration, $e->getMessage());

            $this->logger->error("Task '{$taskName}' failed", [
                'error' => $e->getMessage(),
                'duration' => "{$duration}ms",
                'failures' => $this->stats[$taskName]->failures,
            ]);
        }
    }

    /**
     * Cancel a scheduled task
     */
    public function cancel(string $taskName): bool
    {
        if (!isset($this->timers[$taskName])) {
            return false;
        }

        $this->loop->cancelTimer($this->timers[$taskName]);
        unset($this->timers[$taskName]);

        $this->logger->info("Cancelled task '{$taskName}'");

        return true;
    }

    /**
     * Cancel all scheduled tasks
     */
    public function cancelAll(): void
    {
        foreach (array_keys($this->timers) as $taskName) {
            $this->cancel($taskName);
        }
    }

    /**
     * Get statistics for a specific task
     */
    public function getTaskStats(string $taskName): ?TaskStats
    {
        return $this->stats[$taskName] ?? null;
    }

    /**
     * @return array<string, TaskStats>
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Get list of scheduled task names
     * @return array<string>
     */
    public function getScheduledTasks(): array
    {
        return array_keys($this->timers);
    }
}
