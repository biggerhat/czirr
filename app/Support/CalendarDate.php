<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Calendar-date arithmetic that is immune to timezones and DST.
 *
 * Carbon 3's diffInDays()/diffInMonths() return *fractional* numbers, and they
 * differ when the two dates sit in different timezones (e.g. a UTC start_date
 * compared with a start-of-month in the user's timezone). Truncating or applying
 * % to those fractions silently shifts recurrences by a period. Everything here
 * works on the plain Y-m-d of each date, so results are exact integers.
 */
final class CalendarDate
{
    /** Midnight UTC on the calendar date of $date (its own timezone's y-m-d). */
    public static function of(CarbonInterface $date): Carbon
    {
        return Carbon::create($date->year, $date->month, $date->day, 0, 0, 0, 'UTC');
    }

    /** Whole days from $a to $b (negative when $b is earlier). */
    public static function daysBetween(CarbonInterface $a, CarbonInterface $b): int
    {
        return (int) round(self::of($a)->diffInDays(self::of($b), false));
    }

    /** Calendar months from $a's month to $b's month (negative when $b is earlier). */
    public static function monthsBetween(CarbonInterface $a, CarbonInterface $b): int
    {
        return ($b->year - $a->year) * 12 + ($b->month - $a->month);
    }

    /** Day of month clamped to the month's length (31st → 28th in February). */
    public static function clampedDay(int $day, int $year, int $month): int
    {
        return min($day, Carbon::create($year, $month, 1, 0, 0, 0, 'UTC')->daysInMonth);
    }
}
