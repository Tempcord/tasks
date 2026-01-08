<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks;

use Ragnarok\Fenrir\Discord;
use Ragnarok\Fenrir\Extension\Extension;
use React\EventLoop\Loop;
use Tempcord\Plugins\Tasks\Attributes\Task;
use Tempcord\Plugins\Tasks\Support\TaskStats;
use Tempest\Container\Singleton;
use Tempest\Log\Logger;
use function Tempest\get;

#[Singleton]
class Registry implements Extension
{
    /** @var array<Task> */
    private array $tasks = [];

    private ?Runner $runner = null;

    public function __construct() {}

    /**
     * Register a task
     */
    public function register(Task $task): void
    {
        $this->tasks[] = $task;
    }

    /**
     * Initialize the task runner when Discord is ready
     */
    public function initialize(Discord $discord): void
    {
        if (empty($this->tasks)) {
            return;
        }

        $logger = get(Logger::class);
        $loop = Loop::get();

        $this->runner = new Runner($loop, $logger);

        // Schedule all tasks
        foreach ($this->tasks as $task) {
            $this->runner->schedule($task);
        }

        $logger->info("📋 Task scheduler initialized", [
            'tasks' => count($this->tasks),
        ]);
    }

    /**
     * Cancel a specific task
     */
    public function cancelTask(string $taskName): bool
    {
        return $this->runner?->cancel($taskName) ?? false;
    }

    /**
     * Cancel all tasks
     */
    public function cancelAllTasks(): void
    {
        $this->runner?->cancelAll();
    }

    /**
     * Get statistics for all tasks
     * @return array<string, TaskStats>
     */
    public function getStats(): array
    {
        return $this->runner ?? [];
    }

    /**
     * Get list of scheduled task names
     * @return array<string>
     */
    public function getScheduledTasks(): array
    {
        return $this->runner?->getScheduledTasks() ?? [];
    }

    /**
     * Get a number of registered tasks
     */
    public function count(): int
    {
        return count($this->tasks);
    }

    public function getAllTasks(): array
    {
        return $this->tasks;
    }
}
