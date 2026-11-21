<?php

use App\Models\Event;
use App\Models\User;
use App\Services\RecurringEventService;
use Carbon\Carbon;

beforeEach(function () {
    $this->svc = new RecurringEventService;
    $this->owner = User::factory()->create();
    // A Monday, 9am UTC, weekly
    $this->master = Event::factory()->recurring('FREQ=WEEKLY;BYDAY=MO')->create([
        'user_id' => $this->owner->id,
        'starts_at' => Carbon::parse('2030-01-07 09:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2030-01-07 10:00:00', 'UTC'),
    ]);
});

function occurrenceStarts(array $occurrences): array
{
    return array_map(fn ($o) => Carbon::parse($o['starts_at'])->toDateString(), $occurrences);
}

test('weekly events expand into one occurrence per week inside the range', function () {
    $occ = $this->svc->expandEventsForRange(
        collect([$this->master]),
        Carbon::parse('2030-01-01', 'UTC'),
        Carbon::parse('2030-01-31', 'UTC'),
        'UTC',
    );

    expect(occurrenceStarts($occ))->toBe(['2030-01-07', '2030-01-14', '2030-01-21', '2030-01-28'])
        ->and($occ[0]['master_event_id'])->toBe($this->master->id)
        ->and(Carbon::parse($occ[0]['ends_at'])->diffInMinutes(Carbon::parse($occ[0]['starts_at'])))->toEqual(-60.0);
});

test('non recurring events and malformed rules are skipped safely', function () {
    $plain = Event::factory()->create(['user_id' => $this->owner->id]);
    $bad = Event::factory()->recurring('NOT-A-RULE')->create(['user_id' => $this->owner->id]);

    $occ = $this->svc->expandEventsForRange(collect([$plain, $bad]), Carbon::parse('2020-01-01'), Carbon::parse('2040-01-01'), 'UTC');

    expect($occ)->toBe([]);
});

test('deleting one occurrence excludes it from expansion', function () {
    $this->svc->deleteSingleOccurrence($this->master, '2030-01-14T09:00:00+00:00');

    $occ = $this->svc->expandEventsForRange(
        collect([$this->master->fresh()]), Carbon::parse('2030-01-01'), Carbon::parse('2030-01-31'), 'UTC',
    );

    expect(occurrenceStarts($occ))->toBe(['2030-01-07', '2030-01-21', '2030-01-28']);
});

test('editing one occurrence creates an exception and hides the original', function () {
    $exception = $this->svc->editSingleOccurrence($this->master, '2030-01-14T09:00:00+00:00', [
        'title' => 'Moved meeting',
        'starts_at' => Carbon::parse('2030-01-15 15:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2030-01-15 16:00:00', 'UTC'),
    ]);

    expect($exception->recurring_event_id)->toBe($this->master->id)
        ->and($exception->rrule)->toBeNull()
        ->and($exception->title)->toBe('Moved meeting');

    $occ = $this->svc->expandEventsForRange(
        collect([$this->master->fresh()]), Carbon::parse('2030-01-01'), Carbon::parse('2030-01-31'), 'UTC',
    );
    expect(occurrenceStarts($occ))->not->toContain('2030-01-14');
});

test('deleting this and future ends the series with an UNTIL', function () {
    $this->svc->deleteThisAndFuture($this->master, '2030-01-21T09:00:00+00:00', 'UTC');

    $master = $this->master->fresh();
    expect($master->rrule)->toContain('UNTIL=20300120T235959Z');

    $occ = $this->svc->expandEventsForRange(
        collect([$master]), Carbon::parse('2030-01-01'), Carbon::parse('2030-02-28'), 'UTC',
    );
    expect(occurrenceStarts($occ))->toBe(['2030-01-07', '2030-01-14']);
});

test('deleting from the first occurrence removes the whole series', function () {
    $this->svc->deleteThisAndFuture($this->master, '2030-01-07T09:00:00+00:00', 'UTC');

    expect(Event::find($this->master->id))->toBeNull();
});

test('editing this and future splits into two series', function () {
    $new = $this->svc->editThisAndFuture($this->master, '2030-01-21T09:00:00+00:00', [
        'title' => 'New schedule',
        'starts_at' => Carbon::parse('2030-01-21 18:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2030-01-21 19:00:00', 'UTC'),
    ], 'UTC');

    expect($this->master->fresh()->rrule)->toContain('UNTIL=')
        ->and($new->id)->not->toBe($this->master->id)
        ->and($new->rrule)->not->toContain('UNTIL')
        ->and($new->title)->toBe('New schedule');
});
