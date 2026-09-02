<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use Tempcord\Plugins\Tasks\Attributes\Task;
use Tempcord\Plugins\Tasks\Runner;
use Tempcord\Plugins\Tasks\Tests\Fixtures\ScheduledCommands;
use Tempest\Container\GenericContainer;
use Tempest\Log\Logger;
use Tempest\Reflection\ClassReflector;

#[CoversClass(Runner::class)]
final class RunnerTest extends TestCase
{
    protected function setUp(): void
    {
        ScheduledCommands::$ran = [];
    }

    private function runner(): Runner
    {
        return new Runner(Loop::get(), $this->createStub(Logger::class), new GenericContainer());
    }

    private function task(string $method, ?int $interval = 60, ?string $cron = null, bool $enabled = true, bool $runOnBoot = false): Task
    {
        $task = new Task(interval: $interval, cron: $cron, runOnBoot: $runOnBoot, enabled: $enabled);
        $task->setReflector(new ClassReflector(ScheduledCommands::class)->getMethod($method));

        return $task;
    }

    public function test_it_schedules_an_interval_task(): void
    {
        $runner = $this->runner();
        $runner->schedule($this->task('everyMinute'));

        $this->assertSame(['everyMinute'], $runner->getScheduledTasks());

        $runner->cancelAll();
    }

    public function test_a_disabled_task_is_never_scheduled(): void
    {
        $runner = $this->runner();
        $runner->schedule($this->task('disabled', enabled: false));

        $this->assertSame([], $runner->getScheduledTasks());
        $this->assertNull($runner->getTaskStats('disabled'));
    }

    public function test_a_cron_task_is_scheduled_on_a_ticking_timer(): void
    {
        $runner = $this->runner();
        $runner->schedule($this->task('report', interval: null, cron: '0 * * * *'));

        $this->assertSame(['report'], $runner->getScheduledTasks());

        $runner->cancelAll();
    }

    public function test_cancelling_removes_a_task(): void
    {
        $runner = $this->runner();
        $runner->schedule($this->task('everyMinute'));

        $this->assertTrue($runner->cancel('everyMinute'));
        $this->assertSame([], $runner->getScheduledTasks());
        $this->assertFalse($runner->cancel('everyMinute'));
    }

    /**
     * Statistics exist from the moment a task is scheduled, so a task that has
     * not run yet still reports zero runs rather than nothing at all.
     */
    public function test_statistics_exist_from_scheduling(): void
    {
        $runner = $this->runner();
        $runner->schedule($this->task('everyMinute'));

        $stats = $runner->getStats();

        $this->assertArrayHasKey('everyMinute', $stats);
        $this->assertSame(0, $stats['everyMinute']->totalRuns);

        $runner->cancelAll();
    }

    /**
     * runOnBoot queues the task on the next tick rather than running it inline,
     * so scheduling never blocks the boot sequence.
     */
    public function test_run_on_boot_executes_the_task(): void
    {
        $runner = $this->runner();
        $runner->schedule($this->task('everyMinute', runOnBoot: true));

        $this->assertSame([], ScheduledCommands::$ran, 'should not have run inline');

        Loop::get()->futureTick(static fn() => Loop::get()->stop());
        Loop::get()->run();

        $this->assertSame(['everyMinute'], ScheduledCommands::$ran);
        $this->assertSame(1, $runner->getStats()['everyMinute']->totalRuns);

        $runner->cancelAll();
    }
}
