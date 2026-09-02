<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests\Fixtures;

use Tempcord\Plugins\Tasks\Attributes\Task;

/**
 * Catching up on whatever expired while the bot was down cannot wait out the
 * first interval.
 */
#[Task(interval: 3600, runOnBoot: true)]
final class BootTask
{
    public static int $turns = 0;

    public function __invoke(): void
    {
        self::$turns++;
    }
}
