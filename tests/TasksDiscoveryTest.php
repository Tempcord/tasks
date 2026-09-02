<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tempcord\Plugins\Tasks\Discoveries\TasksDiscovery;
use Tempcord\Plugins\Tasks\Registry;
use Tempcord\Plugins\Tasks\Tests\Fixtures\ScheduledCommands;
use Tempest\Container\GenericContainer;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Log\Logger;
use Tempest\Reflection\ClassReflector;

#[CoversClass(TasksDiscovery::class)]
final class TasksDiscoveryTest extends TestCase
{
    private DiscoveryLocation $location;

    protected function setUp(): void
    {
        $this->location = new DiscoveryLocation(
            namespace: 'Tempcord\\Plugins\\Tasks\\Tests\\Fixtures\\',
            path: __DIR__ . '/Fixtures',
        );
    }

    private function discovery(Registry $registry): TasksDiscovery
    {
        $discovery = new TasksDiscovery($registry);
        $discovery->setItems(new DiscoveryItems());

        return $discovery;
    }

    private function registry(): Registry
    {
        return new Registry(new GenericContainer(), $this->createStub(Logger::class));
    }

    public function test_it_finds_every_annotated_method(): void
    {
        $registry = $this->registry();
        $discovery = $this->discovery($registry);

        $discovery->discover($this->location, new ClassReflector(ScheduledCommands::class));
        $discovery->apply();

        $names = array_map(static fn($task) => $task->getName(), $registry->getAllTasks());

        sort($names);

        $this->assertSame(['disabled', 'everyMinute', 'hourly-report'], $names);
    }

    /**
     * A method without the attribute is left alone, so helpers can sit beside
     * scheduled work.
     */
    public function test_it_ignores_methods_without_the_attribute(): void
    {
        $registry = $this->registry();
        $discovery = $this->discovery($registry);

        $discovery->discover($this->location, new ClassReflector(ScheduledCommands::class));
        $discovery->apply();

        $names = array_map(static fn($task) => $task->getName(), $registry->getAllTasks());

        $this->assertNotContains('notATask', $names);
    }

    /**
     * The reflector is what lets the runner find and call the method later, so
     * discovery has to attach it.
     */
    public function test_it_attaches_the_method_to_each_task(): void
    {
        $registry = $this->registry();
        $discovery = $this->discovery($registry);

        $discovery->discover($this->location, new ClassReflector(ScheduledCommands::class));
        $discovery->apply();

        foreach ($registry->getAllTasks() as $task) {
            $this->assertNotNull($task->reflector);
            $this->assertSame(ScheduledCommands::class, $task->reflector->getDeclaringClass()->getName());
        }
    }

    /**
     * Registration happens in apply, not while discovering, so a discovery run
     * that is thrown away leaves no trace.
     */
    public function test_nothing_is_registered_until_apply(): void
    {
        $registry = $this->registry();
        $discovery = $this->discovery($registry);

        $discovery->discover($this->location, new ClassReflector(ScheduledCommands::class));

        $this->assertSame(0, $registry->count());
    }
}
