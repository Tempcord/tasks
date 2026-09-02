<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tempcord\Plugins\Tasks\Attributes\Task;
use Tempcord\Plugins\Tasks\Compiler\TaskCompiler;
use Tempcord\Plugins\Tasks\Runner;
use Tempcord\Plugins\Tasks\Tests\Doubles\FakeLoop;
use Tempcord\Plugins\Tasks\Tests\Doubles\RecordingLogger;
use Tempcord\Plugins\Tasks\Tests\Fixtures\MinutelyTask;
use Tempest\Container\GenericContainer;
use Tempest\Reflection\ClassReflector;

/**
 * How a cron task is put on the loop.
 *
 * Waking every minute to ask "is it time yet" drifts: the first tick lands at
 * whatever offset within the minute the bot happened to start at, and once the
 * drift crosses a boundary a matching minute is stepped over entirely and the
 * task silently does not run that hour. So each turn is armed for the exact
 * wait until the next matching minute, and re-armed from the turn just taken.
 */
#[CoversClass(Runner::class)]
final class CronSchedulingTest extends TestCase
{
    private FakeLoop $loop;

    private Runner $runner;

    protected function setUp(): void
    {
        MinutelyTask::$turns = 0;

        $this->loop = new FakeLoop();
        $this->runner = new Runner($this->loop, new GenericContainer(), new RecordingLogger());

        $reflector = new ClassReflector(MinutelyTask::class);

        /** @var Task $attribute */
        $attribute = $reflector->getAttribute(Task::class);

        $this->runner->schedule(new TaskCompiler()->compileClass($reflector, $attribute));
    }

    /**
     * A periodic timer is exactly what drifts, so a cron task must not be given
     * one.
     */
    public function test_a_cron_task_is_armed_one_turn_at_a_time(): void
    {
        $this->assertCount(1, $this->loop->timers);
        $this->assertFalse($this->loop->timers[0]->isPeriodic());
    }

    public function test_it_waits_only_until_the_next_matching_minute(): void
    {
        $wait = $this->loop->timers[0]->getInterval();

        $this->assertGreaterThan(0, $wait);
        $this->assertLessThanOrEqual(60, $wait);
    }

    public function test_the_turn_runs_when_its_moment_arrives(): void
    {
        $this->loop->timers[0]->fire();

        $this->assertSame(1, MinutelyTask::$turns);
    }

    /**
     * Re-armed from the turn just taken, so the wait is recomputed rather than
     * accumulated.
     */
    public function test_taking_a_turn_arms_the_next_one(): void
    {
        $first = $this->loop->timers[0];
        $first->fire();

        $armed = $this->loop->lastTimer();

        $this->assertNotNull($armed);
        $this->assertNotSame($first, $armed);
        $this->assertFalse($armed->isPeriodic());
        $this->assertGreaterThan(0, $armed->getInterval());
        $this->assertLessThanOrEqual(60, $armed->getInterval());
    }

    public function test_it_keeps_taking_turns(): void
    {
        for ($turn = 0; $turn < 3; $turn++) {
            $this->loop->lastTimer()?->fire();
        }

        $this->assertSame(3, MinutelyTask::$turns);
    }

    public function test_a_cron_task_can_be_cancelled(): void
    {
        $this->assertTrue($this->runner->cancel('MinutelyTask'));
        $this->assertSame([], $this->runner->scheduled());
    }
}
