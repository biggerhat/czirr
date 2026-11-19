<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\ChoreAssignment;
use App\Models\ChoreCompletion;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Expense;
use App\Models\MealPlanEntry;
use App\Models\User;
use Carbon\Carbon;

class DailyDigestService
{
    public function __construct(private RecurringEventService $recurringService) {}

    /**
     * Build the digest sections for a user's "today" (in their own timezone).
     *
     * @return array{date: string, chores: list<array>, meals: list<array>, events: list<array>, bills: list<array>}
     */
    public function build(User $user, ?Carbon $now = null): array
    {
        $timezone = $user->timezone ?: 'UTC';
        $today = Carbon::instance($now ?? now())->setTimezone($timezone)->startOfDay();
        $ownerId = $user->familyOwnerId();

        return [
            'date' => $today->toDateString(),
            'chores' => $user->can('chores.view') ? $this->chores($user, $ownerId, $today) : [],
            'meals' => $user->can('meal-plans.view') ? $this->meals($ownerId, $today) : [],
            'events' => $user->can('events.view') ? $this->events($ownerId, $today, $timezone) : [],
            'bills' => $user->can('budgeting.view') ? $this->bills($user, $ownerId, $today) : [],
            'birthdays' => $user->can('contacts.view') ? $this->birthdays($ownerId, $today) : [],
        ];
    }

    public function isEmpty(array $digest): bool
    {
        return collect($digest)->except('date')->flatten(1)->isEmpty();
    }

    /**
     * Birthdays in the next 7 days. Today's is already on the calendar as an event.
     *
     * @return list<array{name: string, when: string, age: int|null}>
     */
    private function birthdays(int $ownerId, Carbon $today): array
    {
        return Contact::where('user_id', $ownerId)
            ->whereNotNull('date_of_birth')
            ->get()
            ->map(function (Contact $c) use ($today) {
                $next = $c->date_of_birth->copy()->year($today->year)->startOfDay();
                if ($next->lt($today)) {
                    $next->addYear();
                }

                return [
                    'days' => \App\Support\CalendarDate::daysBetween($today, $next),
                    'name' => trim($c->first_name.' '.$c->last_name),
                    'when' => $next->format('D, M j'),
                    'age' => $c->date_of_birth->year > 1900 ? $next->year - $c->date_of_birth->year : null,
                ];
            })
            ->filter(fn ($b) => $b['days'] >= 1 && $b['days'] <= 7)
            ->sortBy('days')
            ->map(fn ($b) => ['name' => $b['name'], 'when' => $b['when'], 'age' => $b['age']])
            ->values()
            ->all();
    }

    private function chores(User $user, int $ownerId, Carbon $today): array
    {
        $member = $user->linkedFamilyMember();

        $assignments = ChoreAssignment::where('day_of_week', $today->dayOfWeek)
            ->whereHas('chore', fn ($q) => $q->where('user_id', $ownerId)->where('is_active', true))
            ->when($member, fn ($q) => $q->where('family_member_id', $member->id))
            ->with(['chore:id,name,points', 'familyMember:id,name,nickname'])
            ->get();

        $done = ChoreCompletion::whereIn('chore_assignment_id', $assignments->pluck('id'))
            ->whereDate('completed_date', $today->toDateString())
            ->pluck('chore_assignment_id')
            ->flip();

        return $assignments
            ->reject(fn ($a) => $done->has($a->id))
            ->map(fn ($a) => [
                'title' => $a->chore->name,
                'who' => $member ? null : $a->familyMember->display_name,
            ])
            ->values()
            ->all();
    }

    private function meals(int $ownerId, Carbon $today): array
    {
        return MealPlanEntry::where('user_id', $ownerId)
            ->whereDate('date', $today->toDateString())
            ->orderByMealType()
            ->get()
            ->map(fn ($m) => ['type' => ucfirst($m->meal_type->value), 'name' => $m->name])
            ->all();
    }

    private function events(int $ownerId, Carbon $today, string $timezone): array
    {
        $start = $today->copy()->utc();
        $end = $today->copy()->endOfDay()->utc();

        $events = Event::where('user_id', $ownerId)
            ->where('starts_at', '<=', $end)
            ->where(fn ($q) => $q->where('ends_at', '>=', $start)->orWhereNotNull('rrule'))
            ->get();

        $regular = $events->reject(fn ($e) => $e->isRecurring());
        $occurrences = $this->recurringService->expandEventsForRange(
            $events->filter(fn ($e) => $e->isRecurring()),
            $start,
            $end,
            $timezone,
        );

        return $regular
            ->map(fn ($e) => [
                'title' => $e->title,
                'time' => $e->is_all_day ? 'All day' : $e->starts_at->copy()->setTimezone($timezone)->format('g:i A'),
                'sort' => $e->starts_at->timestamp,
            ])
            ->concat(collect($occurrences)->map(fn ($o) => [
                'title' => $o['title'],
                'time' => $o['is_all_day'] ? 'All day' : Carbon::parse($o['starts_at'])->setTimezone($timezone)->format('g:i A'),
                'sort' => Carbon::parse($o['starts_at'])->timestamp,
            ]))
            ->sortBy('sort')
            ->map(fn ($e) => ['title' => $e['title'], 'time' => $e['time']])
            ->values()
            ->all();
    }

    private function bills(User $user, int $ownerId, Carbon $today): array
    {
        $window = (int) $user->notificationPreference('bills_due_days');

        $bills = Bill::where('user_id', $ownerId)->where('is_active', true)->get();

        $due = $bills
            ->map(fn (Bill $b) => [$b, $b->nextDueDate($today)])
            ->filter(fn ($pair) => $pair[1]->between($today, $today->copy()->addDays($window)->endOfDay()));

        $paid = $due->isEmpty() ? collect() : Expense::whereIn('bill_id', $due->pluck(0)->pluck('id'))
            ->whereYear('date', $today->year)
            ->whereMonth('date', $today->month)
            ->pluck('bill_id')
            ->flip();

        return $due
            ->reject(fn ($pair) => $paid->has($pair[0]->id))
            ->sortBy(fn ($pair) => $pair[1]->timestamp)
            ->map(fn ($pair) => [
                'name' => $pair[0]->name,
                'amount' => $pair[0]->amount,
                'due' => $pair[1]->isSameDay($today) ? 'today' : $pair[1]->format('D, M j'),
            ])
            ->values()
            ->all();
    }
}
