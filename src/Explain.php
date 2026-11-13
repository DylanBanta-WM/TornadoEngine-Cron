<?php

namespace TornadoEngine\Cron;

use Cron\CronExpression;
use DateTimeInterface;

/**
 * Explains a cron expression in plain English and works out when it runs next,
 * using the same library the scheduler uses.
 */
class Explain
{
    private const MONTHS = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    private const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    private const NAMES = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        'sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6,
    ];

    public static function valid(string $expression): bool
    {
        return CronExpression::isValidExpression(trim($expression));
    }

    /**
     * For example "At 02:00 on Sunday." Null when the expression isn't valid.
     */
    public static function describe(string $expression): ?string
    {
        $expression = trim($expression);
        if (! self::valid($expression)) {
            return null;
        }

        $fields = preg_split('/\s+/', CronExpression::factory($expression)->getExpression());
        [$minute, $hour, $day, $month, $weekday] = array_map('strtolower', $fields);

        $parts = [self::time($minute, $hour)];

        if ($day !== '*' && $day !== '?') {
            $parts[] = 'on '.self::field($day, 'day-of-month');
        }
        if ($weekday !== '*' && $weekday !== '?') {
            $parts[] = ($day !== '*' && $day !== '?' ? 'and on ' : 'on ').self::field($weekday, 'day-of-week', self::DAYS);
        }
        if ($month !== '*') {
            $parts[] = 'in '.self::field($month, 'month', self::MONTHS);
        }

        return ucfirst(implode(' ', $parts)).'.';
    }

    /**
     * The next times it runs, in the site's timezone.
     *
     * @return list<DateTimeInterface>
     */
    public static function next(string $expression, int $count = 5): array
    {
        if (! self::valid($expression)) {
            return [];
        }

        return array_values(CronExpression::factory(trim($expression))
            ->getMultipleRunDates($count, 'now', false, false, config('app.timezone')));
    }

    private static function time(string $minute, string $hour): string
    {
        if (ctype_digit($minute) && ctype_digit($hour)) {
            return sprintf('at %02d:%02d', $hour, $minute);
        }

        $text = match (true) {
            $minute === '*' => 'at every minute',
            str_starts_with($minute, '*/') => 'at every '.self::ordinal((int) substr($minute, 2)).' minute',
            default => 'at '.self::field($minute, 'minute'),
        };

        if ($hour === '*') {
            return $text;
        }

        return $text.' past '.(str_starts_with($hour, '*/')
            ? 'every '.self::ordinal((int) substr($hour, 2)).' hour'
            : self::field($hour, 'hour'));
    }

    /**
     * One field, for example "day-of-week 1 through 5" or "Monday and Friday".
     *
     * @param  array<int, string>  $names
     */
    private static function field(string $value, string $unit, array $names = []): string
    {
        $name = function (string $v) use ($names) {
            $n = self::NAMES[$v] ?? (ctype_digit($v) ? (int) $v : null);

            return $names && $n !== null && isset($names[$n]) ? $names[$n] : $v;
        };
        $prefix = $names ? '' : $unit.' ';

        $items = array_map(function (string $item) use ($name, $unit, $prefix) {
            [$range, $step] = array_pad(explode('/', $item, 2), 2, null);
            $range = $range === '*' ? null : $range;
            $span = $range === null ? null : (str_contains($range, '-')
                ? implode(' through ', array_map($name, explode('-', $range, 2)))
                : $name($range));

            if ($step !== null) {
                return 'every '.self::ordinal((int) $step).' '.$unit.($span ? ' from '.$span : '');
            }

            return $prefix.$span;
        }, explode(',', $value));

        // "minute 5, minute 10 and minute 15" reads better as "minute 5, 10 and 15".
        if ($prefix !== '') {
            $original = $items;
            foreach ($original as $i => $item) {
                if ($i > 0 && str_starts_with($item, $prefix) && str_starts_with($original[$i - 1], $prefix)) {
                    $items[$i] = substr($item, strlen($prefix));
                }
            }
        }

        return count($items) > 1
            ? implode(', ', array_slice($items, 0, -1)).' and '.end($items)
            : $items[0];
    }

    private static function ordinal(int $n): string
    {
        $suffix = in_array($n % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th');

        return $n.$suffix;
    }
}
