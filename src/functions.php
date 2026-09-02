<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks;

use Tempcord\Plugins\Tasks\Support\TaskStats;

use function Tempest\Container\get;

if (!function_exists('Tempcord\Plugins\Tasks\tasks')) {
    /**
     * The task registry, for reaching the schedule from somewhere the container
     * does not inject into.
     */
    function tasks(): Registry
    {
        return get(Registry::class);
    }

    function cancelTask(string $taskName): bool
    {
        return tasks()->cancel($taskName);
    }

    /**
     * @return array<string, TaskStats>
     */
    function taskStats(): array
    {
        return tasks()->stats();
    }
}
