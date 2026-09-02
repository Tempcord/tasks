<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Discoveries;

use Tempcord\Plugins\Tasks\Attributes\Task;
use Tempcord\Plugins\Tasks\Compiler\TaskCompiler;
use Tempcord\Plugins\Tasks\Registry;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;

final class TasksDiscovery implements Discovery
{
    use IsDiscovery;

    public function __construct(
        private readonly Registry $registry,
        private readonly TaskCompiler $compiler = new TaskCompiler(),
    ) {}

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        foreach ($class->getAttributes(Task::class) as $task) {
            $this->discoveryItems->add($location, $this->compiler->compileClass($class, $task));
        }

        foreach ($class->getPublicMethods() as $method) {
            foreach ($method->getAttributes(Task::class) as $task) {
                $this->discoveryItems->add($location, $this->compiler->compileMethod($class, $method, $task));
            }
        }
    }

    public function apply(): void
    {
        foreach ($this->discoveryItems as $task) {
            $this->registry->add($task);
        }
    }
}
