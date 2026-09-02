<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests\Fixtures;

use Tempcord\Plugins\Tasks\Attributes\Task;

#[Task(interval: 10, enabled: false)]
final class DisabledTask
{
    public static int $turns = 0;

    public function __invoke(): void
    {
        self::$turns++;
    }
}
