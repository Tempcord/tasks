<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests\Fixtures;

use Tempcord\Plugins\Tasks\Attributes\Task;

#[Task(cron: 'every other tuesday')]
final class UnreadableCronTask
{
    public function __invoke(): void {}
}
