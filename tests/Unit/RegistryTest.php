<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tempcord\Plugins\Tasks\Attributes\Task;
use Tempcord\Plugins\Tasks\Compiler\TaskCompiler;
use Tempcord\Plugins\Tasks\Definitions\TaskDefinition;
use Tempcord\Plugins\Tasks\Registry;
use Tempcord\Plugins\Tasks\Tests\Doubles\FakeLoop;
use Tempcord\Plugins\Tasks\Tests\Doubles\RecordingLogger;
use Tempcord\Plugins\Tasks\Tests\Fixtures\DisabledTask;
use Tempcord\Plugins\Tasks\Tests\Fixtures\SweepMessages;
use Tempest\Container\GenericContainer;
use Tempest\Log\Logger;
use Tempest\Reflection\ClassReflector;

#[CoversClass(Registry::class)]
final class RegistryTest extends TestCase
{
    private GenericContainer $container;

    protected function setUp(): void
    {
        SweepMessages::$turns = 0;

        $this->container = new GenericContainer();
        $this->container->singleton(Logger::class, new RecordingLogger());
    }

    private function registry(string ...$classes): Registry
    {
        $registry = new Registry($this->container);

        foreach ($classes as $class) {
            $registry->add($this->definition($class));
        }

        return $registry;
    }

    private function definition(string $class): TaskDefinition
    {
        $reflector = new ClassReflector($class);

        /** @var Task $attribute */
        $attribute = $reflector->getAttribute(Task::class);

        return new TaskCompiler()->compileClass($reflector, $attribute);
    }

    public function test_it_holds_registered_tasks(): void
    {
        $registry = $this->registry(SweepMessages::class, DisabledTask::class);

        $this->assertSame(2, $registry->count());
        $this->assertSame(
            ['SweepMessages', 'DisabledTask'],
            array_map(static fn($task) => $task->name, $registry->all()),
        );
    }

    /**
     * Discovery can reach the same class twice — from the bot's own code and
     * from a package that ships it — and the task must still run once.
     */
    public function test_the_same_task_registered_twice_is_held_once(): void
    {
        $registry = $this->registry(SweepMessages::class, SweepMessages::class);

        $this->assertSame(1, $registry->count());
    }

    public function test_starting_puts_every_enabled_task_on_the_loop(): void
    {
        $loop = new FakeLoop();
        $scheduled = $this->registry(SweepMessages::class, DisabledTask::class)->start($loop);

        $this->assertCount(1, $scheduled);
        $this->assertStringContainsString('SweepMessages', $scheduled[0]);
        $this->assertStringContainsString('every 10 seconds', $scheduled[0]);
        $this->assertCount(1, $loop->timers);
    }

    /**
     * A bot with nothing scheduled must not build a runner, and must not report
     * having started one.
     */
    public function test_a_bot_with_no_tasks_starts_nothing(): void
    {
        $this->assertSame([], $this->registry()->start(new FakeLoop()));
    }

    public function test_statistics_are_empty_before_the_scheduler_starts(): void
    {
        $this->assertSame([], $this->registry(SweepMessages::class)->stats());
    }

    public function test_cancelling_before_the_scheduler_starts_reports_failure(): void
    {
        $this->assertFalse($this->registry(SweepMessages::class)->cancel('SweepMessages'));
    }

    public function test_a_started_task_can_be_cancelled_through_the_registry(): void
    {
        $registry = $this->registry(SweepMessages::class);
        $loop = new FakeLoop();
        $registry->start($loop);

        $this->assertTrue($registry->cancel('SweepMessages'));

        $loop->tick(3);

        $this->assertSame(0, SweepMessages::$turns);
    }
}
