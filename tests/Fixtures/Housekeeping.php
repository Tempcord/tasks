<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests\Fixtures;

use Tempcord\Plugins\Tasks\Attributes\Task;

/**
 * Several chores that belong together, which would only be split across classes
 * to satisfy the attribute.
 */
final class Housekeeping
{
    public static int $swept = 0;

    public static int $pruned = 0;

    #[Task(interval: 10)]
    public function sweepMessages(): void
    {
        self::$swept++;
    }

    #[Task(cron: '@daily', name: 'nightly-prune')]
    public function pruneStatistics(): void
    {
        self::$pruned++;
    }

    public function notATask(): void {}
}
