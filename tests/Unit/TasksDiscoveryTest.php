<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tempcord\Plugins\Tasks\Discoveries\TasksDiscovery;
use Tempcord\Plugins\Tasks\Registry;
use Tempcord\Plugins\Tasks\Tests\Fixtures\ScheduledCommands;
use Tempcord\Plugins\Tasks\Tests\Fixtures\SweepMessages;
use Tempest\Container\GenericContainer;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

#[CoversClass(TasksDiscovery::class)]
final class TasksDiscoveryTest extends TestCase
{
    private DiscoveryLocation $location;

    protected function setUp(): void
    {
        $this->location = new DiscoveryLocation(
            namespace: 'Tempcord\\Plugins\\Tasks\\Tests\\Fixtures\\',
            path: dirname(__DIR__) . '/Fixtures',
        );
    }

    private function registry(): Registry
    {
        return new Registry(new GenericContainer());
    }

    private function discover(Registry $registry, string ...$classes): void
    {
        $discovery = new TasksDiscovery($registry);
        $discovery->setItems(new DiscoveryItems());

        foreach ($classes as $class) {
            $discovery->discover($this->location, new ClassReflector($class));
        }

        $discovery->apply();
    }

    /**
     * @return list<string>
     */
    private function names(Registry $registry): array
    {
        $names = array_map(static fn($task) => $task->name, $registry->all());
        sort($names);

        return $names;
    }

    public function test_it_finds_every_annotated_method(): void
    {
        $registry = $this->registry();
        $this->discover($registry, ScheduledCommands::class);

        $this->assertSame(
            ['ScheduledCommands::disabled', 'ScheduledCommands::everyMinute', 'hourly-report'],
            $this->names($registry),
        );
    }

    /**
     * A task on the class itself is found the same way, so a bot may declare
     * one task per class the way it declares commands and listeners.
     */
    public function test_it_finds_a_task_declared_on_the_class(): void
    {
        $registry = $this->registry();
        $this->discover($registry, SweepMessages::class);

        $this->assertSame(['SweepMessages'], $this->names($registry));
    }

    /**
     * A method without the attribute is left alone, so helpers can sit beside
     * scheduled work.
     */
    public function test_it_ignores_methods_without_the_attribute(): void
    {
        $registry = $this->registry();
        $this->discover($registry, ScheduledCommands::class);

        $this->assertNotContains('ScheduledCommands::notATask', $this->names($registry));
    }

    /**
     * The method is what the runner calls later, so discovery has to carry it
     * through.
     */
    public function test_each_task_knows_what_to_call(): void
    {
        $registry = $this->registry();
        $this->discover($registry, ScheduledCommands::class);

        foreach ($registry->all() as $task) {
            $this->assertSame(ScheduledCommands::class, $task->handler);
            $this->assertSame(
                ScheduledCommands::class,
                $task->method->getDeclaringClass()->getName(),
            );
        }
    }

    /**
     * Registration happens in apply, not while discovering, so a discovery run
     * that is thrown away leaves no trace.
     */
    public function test_nothing_is_registered_until_apply(): void
    {
        $registry = $this->registry();

        $discovery = new TasksDiscovery($registry);
        $discovery->setItems(new DiscoveryItems());
        $discovery->discover($this->location, new ClassReflector(ScheduledCommands::class));

        $this->assertSame(0, $registry->count());
    }
}
