<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Discoveries;

use Tempcord\Plugins\Tasks\Attributes\Task;
use Tempcord\Plugins\Tasks\Registry;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;

/**
 * Finds every #[Task] method, wherever it lives.
 *
 * Discovery reaches the bot's own code and any installed package alike, so a
 * package can ship scheduled tasks of its own.
 */
final class TasksDiscovery implements Discovery
{
    use IsDiscovery;

    public function __construct(
        private readonly Registry $registry,
    ) {}

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        foreach ($class->getPublicMethods() as $method) {
            $task = $method->getAttribute(Task::class);

            if ($task === null) {
                continue;
            }

            $task->setReflector($method);

            $this->discoveryItems->add($location, $task);
        }
    }

    public function apply(): void
    {
        foreach ($this->discoveryItems as $task) {
            $this->registry->register($task);
        }
    }
}
