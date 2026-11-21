<?php

use App\Models\Bill;
use App\Models\Income;
use App\Support\CalendarDate;
use Carbon\Carbon;

function billOn(string $frequency, string $start): Bill
{
    $bill = new Bill(['frequency' => $frequency, 'amount' => 10]);
    $bill->start_date = Carbon::parse($start); // stored as a UTC date, like the DB cast

    return $bill;
}

dataset('timezones', ['UTC', 'America/New_York', 'America/Los_Angeles', 'Europe/Paris', 'Asia/Kolkata', 'Asia/Tokyo', 'Pacific/Auckland']);

it('counts quarterly occurrences in the right months whatever the caller timezone', function (string $tz) {
    $bill = billOn('quarterly', '2026-01-15');
    $expected = ['2026-01' => 1, '2026-02' => 0, '2026-03' => 0, '2026-04' => 1, '2026-05' => 0, '2026-07' => 1, '2026-10' => 1, '2027-01' => 1];

    foreach ($expected as $ym => $want) {
        $m = Carbon::parse($ym.'-10', $tz)->startOfMonth();
        expect($bill->occurrencesInRange($m->copy(), $m->copy()->endOfMonth()))->toBe($want, "$ym in $tz");
    }
})->with('timezones');

it('counts yearly, monthly and income schedules independent of timezone', function (string $tz) {
    $yearly = billOn('yearly', '2025-03-31');
    $m = fn (string $ym) => Carbon::parse($ym.'-01', $tz)->startOfMonth();

    expect($yearly->occurrencesInRange($m('2026-03'), $m('2026-03')->endOfMonth()))->toBe(1)
        ->and($yearly->occurrencesInRange($m('2026-04'), $m('2026-04')->endOfMonth()))->toBe(0);

    $income = new Income(['frequency' => 'monthly', 'amount' => 1]);
    $income->start_date = Carbon::parse('2026-01-31');
    // 31st clamps to the end of shorter months
    expect($income->occurrencesInRange($m('2026-02'), $m('2026-02')->endOfMonth()))->toBe(1)
        ->and($income->occurrencesInRange($m('2025-12'), $m('2025-12')->endOfMonth()))->toBe(0);
})->with('timezones');

it('counts weekly and biweekly occurrences exactly', function (string $tz) {
    // Fridays from 2026-01-02; Jan 2026 has Fridays on 2, 9, 16, 23, 30
    $weekly = billOn('weekly', '2026-01-02');
    $biweekly = billOn('biweekly', '2026-01-02');
    $start = Carbon::parse('2026-01-01', $tz)->startOfDay();
    $end = Carbon::parse('2026-01-31', $tz)->endOfDay();

    expect($weekly->occurrencesInRange($start->copy(), $end->copy()))->toBe(5)
        ->and($biweekly->occurrencesInRange($start->copy(), $end->copy()))->toBe(3) // 2, 16, 30
        ->and($weekly->occurrencesInRange(Carbon::parse('2026-01-03', $tz)->startOfDay(), Carbon::parse('2026-01-08', $tz)->endOfDay()))->toBe(0);
})->with('timezones');

it('finds the next due date within the cycle, not just N months from now', function () {
    // Quarterly from Jan 15: due Jan, Apr, Jul, Oct. From Feb 20 the next one is Apr 15 (it used to say May 15).
    $quarterly = billOn('quarterly', '2026-01-15');
    expect($quarterly->nextDueDate(Carbon::parse('2026-02-20'))->toDateString())->toBe('2026-04-15')
        ->and($quarterly->nextDueDate(Carbon::parse('2026-04-15'))->toDateString())->toBe('2026-04-15')
        ->and($quarterly->nextDueDate(Carbon::parse('2026-04-16'))->toDateString())->toBe('2026-07-15');

    $yearly = billOn('yearly', '2025-06-10');
    expect($yearly->nextDueDate(Carbon::parse('2026-06-11'))->toDateString())->toBe('2027-06-10');

    $monthly = billOn('monthly', '2026-01-31');
    expect($monthly->nextDueDate(Carbon::parse('2026-02-10'))->toDateString())->toBe('2026-02-28');

    $future = billOn('monthly', '2026-09-05');
    expect($future->nextDueDate(Carbon::parse('2026-03-01'))->toDateString())->toBe('2026-09-05');
});

it('handles one-off and weekly next due dates', function (string $tz) {
    $once = billOn('once', '2026-05-01');
    expect($once->nextDueDate(Carbon::parse('2026-04-30', $tz))->toDateString())->toBe('2026-05-01')
        ->and($once->nextDueDate(Carbon::parse('2026-05-02', $tz))->year)->toBe(9999);

    $weekly = billOn('weekly', '2026-01-02');
    expect($weekly->nextDueDate(Carbon::parse('2026-01-10', $tz)->startOfDay())->toDateString())->toBe('2026-01-16');
})->with('timezones');

it('computes whole calendar day and month differences', function () {
    expect(CalendarDate::daysBetween(Carbon::parse('2026-03-01', 'Asia/Tokyo'), Carbon::parse('2026-03-08', 'America/New_York')))->toBe(7)
        ->and(CalendarDate::daysBetween(Carbon::parse('2026-03-08'), Carbon::parse('2026-03-01')))->toBe(-7)
        ->and(CalendarDate::monthsBetween(Carbon::parse('2025-11-30'), Carbon::parse('2026-02-01')))->toBe(3)
        ->and(CalendarDate::clampedDay(31, 2026, 2))->toBe(28)
        ->and(CalendarDate::clampedDay(31, 2024, 2))->toBe(29);
});
