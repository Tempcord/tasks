<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks;

use function Tempest\get;

if (!function_exists('Tempcord\Plugins\Tasks\tasks')) {
    /**
     * Get the tasks registry for managing scheduled tasks
     */
    function tasks(): Registry
    {
        return get(Registry::class);
    }

    /**
     * Cancel a scheduled task by name
     */
    function cancelTask(string $taskName): bool
    {
        return tasks()->cancelTask($taskName);
    }

    /**
     * Get task statistics
     * @return array<string, Support\TaskStats>
     */
    function taskStats(): array
    {
        return tasks()->getStats();
    }
}
