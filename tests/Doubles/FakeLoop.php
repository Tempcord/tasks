<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests\Doubles;

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

/**
 * A loop that never runs, so a test can say exactly when a timer fires.
 *
 * Waiting out a real interval would make the suite as slow as the schedules it
 * exercises, and a cron task's next turn can be an hour away.
 */
final class FakeLoop implements LoopInterface
{
    /** @var list<FakeTimer> */
    public array $timers = [];

    /** @var list<callable> */
    public array $futureTicks = [];

    public function addTimer($interval, $callback): TimerInterface
    {
        $timer = new FakeTimer((float) $interval, $callback, periodic: false);
        $this->timers[] = $timer;

        return $timer;
    }

    public function addPeriodicTimer($interval, $callback): TimerInterface
    {
        $timer = new FakeTimer((float) $interval, $callback, periodic: true);
        $this->timers[] = $timer;

        return $timer;
    }

    public function cancelTimer(TimerInterface $timer): void
    {
        $this->timers = array_values(array_filter(
            $this->timers,
            static fn(FakeTimer $known) => $known !== $timer,
        ));
    }

    public function futureTick($listener): void
    {
        $this->futureTicks[] = $listener;
    }

    /**
     * Fires every timer currently armed, once.
     *
     * The list is copied first: a cron task re-arms itself from inside its own
     * callback, and the new timer belongs to the next turn rather than this one.
     */
    public function tick(int $times = 1): void
    {
        for ($turn = 0; $turn < $times; $turn++) {
            foreach ($this->timers as $timer) {
                $timer->fire();
            }
        }
    }

    public function drainFutureTicks(): void
    {
        $ticks = $this->futureTicks;
        $this->futureTicks = [];

        foreach ($ticks as $tick) {
            $tick();
        }
    }

    /**
     * The most recently armed timer, which for a cron task is the one waiting
     * for the next matching minute.
     */
    public function lastTimer(): ?FakeTimer
    {
        return $this->timers === [] ? null : $this->timers[count($this->timers) - 1];
    }

    public function addReadStream($stream, $listener): void {}

    public function addWriteStream($stream, $listener): void {}

    public function removeReadStream($stream): void {}

    public function removeWriteStream($stream): void {}

    public function addSignal($signal, $listener): void {}

    public function removeSignal($signal, $listener): void {}

    public function run(): void {}

    public function stop(): void {}
}
