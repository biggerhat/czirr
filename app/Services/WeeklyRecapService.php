<?php

namespace App\Services;

use App\Models\ChoreCompletion;
use App\Models\Expense;
use App\Models\MealPlanEntry;
use App\Models\User;
use Carbon\Carbon;

class WeeklyRecapService
{
    public function __construct(private ChoreScoreService $scores) {}

    /** @return array{leaders: list<array{name: string, points: int}>, chores_done: int, bills_paid: int, bills_total: float, meals_next_week: int} */
    public function build(User $user, ?Carbon $now = null): array
    {
        $ownerId = $user->familyOwnerId();
        $today = Carbon::instance($now ?? now())->setTimezone($user->timezone ?: 'UTC')->startOfDay();
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);

        $canChores = $user->can('chores.view');
        $leaders = $canChores
            ? $this->scores->getWeeklyScores($ownerId, $weekStart)
                ->filter(fn ($s) => $s['weekly_total'] > 0)
                ->take(3)
                ->map(fn ($s) => ['name' => $s['name'], 'points' => $s['weekly_total']])
                ->values()->all()
            : [];

        $choresDone = $canChores
            ? ChoreCompletion::whereHas('familyMember', fn ($q) => $q->where('user_id', $ownerId))
                ->whereBetween('completed_date', [$weekStart->toDateString(), $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->toDateString()])
                ->count()
            : 0;

        $bills = $user->can('budgeting.view')
            ? Expense::where('user_id', $ownerId)->whereNotNull('bill_id')
                ->whereBetween('date', [$weekStart->toDateString(), $today->toDateString()])
            : null;

        $nextMonday = $weekStart->copy()->addWeek();

        return [
            'leaders' => $leaders,
            'chores_done' => $choresDone,
            'bills_paid' => $bills?->count() ?? 0,
            'bills_total' => (float) ($bills?->sum('amount') ?? 0),
            'meals_next_week' => $user->can('meal-plans.view')
                ? MealPlanEntry::where('user_id', $ownerId)
                    ->whereBetween('date', [$nextMonday->toDateString(), $nextMonday->copy()->addDays(6)->toDateString()])
                    ->count()
                : 0,
        ];
    }

    public function isEmpty(array $recap): bool
    {
        return $recap['leaders'] === [] && $recap['chores_done'] === 0 && $recap['bills_paid'] === 0;
    }
}
