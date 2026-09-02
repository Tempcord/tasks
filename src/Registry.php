<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks;

use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Extension\Extension;
use React\EventLoop\Loop;
use Tempcord\Plugins\Tasks\Attributes\Task;
use Tempcord\Plugins\Tasks\Support\TaskStats;
use Tempest\Container\Container;
use Tempest\Container\Singleton;
use Tempest\Log\Logger;

/**
 * Holds every discovered task and starts them once Discord is ready.
 */
#[Singleton]
final class Registry implements Extension
{
    /** @var array<string, Task> */
    private array $tasks = [];

    private ?Runner $runner = null;

    public function __construct(
        private readonly Container $container,
        private readonly Logger $logger,
    ) {}

    public function register(Task $task): void
    {
        // Keyed by name so a task discovered twice is scheduled once.
        $this->tasks[$task->getName()] = $task;
    }

    /**
     * Called by Fenrir once the extension is registered, which the plugin does
     * as the bot boots.
     */
    public function initialize(Discord $discord): void
    {
        if ($this->tasks === []) {
            return;
        }

        $this->runner ??= new Runner(Loop::get(), $this->logger, $this->container);

        foreach ($this->tasks as $task) {
            $this->runner->schedule($task);
        }

        $this->logger->info('Task scheduler initialized', ['tasks' => count($this->tasks)]);
    }

    public function cancelTask(string $taskName): bool
    {
        return $this->runner?->cancel($taskName) ?? false;
    }

    public function cancelAllTasks(): void
    {
        $this->runner?->cancelAll();
    }

    /**
     * Execution statistics per task, empty until the scheduler has started.
     *
     * @return array<string, TaskStats>
     */
    public function getStats(): array
    {
        return $this->runner?->getStats() ?? [];
    }

    /**
     * @return list<string>
     */
    public function getScheduledTasks(): array
    {
        return $this->runner?->getScheduledTasks() ?? [];
    }

    public function count(): int
    {
        return count($this->tasks);
    }

    /**
     * @return list<Task>
     */
    public function getAllTasks(): array
    {
        return array_values($this->tasks);
    }
}
