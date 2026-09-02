<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks;

use Tempcord\Plugins\Tasks\Support\TaskStats;
use function Tempest\Container\get;

if (!function_exists('Tempcord\Plugins\Tasks\tasks')) {
    /**
     * The task registry, for inspecting or cancelling scheduled tasks.
     *
     * Prefer injecting Registry where you can; this exists for the places a
     * container is awkward to reach, such as a closure in configuration.
     */
    function tasks(): Registry
    {
        return get(Registry::class);
    }

    function cancelTask(string $taskName): bool
    {
        return tasks()->cancelTask($taskName);
    }

    /**
     * @return array<string, TaskStats>
     */
    function taskStats(): array
    {
        return tasks()->getStats();
    }
}
