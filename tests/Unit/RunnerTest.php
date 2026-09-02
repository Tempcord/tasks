<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use React\Promise\Deferred;
use Tempcord\Plugins\Tasks\Attributes\Task;
use Tempcord\Plugins\Tasks\Compiler\TaskCompiler;
use Tempcord\Plugins\Tasks\Definitions\TaskDefinition;
use Tempcord\Plugins\Tasks\Registry;
use Tempcord\Plugins\Tasks\Runner;
use Tempcord\Plugins\Tasks\Support\TaskStats;
use Tempcord\Plugins\Tasks\Tests\Doubles\FakeLoop;
use Tempcord\Plugins\Tasks\Tests\Doubles\RecordingLogger;
use Tempcord\Plugins\Tasks\Tests\Fixtures\BootTask;
use Tempcord\Plugins\Tasks\Tests\Fixtures\DisabledTask;
use Tempcord\Plugins\Tasks\Tests\Fixtures\FailingTask;
use Tempcord\Plugins\Tasks\Tests\Fixtures\Housekeeping;
use Tempcord\Plugins\Tasks\Tests\Fixtures\SlowTask;
use Tempcord\Plugins\Tasks\Tests\Fixtures\SweepMessages;
use Tempest\Container\GenericContainer;
use Tempest\Reflection\ClassReflector;

#[CoversClass(Runner::class)]
#[CoversClass(Registry::class)]
#[CoversClass(TaskStats::class)]
final class RunnerTest extends TestCase
{
    private FakeLoop $loop;

    private RecordingLogger $logger;

    private GenericContainer $container;

    protected function setUp(): void
    {
        SweepMessages::$turns = 0;
        BootTask::$turns = 0;
        DisabledTask::$turns = 0;
        Housekeeping::$swept = 0;
        Housekeeping::$pruned = 0;
        SlowTask::$started = 0;
        SlowTask::$holding = new Deferred();

        $this->loop = new FakeLoop();
        $this->logger = new RecordingLogger();
        $this->container = new GenericContainer();
    }

    private function definition(string $class): TaskDefinition
    {
        $reflector = new ClassReflector($class);

        /** @var Task $attribute */
        $attribute = $reflector->getAttribute(Task::class);

        return new TaskCompiler()->compileClass($reflector, $attribute);
    }

    private function runner(): Runner
    {
        return new Runner($this->loop, $this->container, $this->logger);
    }

    private function schedule(string ...$classes): Runner
    {
        $runner = $this->runner();

        foreach ($classes as $class) {
            $runner->schedule($this->definition($class));
        }

        return $runner;
    }

    public function test_an_interval_task_takes_a_turn_when_its_timer_fires(): void
    {
        $this->schedule(SweepMessages::class);

        $this->loop->tick(3);

        $this->assertSame(3, SweepMessages::$turns);
    }

    public function test_an_interval_task_is_armed_for_the_interval_it_declared(): void
    {
        $this->schedule(SweepMessages::class);

        $this->assertSame(10.0, $this->loop->timers[0]->getInterval());
        $this->assertTrue($this->loop->timers[0]->isPeriodic());
    }

    /**
     * A task is a repeating chore, so nothing happens until the first interval
     * has passed — unless it says otherwise.
     */
    public function test_a_task_does_not_take_a_turn_merely_by_being_scheduled(): void
    {
        $this->schedule(SweepMessages::class);

        $this->assertSame(0, SweepMessages::$turns);
    }

    /**
     * Catching up on what expired while the bot was down cannot wait out the
     * first interval — but it still waits for the bot to finish starting.
     */
    public function test_a_task_that_runs_on_boot_takes_its_first_turn_on_the_next_tick(): void
    {
        $this->schedule(BootTask::class);

        $this->assertSame(0, BootTask::$turns);

        $this->loop->drainFutureTicks();

        $this->assertSame(1, BootTask::$turns);
    }

    public function test_a_disabled_task_is_left_out_of_the_schedule_entirely(): void
    {
        $scheduled = $this->runner()->schedule($this->definition(DisabledTask::class));

        $this->loop->tick();
        $this->loop->drainFutureTicks();

        $this->assertFalse($scheduled);
        $this->assertSame([], $this->loop->timers);
        $this->assertSame(0, DisabledTask::$turns);
    }

    /**
     * A timer fires again whether or not the last turn threw. Without
     * containment the exception travels into the event loop and takes the
     * process with it.
     */
    public function test_a_task_that_throws_is_reported_and_keeps_its_place(): void
    {
        $this->schedule(FailingTask::class, SweepMessages::class);

        $this->loop->tick(2);

        $this->assertSame(2, SweepMessages::$turns);
        $this->assertTrue($this->logger->has('the database went away'));
    }

    public function test_a_failure_is_counted_against_the_task(): void
    {
        $runner = $this->schedule(FailingTask::class);

        $this->loop->tick(2);

        $stats = $runner->statsFor('FailingTask');

        $this->assertNotNull($stats);
        $this->assertSame(2, $stats->totalRuns);
        $this->assertSame(2, $stats->failures);
        $this->assertSame('the database went away', $stats->lastError);
        $this->assertSame(0.0, $stats->getSuccessRate());
    }

    public function test_a_turn_that_worked_is_counted_too(): void
    {
        $runner = $this->schedule(SweepMessages::class);

        $this->loop->tick(3);

        $stats = $runner->statsFor('SweepMessages');

        $this->assertNotNull($stats);
        $this->assertSame(3, $stats->totalRuns);
        $this->assertSame(3, $stats->successfulRuns);
        $this->assertSame(100.0, $stats->getSuccessRate());
    }

    /**
     * A task running every ten seconds would otherwise write eight thousand
     * lines a day saying nothing happened.
     */
    public function test_an_ordinary_turn_is_not_announced(): void
    {
        $this->schedule(SweepMessages::class);

        $this->loop->tick(3);

        $this->assertNotContains('info', $this->logger->levels);
        $this->assertNotContains('error', $this->logger->levels);
    }

    /**
     * A task slower than its own interval must not be started alongside itself,
     * or each turn makes the next one slower until nothing else gets a look in.
     */
    public function test_a_task_still_busy_from_its_last_turn_is_skipped(): void
    {
        $this->schedule(SlowTask::class);

        $this->loop->tick(4);

        $this->assertSame(1, SlowTask::$started);
        $this->assertTrue($this->logger->has('still busy'));
    }

    public function test_a_task_that_catches_up_is_run_again(): void
    {
        $this->schedule(SlowTask::class);

        $this->loop->tick();
        SlowTask::$holding->resolve(null);
        $this->loop->tick();

        $this->assertSame(2, SlowTask::$started);
    }

    public function test_a_cancelled_task_stops_taking_turns(): void
    {
        $runner = $this->schedule(SweepMessages::class);

        $this->assertTrue($runner->cancel('SweepMessages'));

        $this->loop->tick(3);

        $this->assertSame(0, SweepMessages::$turns);
        $this->assertSame([], $runner->scheduled());
    }

    public function test_cancelling_something_that_was_never_scheduled_says_so(): void
    {
        $this->assertFalse($this->schedule(SweepMessages::class)->cancel('NoSuchTask'));
    }

    public function test_every_task_can_be_cancelled_at_once(): void
    {
        $runner = $this->schedule(SweepMessages::class, FailingTask::class);

        $runner->cancelAll();
        $this->loop->tick(3);

        $this->assertSame(0, SweepMessages::$turns);
        $this->assertSame([], $runner->scheduled());
    }

    /**
     * Several chores on one class each get their own place in the schedule.
     */
    public function test_tasks_declared_on_methods_are_scheduled_separately(): void
    {
        $reflector = new ClassReflector(Housekeeping::class);
        $compiler = new TaskCompiler();
        $runner = $this->runner();

        foreach (['sweepMessages', 'pruneStatistics'] as $methodName) {
            $method = $reflector->getMethod($methodName);

            /** @var Task $attribute */
            $attribute = $method->getAttribute(Task::class);

            $runner->schedule($compiler->compileMethod($reflector, $method, $attribute));
        }

        $this->assertSame(
            ['Housekeeping::sweepMessages', 'nightly-prune'],
            $runner->scheduled(),
        );
    }
}
