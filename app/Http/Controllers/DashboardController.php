<?php

namespace App\Http\Controllers;

use App\Enums\ListVisibility;
use App\Models\Bill;
use App\Models\ChoreAssignment;
use App\Models\FamilyList;
use App\Models\MealPlanEntry;
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

        $bills = Bill::where('user_id', $ownerId)
            ->with('category')
            ->where('is_active', true)
            ->get();

        $upcomingBills = $bills->map(function (Bill $bill) use ($today) {
            $bill->setAttribute('next_due_date', $bill->nextDueDate($today)->toDateString());
            $bill->setAttribute('is_paid_this_month', $bill->isPaidForMonth($today));

            return $bill;
        })
            ->filter(fn (Bill $bill) => Carbon::parse($bill->getAttribute('next_due_date'))->diffInDays($today, false) >= -14)
            ->sortBy('next_due_date')
            ->values();

        $todaysChores = ChoreAssignment::where('day_of_week', Carbon::now()->dayOfWeek)
            ->whereHas('chore', fn ($q) => $q->where('user_id', $ownerId)->where('is_active', true))
            ->with(['chore', 'familyMember'])
            ->get();

        // Today's meals
        $todaysMeals = MealPlanEntry::where('user_id', $ownerId)
            ->whereDate('date', $today)
            ->orderByRaw("FIELD(meal_type, 'breakfast', 'lunch', 'dinner', 'snack')")
            ->get(['id', 'date', 'meal_type', 'name', 'recipe_id']);

        // Pinned lists — respect family visibility
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

        return Inertia::render('Dashboard', [
            'upcomingBills' => $upcomingBills,
            'todaysChores' => $todaysChores,
            'todaysMeals' => $todaysMeals,
            'pinnedLists' => $pinnedLists,
        ]);
    }
}
