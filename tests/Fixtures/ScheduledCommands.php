<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests\Fixtures;

use Tempcord\Plugins\Tasks\Attributes\Task;

final class ScheduledCommands
{
    /** @var list<string> */
    public static array $ran = [];

    #[Task(interval: 60)]
    public function everyMinute(): void
    {
        self::$ran[] = 'everyMinute';
    }

    #[Task(cron: '0 * * * *', name: 'hourly-report')]
    public function report(): void
    {
        self::$ran[] = 'report';
    }

    #[Task(interval: 30, enabled: false)]
    public function disabled(): void
    {
        self::$ran[] = 'disabled';
    }

    public function notATask(): void
    {
        self::$ran[] = 'notATask';
    }
}
