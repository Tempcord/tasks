<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Tasks\Tests\Unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tempcord\Plugins\Tasks\Support\CronExpression;

#[CoversClass(CronExpression::class)]
final class CronExpressionTest extends TestCase
{
    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment);
    }

    public function test_a_wildcard_matches_every_minute(): void
    {
        $cron = new CronExpression('* * * * *');

        $this->assertTrue($cron->matches($this->at('2026-09-02 13:37:00')));
        $this->assertTrue($cron->matches($this->at('2026-01-01 00:00:00')));
    }

    public function test_a_fixed_minute_and_hour_matches_only_then(): void
    {
        $cron = new CronExpression('30 14 * * *');

        $this->assertTrue($cron->matches($this->at('2026-09-02 14:30:00')));
        $this->assertFalse($cron->matches($this->at('2026-09-02 14:31:00')));
        $this->assertFalse($cron->matches($this->at('2026-09-02 15:30:00')));
    }

    #[DataProvider('aliases')]
    public function test_an_alias_stands_for_its_expression(string $alias, string $matches, string $misses): void
    {
        $cron = new CronExpression($alias);

        $this->assertTrue($cron->matches($this->at($matches)), $alias . ' should match ' . $matches);
        $this->assertFalse($cron->matches($this->at($misses)), $alias . ' should not match ' . $misses);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function aliases(): array
    {
        return [
            '@hourly' => ['@hourly', '2026-09-02 14:00:00', '2026-09-02 14:01:00'],
            '@daily' => ['@daily', '2026-09-02 00:00:00', '2026-09-02 01:00:00'],
            '@midnight' => ['@midnight', '2026-09-02 00:00:00', '2026-09-02 12:00:00'],
            '@weekly' => ['@weekly', '2026-09-06 00:00:00', '2026-09-07 00:00:00'],
            '@monthly' => ['@monthly', '2026-09-01 00:00:00', '2026-09-02 00:00:00'],
            '@yearly' => ['@yearly', '2026-01-01 00:00:00', '2026-02-01 00:00:00'],
        ];
    }

    public function test_a_list_matches_any_of_its_values(): void
    {
        $cron = new CronExpression('0,15,30,45 * * * *');

        $this->assertTrue($cron->matches($this->at('2026-09-02 14:15:00')));
        $this->assertTrue($cron->matches($this->at('2026-09-02 14:45:00')));
        $this->assertFalse($cron->matches($this->at('2026-09-02 14:20:00')));
    }

    public function test_a_range_matches_within_it(): void
    {
        $cron = new CronExpression('0 9-17 * * *');

        $this->assertTrue($cron->matches($this->at('2026-09-02 09:00:00')));
        $this->assertTrue($cron->matches($this->at('2026-09-02 17:00:00')));
        $this->assertFalse($cron->matches($this->at('2026-09-02 18:00:00')));
    }

    public function test_a_step_over_a_wildcard_counts_from_the_bottom_of_the_field(): void
    {
        $cron = new CronExpression('*/15 * * * *');

        $this->assertTrue($cron->matches($this->at('2026-09-02 14:00:00')));
        $this->assertTrue($cron->matches($this->at('2026-09-02 14:30:00')));
        $this->assertFalse($cron->matches($this->at('2026-09-02 14:10:00')));
    }

    /**
     * A step over a range counts from where the range starts: 1-10/2 is every
     * second value beginning at one, so 1,3,5,7,9 — not 2,4,6,8,10.
     */
    public function test_a_step_over_a_range_counts_from_the_start_of_the_range(): void
    {
        $cron = new CronExpression('1-10/2 * * * *');

        foreach ([1, 3, 5, 7, 9] as $minute) {
            $this->assertTrue($cron->matches($this->at(sprintf('2026-09-02 14:%02d:00', $minute))));
        }

        foreach ([2, 4, 10] as $minute) {
            $this->assertFalse($cron->matches($this->at(sprintf('2026-09-02 14:%02d:00', $minute))));
        }
    }

    /**
     * Cron's one genuine oddity: with both day fields restricted the line runs
     * on days matching either.
     */
    public function test_restricting_both_day_fields_matches_either_of_them(): void
    {
        $cron = new CronExpression('0 0 1 * 1');

        // The first of the month, which in July 2026 is a Wednesday.
        $this->assertTrue($cron->matches($this->at('2026-07-01 00:00:00')));
        // An ordinary Monday, which is not the first.
        $this->assertTrue($cron->matches($this->at('2026-07-06 00:00:00')));
        // Neither.
        $this->assertFalse($cron->matches($this->at('2026-07-07 00:00:00')));
    }

    /**
     * With only one of them restricted the other stands open and the usual
     * reading applies.
     */
    public function test_restricting_one_day_field_still_narrows(): void
    {
        $cron = new CronExpression('0 0 1 * *');

        $this->assertTrue($cron->matches($this->at('2026-07-01 00:00:00')));
        $this->assertFalse($cron->matches($this->at('2026-07-06 00:00:00')));
    }

    public function test_the_next_run_is_the_following_matching_minute(): void
    {
        $next = new CronExpression('0 * * * *')->getNextRunDate($this->at('2026-09-02 14:30:12'));

        $this->assertSame('2026-09-02 15:00:00', $next->format('Y-m-d H:i:s'));
    }

    /**
     * The minute the expression is asked from is behind it; asking at exactly
     * the matching minute gives the next one rather than answering with now.
     */
    public function test_the_next_run_is_never_the_minute_it_was_asked_in(): void
    {
        $next = new CronExpression('* * * * *')->getNextRunDate($this->at('2026-09-02 14:30:00'));

        $this->assertSame('2026-09-02 14:31:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_it_says_how_long_until_the_next_run(): void
    {
        $seconds = new CronExpression('0 * * * *')
            ->getSecondsUntilNextRun($this->at('2026-09-02 14:59:30'));

        $this->assertSame(30, $seconds);
    }

    public function test_an_expression_with_the_wrong_number_of_fields_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected 5 fields');

        new CronExpression('0 0 *');
    }

    public function test_a_value_outside_its_field_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('out of range');

        new CronExpression('99 * * * *');
    }

    /**
     * Anything that is not a number would otherwise be cast to zero, and a task
     * written for noon would quietly run at midnight.
     */
    public function test_something_that_is_not_a_number_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CronExpression('noon * * * *');
    }

    public function test_a_step_of_zero_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('step must be at least 1');

        new CronExpression('*/0 * * * *');
    }
}
