<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tempcord\Plugins\Tasks\Attributes\Task;
use Tempcord\Plugins\Tasks\Registry;
use Tempcord\Plugins\Tasks\Tests\Fixtures\ScheduledCommands;
use Tempest\Container\GenericContainer;
use Tempest\Log\Logger;
use Tempest\Reflection\ClassReflector;

#[CoversClass(Registry::class)]
final class RegistryTest extends TestCase
{
    private function registry(): Registry
    {
        return new Registry(new GenericContainer(), $this->createStub(Logger::class));
    }

    private function task(string $method, ?string $name = null): Task
    {
        $task = new Task(interval: 60, name: $name);
        $task->setReflector(new ClassReflector(ScheduledCommands::class)->getMethod($method));

        return $task;
    }

    public function test_it_holds_registered_tasks(): void
    {
        $registry = $this->registry();
        $registry->register($this->task('everyMinute'));

        $this->assertSame(1, $registry->count());
        $this->assertSame('everyMinute', $registry->getAllTasks()[0]->getName());
    }

    /**
     * Discovery can reach the same method through more than one location, and
     * scheduling it twice would run it twice on every tick.
     */
    public function test_the_same_task_registered_twice_is_held_once(): void
    {
        $registry = $this->registry();
        $registry->register($this->task('everyMinute'));
        $registry->register($this->task('everyMinute'));

        $this->assertSame(1, $registry->count());
    }

    /**
     * Statistics come from the runner, which does not exist until the scheduler
     * starts. This used to return the runner itself, so callers expecting a map
     * of statistics got an object.
     */
    public function test_statistics_are_empty_before_the_scheduler_starts(): void
    {
        $registry = $this->registry();
        $registry->register($this->task('everyMinute'));

        $this->assertSame([], $registry->getStats());
        $this->assertSame([], $registry->getScheduledTasks());
    }

    public function test_cancelling_before_the_scheduler_starts_reports_failure(): void
    {
        $this->assertFalse($this->registry()->cancelTask('everyMinute'));
    }
}
