<?php

namespace App\Http\Controllers;

use App\Enums\ListVisibility;
use App\Models\Bill;
use App\Models\ChoreAssignment;
use App\Models\Expense;
use App\Models\FamilyList;
use App\Models\MealPlanEntry;
use App\Services\ChoreScoreService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $today = Carbon::today();
        $user = $request->user();
        $ownerId = $user->familyOwnerId();
        $linkedMember = $user->linkedFamilyMember();

        $can = [
            'viewBills' => $user->can('budgeting.view'),
            'viewEvents' => $user->can('events.view'),
            'viewMeals' => $user->can('meal-plans.view'),
            'viewChores' => $user->can('chores.view'),
            'viewLists' => $user->can('lists.view'),
        ];

        $todaysBills = collect();
        if ($can['viewBills']) {
            $bills = Bill::where('user_id', $ownerId)
                ->with('category')
                ->where('is_active', true)
                ->get();

            $dueTodayBills = $bills->map(function (Bill $bill) use ($today) {
                $bill->setAttribute('next_due_date', $bill->nextDueDate($today)->toDateString());

                return $bill;
            })->filter(fn (Bill $bill) => Carbon::parse($bill->getAttribute('next_due_date'))->isSameDay($today));

            // Batch-load payment status (1 query instead of N)
            $paidBillIds = $dueTodayBills->isNotEmpty()
                ? Expense::whereIn('bill_id', $dueTodayBills->pluck('id'))
                    ->whereYear('date', $today->year)
                    ->whereMonth('date', $today->month)
                    ->distinct()
                    ->pluck('bill_id')
                    ->flip()
                : collect();

            $todaysBills = $dueTodayBills->map(function (Bill $bill) use ($paidBillIds) {
                $bill->setAttribute('is_paid_this_month', $paidBillIds->has($bill->id));

                return $bill;
            })->sortBy('next_due_date')->values();
        }

        $todaysChores = collect();
        $scoreboardSummary = [];
        if ($can['viewChores']) {
            $todaysChores = ChoreAssignment::where('day_of_week', Carbon::now()->dayOfWeek)
                ->whereHas('chore', fn ($q) => $q->where('user_id', $ownerId)->where('is_active', true))
                ->with(['chore', 'familyMember'])
                ->get();

            $scoreService = app(ChoreScoreService::class);
            $weeklyScores = $scoreService->getWeeklyScores($ownerId);
            $scoreboardSummary = $weeklyScores->take(3)->values()->all();
        }

        $todaysMeals = collect();
        if ($can['viewMeals']) {
            $todaysMeals = MealPlanEntry::where('user_id', $ownerId)
                ->whereDate('date', $today)
                ->orderByRaw("FIELD(meal_type, 'breakfast', 'lunch', 'dinner', 'snack')")
                ->get(['id', 'date', 'meal_type', 'name', 'recipe_id']);
        }

        $pinnedLists = collect();
        if ($can['viewLists']) {
            $pinnedListsQuery = $linkedMember
                ? FamilyList::where('is_pinned', true)->where(function ($query) use ($user, $linkedMember, $ownerId) {
                    $query->where('user_id', $user->id)
                        ->orWhere(function ($q) use ($linkedMember, $ownerId) {
                            $q->where('user_id', $ownerId)
                                ->where(function ($vis) use ($linkedMember) {
                                    $vis->where('visibility', ListVisibility::Everyone->value)
                                        ->when($linkedMember->role?->value === 'parent', fn ($q) => $q->orWhere('visibility', ListVisibility::Parents->value))
                                        ->when($linkedMember->role?->value === 'child', fn ($q) => $q->orWhere('visibility', ListVisibility::Children->value))
                                        ->orWhere(function ($specific) use ($linkedMember) {
                                            $specific->where('visibility', ListVisibility::Specific->value)
                                                ->whereHas('members', fn ($q) => $q->where('family_member_id', $linkedMember->id));
                                        });
                                });
                        });
                })
                : $user->familyLists()->where('is_pinned', true);

            $pinnedLists = $pinnedListsQuery
                ->with(['items' => fn ($q) => $q->orderBy('is_completed')->orderBy('name')])
                ->orderBy('name')
                ->get();
        }

        return Inertia::render('Dashboard', [
            'todaysBills' => $todaysBills,
            'todaysChores' => $todaysChores,
            'todaysMeals' => $todaysMeals,
            'pinnedLists' => $pinnedLists,
            'scoreboardSummary' => $scoreboardSummary,
            'can' => $can,
        ]);
    }
}
