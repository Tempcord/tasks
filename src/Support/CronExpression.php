<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Support;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Simple cron expression parser
 *
 * Supports standard 5-field cron format:
 * ┌───────────── minute (0-59)
 * │ ┌───────────── hour (0-23)
 * │ │ ┌───────────── day of month (1-31)
 * │ │ │ ┌───────────── month (1-12)
 * │ │ │ │ ┌───────────── day of week (0-6, Sunday = 0)
 * │ │ │ │ │
 * * * * * *
 */
final class CronExpression
{
    private array $minutes;
    private array $hours;
    private array $daysOfMonth;
    private array $months;
    private array $daysOfWeek;

    public function __construct(
        public readonly string $expression
    ) {
        $this->parse($expression);
    }

    /**
     * Check if the cron expression matches the given time
     */
    public function matches(DateTimeInterface $dateTime): bool
    {
        $minute = (int) $dateTime->format('i');
        $hour = (int) $dateTime->format('G');
        $dayOfMonth = (int) $dateTime->format('j');
        $month = (int) $dateTime->format('n');
        $dayOfWeek = (int) $dateTime->format('w');

        return in_array($minute, $this->minutes, true)
            && in_array($hour, $this->hours, true)
            && in_array($dayOfMonth, $this->daysOfMonth, true)
            && in_array($month, $this->months, true)
            && in_array($dayOfWeek, $this->daysOfWeek, true);
    }

    /**
     * Get the next run time after the given time
     */
    public function getNextRunDate(DateTimeInterface $from): DateTimeImmutable
    {
        $next = DateTimeImmutable::createFromInterface($from);
        $next = $next->modify('+1 minute')->setTime(
            (int) $next->modify('+1 minute')->format('G'),
            (int) $next->modify('+1 minute')->format('i'),
            0
        );

        // Search for up to 4 years to find next match
        $maxIterations = 60 * 24 * 366 * 4;

        for ($i = 0; $i < $maxIterations; $i++) {
            if ($this->matches($next)) {
                return $next;
            }
            $next = $next->modify('+1 minute');
        }

        throw new \RuntimeException('Could not find next run date within 4 years');
    }

    /**
     * Get seconds until the next run
     */
    public function getSecondsUntilNextRun(?DateTimeInterface $from = null): int
    {
        $from ??= new DateTimeImmutable();
        $next = $this->getNextRunDate($from);

        return $next->getTimestamp() - $from->getTimestamp();
    }

    /**
     * Parse the cron expression
     */
    private function parse(string $expression): void
    {
        // Handle common aliases
        $expression = match (strtolower(trim($expression))) {
            '@yearly', '@annually' => '0 0 1 1 *',
            '@monthly' => '0 0 1 * *',
            '@weekly' => '0 0 * * 0',
            '@daily', '@midnight' => '0 0 * * *',
            '@hourly' => '0 * * * *',
            default => trim($expression),
        };

        $parts = preg_split('/\s+/', $expression);

        if (count($parts) !== 5) {
            throw new InvalidArgumentException(
                "Invalid cron expression '{$expression}'. Expected 5 fields: minute hour day month weekday"
            );
        }

        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = $parts;

        $this->minutes = $this->parseField($minute, 0, 59);
        $this->hours = $this->parseField($hour, 0, 23);
        $this->daysOfMonth = $this->parseField($dayOfMonth, 1, 31);
        $this->months = $this->parseField($month, 1, 12);
        $this->daysOfWeek = $this->parseField($dayOfWeek, 0, 6);
    }

    /**
     * Parse a single cron field
     */
    private function parseField(string $field, int $min, int $max): array
    {
        $values = [];

        // Handle comma-separated values
        $parts = explode(',', $field);

        foreach ($parts as $part) {
            $values = array_merge($values, $this->parsePart($part, $min, $max));
        }

        $values = array_unique($values);
        sort($values);

        return $values;
    }

    /**
     * Parse a single part of a cron field
     */
    private function parsePart(string $part, int $min, int $max): array
    {
        // Handle wildcard (*)
        if ($part === '*') {
            return range($min, $max);
        }

        // Handle step values (*/5, 1-10/2)
        if (str_contains($part, '/')) {
            [$range, $step] = explode('/', $part, 2);
            $step = (int) $step;

            if ($range === '*') {
                $rangeValues = range($min, $max);
            } else {
                $rangeValues = $this->parsePart($range, $min, $max);
            }

            return array_values(array_filter(
                $rangeValues,
                fn($v) => ($v - $min) % $step === 0
            ));
        }

        // Handle ranges (1-5)
        if (str_contains($part, '-')) {
            [$start, $end] = explode('-', $part, 2);
            $start = max($min, (int) $start);
            $end = min($max, (int) $end);
            return range($start, $end);
        }

        // Handle single value
        $value = (int) $part;
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException(
                "Value {$value} is out of range [{$min}-{$max}]"
            );
        }

        return [$value];
    }
}
