<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tempcord\Plugins\Tasks\Attributes\Task;
use Tempcord\Plugins\Tasks\Compiler\TaskCompiler;
use Tempcord\Plugins\Tasks\Definitions\TaskDefinition;
use Tempcord\Plugins\Tasks\Tests\Fixtures\HandlerlessTask;
use Tempcord\Plugins\Tasks\Tests\Fixtures\Housekeeping;
use Tempcord\Plugins\Tasks\Tests\Fixtures\SweepMessages;
use Tempcord\Plugins\Tasks\Tests\Fixtures\UnreadableCronTask;
use Tempest\Reflection\ClassReflector;

#[CoversClass(Task::class)]
#[CoversClass(TaskCompiler::class)]
#[CoversClass(TaskDefinition::class)]
final class TaskCompilerTest extends TestCase
{
    private function compileClass(string $class): TaskDefinition
    {
        $reflector = new ClassReflector($class);

        /** @var Task $attribute */
        $attribute = $reflector->getAttribute(Task::class);

        return new TaskCompiler()->compileClass($reflector, $attribute);
    }

    private function compileMethod(string $class, string $methodName): TaskDefinition
    {
        $reflector = new ClassReflector($class);
        $method = $reflector->getMethod($methodName);

        /** @var Task $attribute */
        $attribute = $method->getAttribute(Task::class);

        return new TaskCompiler()->compileMethod($reflector, $method, $attribute);
    }

    public function test_a_task_on_a_class_runs_through_its_invoke(): void
    {
        $definition = $this->compileClass(SweepMessages::class);

        $this->assertSame(SweepMessages::class, $definition->handler);
        $this->assertSame('__invoke', $definition->method->getName());
        $this->assertSame(10, $definition->interval);
    }

    public function test_a_task_on_a_method_runs_through_that_method(): void
    {
        $definition = $this->compileMethod(Housekeeping::class, 'sweepMessages');

        $this->assertSame(Housekeeping::class, $definition->handler);
        $this->assertSame('sweepMessages', $definition->method->getName());
    }

    /**
     * A method name on its own would collide between classes, and two tasks
     * sharing a name share their statistics and cannot be cancelled apart.
     */
    public function test_a_method_task_is_named_after_its_class_as_well(): void
    {
        $this->assertSame(
            'Housekeeping::sweepMessages',
            $this->compileMethod(Housekeeping::class, 'sweepMessages')->name,
        );
    }

    public function test_a_class_task_is_named_after_its_class(): void
    {
        $this->assertSame('SweepMessages', $this->compileClass(SweepMessages::class)->name);
    }

    public function test_a_name_given_in_the_attribute_wins(): void
    {
        $this->assertSame(
            'nightly-prune',
            $this->compileMethod(Housekeeping::class, 'pruneStatistics')->name,
        );
    }

    public function test_a_class_task_without_an_invoke_method_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('should declare an __invoke method');

        $this->compileClass(HandlerlessTask::class);
    }

    /**
     * An expression nobody can read should fail while the bot is starting and
     * say which task it came from, not four hours later inside a timer.
     */
    public function test_an_unreadable_cron_expression_is_refused_at_discovery(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('UnreadableCronTask');

        $this->compileClass(UnreadableCronTask::class);
    }

    public function test_a_task_must_say_when_it_runs(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('either an interval or a cron expression');

        new Task();
    }

    public function test_a_task_cannot_be_scheduled_two_ways_at_once(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be given both');

        new Task(interval: 60, cron: '@daily');
    }

    /**
     * A zero interval asks the loop to run the task as fast as it can, which
     * starves the gateway heartbeat and drops the connection.
     */
    public function test_a_task_must_run_at_least_a_second_apart(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one second');

        new Task(interval: 0);
    }

    public function test_an_interval_reads_as_something_a_person_can_check(): void
    {
        $this->assertSame('every 10 seconds', $this->compileClass(SweepMessages::class)->schedule());
        $this->assertSame('cron: @daily', $this->compileMethod(Housekeeping::class, 'pruneStatistics')->schedule());
    }
}
