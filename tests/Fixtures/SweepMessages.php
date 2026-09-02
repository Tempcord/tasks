<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests\Fixtures;

use Tempcord\Plugins\Tasks\Attributes\Task;

/**
 * A task declared the way every other Tempcord handler is: on the class.
 */
#[Task(interval: 10)]
final class SweepMessages
{
    public static int $turns = 0;

    public function __invoke(): void
    {
        self::$turns++;
    }
}
