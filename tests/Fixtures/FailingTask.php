<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests\Fixtures;

use RuntimeException;
use Tempcord\Plugins\Tasks\Attributes\Task;

#[Task(interval: 10)]
final class FailingTask
{
    public function __invoke(): void
    {
        throw new RuntimeException('the database went away');
    }
}
