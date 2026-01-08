<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Support;

use DateTimeImmutable;

/**
 * Tracks execution statistics for a task
 */
final class TaskStats
{
    public int $totalRuns = 0;
    public int $successfulRuns = 0;
    public int $failures = 0;
    public float $totalDuration = 0;
    public ?float $lastDuration = null;
    public ?DateTimeImmutable $lastRunAt = null;
    public ?DateTimeImmutable $lastSuccessAt = null;
    public ?DateTimeImmutable $lastFailureAt = null;
    public ?string $lastError = null;

    public function __construct(
        public readonly string $taskName
    ) {}

    /**
     * Record a successful execution
     */
    public function recordSuccess(float $durationMs): void
    {
        $this->totalRuns++;
        $this->successfulRuns++;
        $this->totalDuration += $durationMs;
        $this->lastDuration = $durationMs;
        $this->lastRunAt = new DateTimeImmutable();
        $this->lastSuccessAt = $this->lastRunAt;
    }

    /**
     * Record a failed execution
     */
    public function recordFailure(float $durationMs, string $error): void
    {
        $this->totalRuns++;
        $this->failures++;
        $this->totalDuration += $durationMs;
        $this->lastDuration = $durationMs;
        $this->lastRunAt = new DateTimeImmutable();
        $this->lastFailureAt = $this->lastRunAt;
        $this->lastError = $error;
    }

    /**
     * Get average execution duration in milliseconds
     */
    public function getAverageDuration(): float
    {
        if ($this->totalRuns === 0) {
            return 0;
        }

        return round($this->totalDuration / $this->totalRuns, 2);
    }

    /**
     * Get success rate as percentage
     */
    public function getSuccessRate(): float
    {
        if ($this->totalRuns === 0) {
            return 100.0;
        }

        return round(($this->successfulRuns / $this->totalRuns) * 100, 2);
    }

    /**
     * Convert to array for logging/display
     */
    public function toArray(): array
    {
        return [
            'task' => $this->taskName,
            'total_runs' => $this->totalRuns,
            'successful' => $this->successfulRuns,
            'failures' => $this->failures,
            'success_rate' => $this->getSuccessRate() . '%',
            'avg_duration' => $this->getAverageDuration() . 'ms',
            'last_run' => $this->lastRunAt?->format('Y-m-d H:i:s'),
            'last_error' => $this->lastError,
        ];
    }
}
