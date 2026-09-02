<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Attributes;

use Attribute;
use InvalidArgumentException;

/**
 * Declares work the bot does on a timer.
 *
 * Goes on an invokable class, the way every other Tempcord attribute does, or
 * on a method when several chores belong together and would only be split
 * across classes to satisfy the attribute:
 *
 *     #[Task(interval: 60)]
 *     final readonly class SweepTemporaryMessages { public function __invoke(): void {} }
 *
 *     final readonly class Housekeeping
 *     {
 *         #[Task(interval: 10)]
 *         public function sweepMessages(): void {}
 *
 *         #[Task(cron: '@daily')]
 *         public function pruneStatistics(): void {}
 *     }
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class Task
{
    /**
     * @param int|null    $interval  run every this many seconds; mutually exclusive with $cron
     * @param string|null $cron      a five field cron expression, or an alias such as '@daily'
     * @param bool        $runOnBoot also take a turn as soon as the bot starts, rather than
     *                               waiting out the first interval
     * @param string|null $name      what to call it in the logs and in tasks:list; defaults to
     *                               the class, or the class and method for a method level task
     * @param bool        $enabled   a task registered but left out of the schedule
     */
    public function __construct(
        public ?int $interval = null,
        public ?string $cron = null,
        public bool $runOnBoot = false,
        public ?string $name = null,
        public bool $enabled = true,
    ) {
        if ($interval === null && $cron === null) {
            throw new InvalidArgumentException('A task must be given either an interval or a cron expression.');
        }

        if ($interval !== null && $cron !== null) {
            throw new InvalidArgumentException('A task cannot be given both an interval and a cron expression.');
        }

        /*
         * A zero or negative interval asks the loop to run the task as fast as
         * it can, which starves everything else the bot is doing, including the
         * gateway heartbeat.
         */
        if ($interval !== null && $interval < 1) {
            throw new InvalidArgumentException('A task interval must be at least one second.');
        }
    }

    public function isInterval(): bool
    {
        return $this->interval !== null;
    }

    public function isCron(): bool
    {
        return $this->cron !== null;
    }
}
