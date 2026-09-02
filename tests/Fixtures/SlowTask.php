<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests\Fixtures;

use React\Promise\Deferred;
use Tempcord\Plugins\Tasks\Attributes\Task;

use function React\Async\await;

/**
 * A task that outlasts its own interval, held open by the test until it is
 * ready to let it finish.
 */
#[Task(interval: 10)]
final class SlowTask
{
    public static int $started = 0;

    public static ?Deferred $holding = null;

    public function __invoke(): void
    {
        self::$started++;

        await(self::$holding->promise());
    }
}
