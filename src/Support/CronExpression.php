<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Support;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * A five field cron expression.
 *
 *     ┌───────────── minute (0-59)
 *     │ ┌───────────── hour (0-23)
 *     │ │ ┌───────────── day of month (1-31)
 *     │ │ │ ┌───────────── month (1-12)
 *     │ │ │ │ ┌───────────── day of week (0-6, Sunday = 0)
 *     │ │ │ │ │
 *     * * * * *
 */
final class CronExpression
{
    /** @var list<int> */
    private array $minutes;

    /** @var list<int> */
    private array $hours;

    /** @var list<int> */
    private array $daysOfMonth;

    /** @var list<int> */
    private array $months;

    /** @var list<int> */
    private array $daysOfWeek;

    /**
     * Whether each of the two day fields names particular days rather than
     * standing open. Cron reads the pair as "or" when both are restricted.
     */
    private bool $dayOfMonthRestricted;

    private bool $dayOfWeekRestricted;

    public function __construct(
        public readonly string $expression,
    ) {
        $this->parse($expression);
    }

    public function matches(DateTimeInterface $moment): bool
    {
        $matchesTime = in_array((int) $moment->format('i'), $this->minutes, true)
            && in_array((int) $moment->format('G'), $this->hours, true)
            && in_array((int) $moment->format('n'), $this->months, true);

        return $matchesTime && $this->matchesDay($moment);
    }

    /**
     * Cron's one genuine oddity: when a line restricts the day of the month
     * *and* the day of the week, it runs on days matching either, not both. So
     * `0 0 1 * 1` is the first of the month and every Monday, which is what
     * anyone writing it expects and not what an "and" would give them.
     */
    private function matchesDay(DateTimeInterface $moment): bool
    {
        $dayOfMonth = in_array((int) $moment->format('j'), $this->daysOfMonth, true);
        $dayOfWeek = in_array((int) $moment->format('w'), $this->daysOfWeek, true);

        if ($this->dayOfMonthRestricted && $this->dayOfWeekRestricted) {
            return $dayOfMonth || $dayOfWeek;
        }

        return $dayOfMonth && $dayOfWeek;
    }

    /**
     * The first minute at or after the one following the given moment that this
     * expression matches.
     */
    public function getNextRunDate(DateTimeInterface $from): DateTimeImmutable
    {
        $next = DateTimeImmutable::createFromInterface($from)
            ->modify('+1 minute')
            ->setTime(
                (int) DateTimeImmutable::createFromInterface($from)->modify('+1 minute')->format('G'),
                (int) DateTimeImmutable::createFromInterface($from)->modify('+1 minute')->format('i'),
                0,
            );

        // A day of the month that never comes round in a given month — the 31st
        // of February — still resolves within four years, or not at all.
        $limit = 60 * 24 * 366 * 4;

        for ($minute = 0; $minute < $limit; $minute++) {
            if ($this->matches($next)) {
                return $next;
            }

            $next = $next->modify('+1 minute');
        }

        throw new RuntimeException(
            'The expression "' . $this->expression . '" does not come round within four years.',
        );
    }

    public function getSecondsUntilNextRun(?DateTimeInterface $from = null): int
    {
        $from ??= new DateTimeImmutable();

        return $this->getNextRunDate($from)->getTimestamp() - $from->getTimestamp();
    }

    private function parse(string $expression): void
    {
        $expression = match (strtolower(trim($expression))) {
            '@yearly', '@annually' => '0 0 1 1 *',
            '@monthly' => '0 0 1 * *',
            '@weekly' => '0 0 * * 0',
            '@daily', '@midnight' => '0 0 * * *',
            '@hourly' => '0 * * * *',
            default => trim($expression),
        };

        $parts = preg_split('/\s+/', $expression) ?: [];

        if (count($parts) !== 5) {
            throw new InvalidArgumentException(
                'Invalid cron expression "' . $expression . '". Expected 5 fields: minute hour day month weekday',
            );
        }

        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = $parts;

        $this->minutes = $this->parseField($minute, 0, 59);
        $this->hours = $this->parseField($hour, 0, 23);
        $this->daysOfMonth = $this->parseField($dayOfMonth, 1, 31);
        $this->months = $this->parseField($month, 1, 12);
        $this->daysOfWeek = $this->parseField($dayOfWeek, 0, 6);

        $this->dayOfMonthRestricted = trim($dayOfMonth) !== '*';
        $this->dayOfWeekRestricted = trim($dayOfWeek) !== '*';
    }

    /**
     * @return list<int>
     */
    private function parseField(string $field, int $min, int $max): array
    {
        $values = [];

        foreach (explode(',', $field) as $part) {
            $values = [...$values, ...$this->parsePart($part, $min, $max)];
        }

        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    /**
     * @return list<int>
     */
    private function parsePart(string $part, int $min, int $max): array
    {
        if ($part === '*') {
            return range($min, $max);
        }

        if (str_contains($part, '/')) {
            [$range, $step] = explode('/', $part, 2);
            $step = (int) $step;

            if ($step < 1) {
                throw new InvalidArgumentException('A cron step must be at least 1, got "' . $part . '".');
            }

            $values = $range === '*' ? range($min, $max) : $this->parsePart($range, $min, $max);

            /*
             * Counted from where the range starts, not from the bottom of the
             * field: 1-10/2 is 1,3,5,7,9 — every second value beginning at one
             * — and not 2,4,6,8,10.
             */
            $start = $values[0];

            return array_values(array_filter(
                $values,
                static fn(int $value) => ($value - $start) % $step === 0,
            ));
        }

        if (str_contains($part, '-')) {
            [$from, $to] = explode('-', $part, 2);

            return range(max($min, (int) $from), min($max, (int) $to));
        }

        $value = (int) $part;

        if ($value < $min || $value > $max || !ctype_digit(trim($part))) {
            throw new InvalidArgumentException(
                'Value "' . $part . '" is out of range [' . $min . '-' . $max . ']',
            );
        }

        return [$value];
    }
}
