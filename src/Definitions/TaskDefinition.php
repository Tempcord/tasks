<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Definitions;

use Tempcord\Plugins\Tasks\Attributes\Task;
use Tempest\Reflection\MethodReflector;

/**
 * A compiled task: what to call, how often, and what to call it.
 *
 * The attribute is left as the declaration it is rather than being handed a
 * reflector and passed around as state — an attribute that mutates is one that
 * cannot be trusted through the discovery cache.
 */
final readonly class TaskDefinition
{
    public function __construct(
        public string $name,
        public string $handler,
        public MethodReflector $method,
        public ?int $interval,
        public ?string $cron,
        public bool $runOnBoot,
        public bool $enabled,
    ) {}

    public function isInterval(): bool
    {
        return $this->interval !== null;
    }

    public function isCron(): bool
    {
        return $this->cron !== null;
    }

    /**
     * How the schedule reads in the logs and in tasks:list.
     */
    public function schedule(): string
    {
        if ($this->cron !== null) {
            return 'cron: ' . $this->cron;
        }

        return 'every ' . $this->humanInterval((int) $this->interval);
    }

    private function humanInterval(int $seconds): string
    {
        [$size, $unit] = match (true) {
            $seconds < 60 => [$seconds, 'second'],
            $seconds < 3600 => [intdiv($seconds, 60), 'minute'],
            $seconds < 86400 => [intdiv($seconds, 3600), 'hour'],
            default => [intdiv($seconds, 86400), 'day'],
        };

        return $size . ' ' . $unit . ($size === 1 ? '' : 's');
    }
}
