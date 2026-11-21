<?php

namespace App\Services;

use App\Models\Event;
use App\Models\MealPlanEntry;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Builds an RFC 5545 iCalendar feed for a user's family calendar.
 */
class CalendarFeedService
{
    public function build(User $user): string
    {
        $tz = $user->timezone ?: 'UTC';
        $ownerId = $user->familyOwnerId();
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'czirr.local';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Czirr Family//Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape(config('app.name', 'Family').' Calendar'),
            'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
            'X-PUBLISHED-TTL:PT1H',
        ];

        if ($user->can('events.view')) {
            foreach ($this->events($user, $ownerId) as $event) {
                array_push($lines, ...$this->eventLines($event, $tz, $host));
            }
        }

        if ($user->can('meal-plans.view')) {
            foreach ($this->meals($ownerId) as $meal) {
                array_push($lines, ...$this->mealLines($meal, $host));
            }
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    private function events(User $user, int $ownerId)
    {
        $canSeeBudget = $user->can('budgeting.view');

        return Event::where('user_id', $ownerId)
            ->when(! $canSeeBudget, fn ($q) => $q->where(
                fn ($q) => $q->whereNull('source')->orWhereNotIn('source', ['bill', 'income'])
            ))
            ->where(fn ($q) => $q->whereNotNull('rrule')->orWhere('ends_at', '>=', now()->subDays(60)))
            ->orderBy('id')
            ->get();
    }

    private function meals(int $ownerId)
    {
        return MealPlanEntry::where('user_id', $ownerId)
            ->whereBetween('date', [now()->subDays(7)->toDateString(), now()->addDays(90)->toDateString()])
            ->get();
    }

    /** @return list<string> */
    private function eventLines(Event $e, string $tz, string $host): array
    {
        $uidId = $e->isException() ? $e->recurring_event_id : $e->id;

        $lines = [
            'BEGIN:VEVENT',
            "UID:event-{$uidId}@{$host}",
            'DTSTAMP:'.$this->utc(Carbon::instance($e->updated_at ?? now())),
        ];

        if ($e->isException() && $e->original_start) {
            $lines[] = 'RECURRENCE-ID'.$this->dateProp($e->original_start, $e->is_all_day, $tz);
        }

        $lines[] = 'DTSTART'.$this->dateProp($e->starts_at, $e->is_all_day, $tz);
        $lines[] = $e->is_all_day
            ? 'DTEND;VALUE=DATE:'.Carbon::instance($e->ends_at)->setTimezone($tz)->addDay()->startOfDay()->format('Ymd')
            : 'DTEND:'.$this->utc($e->ends_at);

        $lines[] = 'SUMMARY:'.$this->escape($e->title);
        if ($e->description) {
            $lines[] = 'DESCRIPTION:'.$this->escape($e->description);
        }

        if ($e->isRecurring()) {
            $lines[] = 'RRULE:'.preg_replace('/^RRULE:/i', '', $e->rrule);
            foreach ($e->recurrence_exceptions ?? [] as $ex) {
                $lines[] = 'EXDATE'.$this->dateProp(Carbon::parse($ex), $e->is_all_day, $tz);
            }
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /** @return list<string> */
    private function mealLines(MealPlanEntry $m, string $host): array
    {
        $date = Carbon::parse($m->date);

        return [
            'BEGIN:VEVENT',
            "UID:meal-{$m->id}@{$host}",
            'DTSTAMP:'.$this->utc(Carbon::instance($m->updated_at ?? now())),
            'DTSTART;VALUE=DATE:'.$date->format('Ymd'),
            'DTEND;VALUE=DATE:'.$date->copy()->addDay()->format('Ymd'),
            'SUMMARY:'.$this->escape(ucfirst($m->meal_type->value).': '.$m->name),
            'TRANSP:TRANSPARENT',
            'END:VEVENT',
        ];
    }

    /** Property suffix + value, e.g. ";VALUE=DATE:20261113" or ":20261113T140000Z". */
    private function dateProp(CarbonInterface $at, bool $allDay, string $tz): string
    {
        return $allDay
            ? ';VALUE=DATE:'.Carbon::instance($at)->setTimezone($tz)->format('Ymd')
            : ':'.$this->utc($at);
    }

    private function utc(CarbonInterface $at): string
    {
        return Carbon::instance($at)->utc()->format('Ymd\THis\Z');
    }

    private function escape(string $text): string
    {
        $text = str_replace(['\\', ';', ','], ['\\\\', '\;', '\\,'], $text);

        return preg_replace('/\r\n|\r|\n/', '\\n', $text);
    }

    /** Fold lines longer than 75 octets without splitting multibyte characters. */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $out = '';
        $current = '';
        $limit = 75;
        foreach (mb_str_split($line) as $char) {
            if (strlen($current) + strlen($char) > $limit) {
                $out .= $current."\r\n ";
                $current = '';
                $limit = 74;
            }
            $current .= $char;
        }

        return $out.$current;
    }
}
