<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Attributes;

use Attribute;
use Tempcord\Contract\CanBeHandled;
use Tempest\Reflection\MethodReflector;

#[Attribute(Attribute::TARGET_METHOD)]
final class Task implements CanBeHandled
{
    public ?MethodReflector $reflector = null;

    /**
     * Define a scheduled task
     *
     * @param int|null $interval Run every X seconds (mutually exclusive with cron)
     * @param string|null $cron Cron expression for scheduling (mutually exclusive with interval)
     * @param bool $runOnBoot Whether to run immediately when the bot starts
     * @param string|null $name Optional name for the task (defaults to method name)
     * @param bool $enabled Whether the task is enabled
     */
    public function __construct(
        public readonly ?int $interval = null,
        public readonly ?string $cron = null,
        public readonly bool $runOnBoot = false,
        public readonly ?string $name = null,
        public readonly bool $enabled = true,
    ) {
        if ($interval === null && $cron === null) {
            throw new \InvalidArgumentException('Task must have either an interval or cron expression');
        }

        if ($interval !== null && $cron !== null) {
            throw new \InvalidArgumentException('Task cannot have both interval and cron expression');
        }

        if ($interval !== null && $interval < 1) {
            throw new \InvalidArgumentException('Task interval must be at least 1 second');
        }
    }

    public function setReflector(MethodReflector $reflector): void
    {
        $this->reflector = $reflector;
    }

    /**
     * Get the task name (method name if not specified)
     */
    public function getName(): string
    {
        if ($this->name !== null) {
            return $this->name;
        }

        if ($this->reflector !== null) {
            return $this->reflector->getName();
        }

        return 'unknown';
    }

    /**
     * Check if this is an interval-based task
     */
    public function isInterval(): bool
    {
        return $this->interval !== null;
    }

    /**
     * Check if this is a cron-based task
     */
    public function isCron(): bool
    {
        return $this->cron !== null;
    }

    /**
     * Get a human-readable schedule description
     */
    public function getScheduleDescription(): string
    {
        if ($this->interval !== null) {
            return $this->formatInterval($this->interval);
        }

        return "cron: {$this->cron}";
    }

    /**
     * Format interval into human-readable string
     */
    private function formatInterval(int $seconds): string
    {
        if ($seconds < 60) {
            return "every {$seconds} second" . ($seconds > 1 ? 's' : '');
        }

        if ($seconds < 3600) {
            $minutes = (int) ($seconds / 60);
            return "every {$minutes} minute" . ($minutes > 1 ? 's' : '');
        }

        if ($seconds < 86400) {
            $hours = (int) ($seconds / 3600);
            return "every {$hours} hour" . ($hours > 1 ? 's' : '');
        }

        $days = (int) ($seconds / 86400);
        return "every {$days} day" . ($days > 1 ? 's' : '');
    }

    public \Tempcord\Support\Commands\CommandHandler $handler {
        get {
            return $this->handler;
        }
    }
}
