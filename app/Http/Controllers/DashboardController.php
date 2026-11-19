<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\BoardPost;
use App\Models\ChoreAssignment;
use App\Models\ChoreCompletion;
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
        $today = $request->user()->localNow()->startOfDay();
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
            $todaysChores = ChoreAssignment::where('day_of_week', $today->dayOfWeek)
                ->whereHas('chore', fn ($q) => $q->where('user_id', $ownerId)->where('is_active', true))
                ->with(['chore', 'familyMember'])
                ->get();

            $scoreService = app(ChoreScoreService::class);
            $weeklyScores = $scoreService->getWeeklyScores($ownerId, $user->localNow());
            $scoreboardSummary = $weeklyScores->take(3)->values()->all();
        }

        $todaysMeals = collect();
        if ($can['viewMeals']) {
            $todaysMeals = MealPlanEntry::where('user_id', $ownerId)
                ->whereDate('date', $today)
                ->orderByMealType()
                ->get(['id', 'date', 'meal_type', 'name', 'recipe_id']);
        }

        $pinnedLists = collect();
        if ($can['viewLists']) {
            $pinnedListsQuery = FamilyList::visibleTo($user)->where('is_pinned', true);

            $pinnedLists = $pinnedListsQuery
                ->with(['items' => fn ($q) => $q->orderBy('is_completed')->orderBy('name')])
                ->orderBy('name')
                ->get();
        }

        // Heads-up strip: things waiting on this person.
        $pendingApprovals = 0;
        if ($can['viewChores'] && $user->can('chores.edit')) {
            $pendingApprovals = ChoreCompletion::withoutGlobalScope('approved')
                ->where('status', ChoreCompletion::PENDING)
                ->whereHas('assignment.chore', fn ($q) => $q->where('user_id', $ownerId))
                ->count();
        }

        // The viewer's own savings balance (only if they have an account with activity).
        $mySavings = null;
        $member = $user->can('chores.view')
            ? \App\Models\FamilyMember::where('user_id', $ownerId)->where('linked_user_id', $user->id)->first()
            : null;
        if ($member) {
            $entries = \App\Models\SavingsEntry::where('user_id', $ownerId)->where('family_member_id', $member->id);
            if ($entries->exists()) {
                $mySavings = round((float) $entries->sum('amount'), 2);
            }
        }

        $latestPost = null;
        if ($can['viewLists']) {
            $post = BoardPost::where('user_id', $ownerId)
                ->with('author:id,name')
                ->orderByDesc('is_pinned')
                ->latest()
                ->first();
            $latestPost = $post ? [
                'body' => \Illuminate\Support\Str::limit($post->body, 140),
                'author' => $post->author?->name,
                'pinned' => $post->is_pinned,
            ] : null;
        }

        return Inertia::render('Dashboard', [
            'pendingApprovals' => $pendingApprovals,
            'mySavings' => $mySavings,
            'latestPost' => $latestPost,
            'todaysBills' => $todaysBills,
            'todaysChores' => $todaysChores,
            'todaysMeals' => $todaysMeals,
            'pinnedLists' => $pinnedLists,
            'scoreboardSummary' => $scoreboardSummary,
            'can' => $can,
        ]);
    }
}
