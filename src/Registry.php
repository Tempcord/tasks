<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks;

use React\EventLoop\LoopInterface;
use Tempcord\Plugins\Tasks\Definitions\TaskDefinition;
use Tempcord\Plugins\Tasks\Support\TaskStats;
use Tempest\Container\Container;
use Tempest\Container\Singleton;
use Tempest\Log\Logger;

/**
 * Holds every discovered task and puts it on the event loop.
 */
#[Singleton]
final class Registry
{
    /** @var array<string, TaskDefinition> keyed by name, so a task discovered twice is scheduled once */
    private array $tasks = [];

    private ?Runner $runner = null;

    public function __construct(
        private readonly Container $container,
    ) {}

    public function add(TaskDefinition $task): void
    {
        $this->tasks[$task->name] = $task;
    }

    /**
     * @return list<TaskDefinition>
     */
    public function all(): array
    {
        return array_values($this->tasks);
    }

    public function count(): int
    {
        return count($this->tasks);
    }

    /**
     * Arms every task's timer. Nothing takes a turn until the loop itself runs,
     * which is after the gateway opens.
     *
     * @return list<string> what was scheduled, for the caller to report
     */
    public function start(LoopInterface $loop): array
    {
        if ($this->tasks === []) {
            return [];
        }

        /*
         * Resolved here rather than in the constructor: discovery builds this
         * registry while the container is still being assembled, before the
         * initializers that provide the logger have themselves been found.
         */
        $this->runner = new Runner($loop, $this->container, $this->container->get(Logger::class));

        $scheduled = [];

        foreach ($this->tasks as $task) {
            if ($this->runner->schedule($task)) {
                $scheduled[] = $task->name . ' (' . $task->schedule() . ')';
            }
        }

        return $scheduled;
    }

    public function cancel(string $taskName): bool
    {
        return $this->runner?->cancel($taskName) ?? false;
    }

    public function cancelAll(): void
    {
        $this->runner?->cancelAll();
    }

    /**
     * @return array<string, TaskStats>
     */
    public function stats(): array
    {
        return $this->runner?->stats() ?? [];
    }

    /**
     * @return list<string>
     */
    public function scheduled(): array
    {
        return $this->runner?->scheduled() ?? [];
    }
}
