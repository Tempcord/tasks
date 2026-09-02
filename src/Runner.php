<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks;

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use Tempcord\Plugins\Tasks\Definitions\TaskDefinition;
use Tempcord\Plugins\Tasks\Support\CronExpression;
use Tempcord\Plugins\Tasks\Support\TaskStats;
use Tempest\Container\Container;
use Tempest\Log\Logger;
use Throwable;

use function React\Async\async;

/**
 * Runs scheduled tasks, and keeps them running.
 *
 * A timer is less forgiving than an event listener: it fires again whether or
 * not the last turn finished or threw, forever. Both are contained here, so
 * that a task cannot quietly stop the bot doing its other work.
 */
final class Runner
{
    /** @var array<string, TimerInterface> */
    private array $timers = [];

    /** @var array<string, true> tasks whose previous turn has not finished */
    private array $running = [];

    /** @var array<string, TaskStats> */
    private array $stats = [];

    public function __construct(
        private readonly LoopInterface $loop,
        private readonly Container $container,
        private readonly Logger $logger,
    ) {}

    /**
     * @return bool whether the task was put on the schedule
     */
    public function schedule(TaskDefinition $task): bool
    {
        if (!$task->enabled) {
            $this->logger->info('Task ' . $task->name . ' is disabled and was left out of the schedule.');

            return false;
        }

        $this->stats[$task->name] = new TaskStats($task->name);

        if ($task->runOnBoot) {
            /*
             * On the next tick rather than now, so a task cannot run before the
             * rest of the bot has finished being wired together.
             */
            $this->loop->futureTick(fn() => $this->run($task));
        }

        $task->isInterval()
            ? $this->armInterval($task)
            : $this->armCron($task, new CronExpression((string) $task->cron));

        return true;
    }

    private function armInterval(TaskDefinition $task): void
    {
        $this->timers[$task->name] = $this->loop->addPeriodicTimer(
            (int) $task->interval,
            fn() => $this->run($task),
        );
    }

    /**
     * Cron is armed one turn at a time, for the exact number of seconds until
     * the next minute that matches.
     *
     * Waking every minute instead would drift: the first tick lands at whatever
     * offset within the minute the bot happened to start at, and once the drift
     * crosses a minute boundary a matching minute is stepped over entirely and
     * the task silently does not run that hour.
     */
    private function armCron(TaskDefinition $task, CronExpression $cron): void
    {
        $seconds = max(1, $cron->getSecondsUntilNextRun());

        $this->timers[$task->name] = $this->loop->addTimer($seconds, function () use ($task, $cron): void {
            $this->run($task);

            // Re-armed from the turn just taken, so the wait is recomputed
            // rather than accumulated.
            $this->armCron($task, $cron);
        });
    }

    private function run(TaskDefinition $task): void
    {
        /*
         * A task that takes longer than its own interval would otherwise be
         * started again alongside itself, and each turn would make the next one
         * slower until nothing else got a look in.
         */
        if (isset($this->running[$task->name])) {
            $this->logger->warning(
                'Task ' . $task->name . ' is still busy from its last turn; skipping this one.',
            );

            return;
        }

        $this->running[$task->name] = true;
        $startedAt = microtime(true);

        /*
         * In a fiber, so a task may await the REST API the way a command
         * handler does, and inside a catch, so one that throws is logged rather
         * than travelling up into the event loop and taking the process with it.
         */
        async(function () use ($task, $startedAt): void {
            try {
                $task->method->invokeArgs($this->container->get($task->handler), []);

                $this->record($task)?->recordSuccess($this->msSince($startedAt));

                // Debug, not info: a task running every ten seconds would
                // otherwise write eight thousand lines a day saying nothing.
                $this->logger->debug('Task ' . $task->name . ' finished.');
            } catch (Throwable $throwable) {
                $this->record($task)?->recordFailure($this->msSince($startedAt), $throwable->getMessage());

                $this->logger->error(
                    'Task ' . $task->name . ' failed: ' . $throwable->getMessage(),
                    ['exception' => $throwable],
                );
            } finally {
                unset($this->running[$task->name]);
            }
        })();
    }

    private function record(TaskDefinition $task): ?TaskStats
    {
        return $this->stats[$task->name] ?? null;
    }

    private function msSince(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
    }

    public function cancel(string $taskName): bool
    {
        if (!isset($this->timers[$taskName])) {
            return false;
        }

        $this->loop->cancelTimer($this->timers[$taskName]);
        unset($this->timers[$taskName]);

        $this->logger->info('Task ' . $taskName . ' was cancelled.');

        return true;
    }

    public function cancelAll(): void
    {
        foreach (array_keys($this->timers) as $taskName) {
            $this->cancel($taskName);
        }
    }

    public function statsFor(string $taskName): ?TaskStats
    {
        return $this->stats[$taskName] ?? null;
    }

    /**
     * @return array<string, TaskStats>
     */
    public function stats(): array
    {
        return $this->stats;
    }

    /**
     * @return list<string>
     */
    public function scheduled(): array
    {
        return array_keys($this->timers);
    }
}
