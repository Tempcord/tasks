<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests\Fixtures;

use Tempcord\Plugins\Tasks\Attributes\Task;

#[Task(cron: '* * * * *')]
final class MinutelyTask
{
    public static int $turns = 0;

    public function __invoke(): void
    {
        self::$turns++;
    }
}
