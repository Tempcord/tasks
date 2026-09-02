<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tempcord\Plugins\Tasks\Attributes\Task;
use Tempcord\Plugins\Tasks\Tests\Fixtures\ScheduledCommands;
use Tempest\Reflection\ClassReflector;

#[CoversClass(Task::class)]
final class TaskTest extends TestCase
{
    public function test_a_task_needs_a_schedule(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('either an interval or cron expression');

        new Task();
    }

    public function test_a_task_cannot_have_both_kinds_of_schedule(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot have both');

        new Task(interval: 60, cron: '* * * * *');
    }

    public function test_an_interval_below_a_second_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 1 second');

        new Task(interval: 0);
    }

    /**
     * The name falls back to the method it sits on, which is only known once
     * discovery has attached the reflector.
     */
    public function test_the_name_falls_back_to_the_method(): void
    {
        $task = new Task(interval: 60);

        $this->assertSame('unknown', $task->getName());

        $task->setReflector(new ClassReflector(ScheduledCommands::class)->getMethod('everyMinute'));

        $this->assertSame('everyMinute', $task->getName());
    }

    public function test_an_explicit_name_wins(): void
    {
        $task = new Task(cron: '0 * * * *', name: 'hourly-report');
        $task->setReflector(new ClassReflector(ScheduledCommands::class)->getMethod('report'));

        $this->assertSame('hourly-report', $task->getName());
    }

    /** @return array<string, array{int, string}> */
    public static function intervals(): array
    {
        return [
            'one second' => [1, 'every 1 second'],
            'seconds' => [30, 'every 30 seconds'],
            'one minute' => [60, 'every 1 minute'],
            'minutes' => [300, 'every 5 minutes'],
            'hours' => [7200, 'every 2 hours'],
            'days' => [172800, 'every 2 days'],
        ];
    }

    #[DataProvider('intervals')]
    public function test_it_describes_an_interval_in_words(int $seconds, string $expected): void
    {
        $this->assertSame($expected, new Task(interval: $seconds)->getScheduleDescription());
    }

    public function test_it_describes_a_cron_schedule(): void
    {
        $this->assertSame('cron: 0 * * * *', new Task(cron: '0 * * * *')->getScheduleDescription());
    }

    public function test_it_knows_which_kind_of_schedule_it_has(): void
    {
        $interval = new Task(interval: 60);
        $cron = new Task(cron: '* * * * *');

        $this->assertTrue($interval->isInterval());
        $this->assertFalse($interval->isCron());
        $this->assertTrue($cron->isCron());
        $this->assertFalse($cron->isInterval());
    }
}
