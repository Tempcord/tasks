<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests\Fixtures;

use Tempcord\Plugins\Tasks\Attributes\Task;

#[Task(interval: 10)]
final class HandlerlessTask
{
}
