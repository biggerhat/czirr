<?php

namespace App\Models\Concerns;

use App\Support\CalendarDate;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Schedule maths shared by Bill and Income: once / weekly / biweekly / monthly /
 * quarterly / yearly, anchored on a start date. All comparisons are by calendar
 * date (see CalendarDate), so the caller's timezone can never shift a result.
 */
trait HasRecurrence
{
    /** @return array{0: int, 1: int} [interval in days, interval in months] — one of them is 0 */
    private function intervals(string $frequency): array
    {
        return match ($frequency) {
            'weekly' => [7, 0],
            'biweekly' => [14, 0],
            'quarterly' => [0, 3],
            'yearly' => [0, 12],
            default => [0, 1], // monthly
        };
    }

    protected function countOccurrences(CarbonInterface $anchor, string $frequency, CarbonInterface $rangeStart, CarbonInterface $rangeEnd): int
    {
        $anchor = CalendarDate::of($anchor);
        $start = CalendarDate::of($rangeStart);
        $end = CalendarDate::of($rangeEnd);

        if ($end->lt($start)) {
            return 0;
        }

        if ($frequency === 'once') {
            return $anchor->between($start, $end) ? 1 : 0;
        }

        [$days, $months] = $this->intervals($frequency);

        if ($days > 0) {
            if ($anchor->gt($end)) {
                return 0;
            }

            $first = $anchor->gte($start)
                ? $anchor
                : $anchor->copy()->addDays((int) ceil(CalendarDate::daysBetween($anchor, $start) / $days) * $days);

            return $first->gt($end) ? 0 : intdiv(CalendarDate::daysBetween($first, $end), $days) + 1;
        }

        $count = 0;
        for ($m = $start->copy()->startOfMonth(); $m->lte($end); $m->addMonthNoOverflow()) {
            $offset = CalendarDate::monthsBetween($anchor, $m);
            if ($offset < 0 || $offset % $months !== 0) {
                continue;
            }

            $due = Carbon::create($m->year, $m->month, CalendarDate::clampedDay($anchor->day, $m->year, $m->month), 0, 0, 0, 'UTC');
            if ($due->between($start, $end)) {
                $count++;
            }
        }

        return $count;
    }

    protected function nextOccurrence(CarbonInterface $anchor, string $frequency, CarbonInterface $from): Carbon
    {
        $anchor = CalendarDate::of($anchor);
        $from = CalendarDate::of($from);

        if ($anchor->gte($from)) {
            return $anchor;
        }

        if ($frequency === 'once') {
            return Carbon::create(9999, 12, 31, 0, 0, 0, 'UTC');
        }

        [$days, $months] = $this->intervals($frequency);

        if ($days > 0) {
            return $anchor->copy()->addDays((int) ceil(CalendarDate::daysBetween($anchor, $from) / $days) * $days);
        }

        // The next month in the anchor's cycle whose due date is on/after $from.
        // A yearly bill needs up to 12 steps; 25 is a comfortable bound.
        $m = $from->copy()->startOfMonth();
        for ($i = 0; $i < 25; $i++, $m->addMonthNoOverflow()) {
            if (CalendarDate::monthsBetween($anchor, $m) % $months !== 0) {
                continue;
            }

            $due = Carbon::create($m->year, $m->month, CalendarDate::clampedDay($anchor->day, $m->year, $m->month), 0, 0, 0, 'UTC');
            if ($due->gte($from)) {
                return $due;
            }
        }

        return $anchor; // unreachable for valid frequencies
    }
}
