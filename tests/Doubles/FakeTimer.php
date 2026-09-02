<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests\Doubles;

use React\EventLoop\TimerInterface;

final class FakeTimer implements TimerInterface
{
    public function __construct(
        private readonly float $interval,
        private readonly mixed $callback,
        private readonly bool $periodic,
    ) {}

    public function getInterval(): float
    {
        return $this->interval;
    }

    public function getCallback(): callable
    {
        return $this->callback;
    }

    public function isPeriodic(): bool
    {
        return $this->periodic;
    }

    public function fire(): void
    {
        ($this->callback)($this);
    }
}
