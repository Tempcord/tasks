<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Attributes;

use Attribute;
use InvalidArgumentException;
use Tempest\Reflection\MethodReflector;

/**
 * Runs a method on a schedule, either every so many seconds or on a cron
 * expression.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Task
{
    public ?MethodReflector $reflector = null;

    /**
     * @param int|null $interval run every this many seconds; mutually exclusive with cron
     * @param string|null $cron a cron expression; mutually exclusive with interval
     * @param bool $runOnBoot also run once as soon as the bot starts
     * @param string|null $name defaults to the method's own name
     * @param bool $enabled a disabled task is discovered but never scheduled
     */
    public function __construct(
        public readonly ?int $interval = null,
        public readonly ?string $cron = null,
        public readonly bool $runOnBoot = false,
        public readonly ?string $name = null,
        public readonly bool $enabled = true,
    ) {
        if ($interval === null && $cron === null) {
            throw new InvalidArgumentException('Task must have either an interval or cron expression');
        }

        if ($interval !== null && $cron !== null) {
            throw new InvalidArgumentException('Task cannot have both interval and cron expression');
        }

        if ($interval !== null && $interval < 1) {
            throw new InvalidArgumentException('Task interval must be at least 1 second');
        }
    }

    public function setReflector(MethodReflector $reflector): void
    {
        $this->reflector = $reflector;
    }

    public function getName(): string
    {
        return $this->name ?? $this->reflector?->getName() ?? 'unknown';
    }

    public function isInterval(): bool
    {
        return $this->interval !== null;
    }

    public function isCron(): bool
    {
        return $this->cron !== null;
    }

    public function getScheduleDescription(): string
    {
        if ($this->interval !== null) {
            return $this->formatInterval($this->interval);
        }

        return "cron: {$this->cron}";
    }

    private function formatInterval(int $seconds): string
    {
        if ($seconds < 60) {
            return 'every ' . $seconds . ' second' . ($seconds > 1 ? 's' : '');
        }

        if ($seconds < 3600) {
            $minutes = intdiv($seconds, 60);

            return 'every ' . $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        }

        if ($seconds < 86400) {
            $hours = intdiv($seconds, 3600);

            return 'every ' . $hours . ' hour' . ($hours > 1 ? 's' : '');
        }

        $days = intdiv($seconds, 86400);

        return 'every ' . $days . ' day' . ($days > 1 ? 's' : '');
    }
}
