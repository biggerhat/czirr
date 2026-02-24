<?php

namespace App\Http\Controllers;

use App\Models\EventType;
use App\Models\FamilyMember;
use App\Services\BudgetService;
use Database\Seeders\EventTypeSeeder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __construct(
        private BudgetService $budgetService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $ownerId = $user->familyOwnerId();

        $this->budgetService->seedDefaultCategories($user);

        // Seed default event types if none exist yet
        if (! EventType::whereNull('user_id')->exists()) {
            (new EventTypeSeeder)->run();
        }

        $eventTypes = EventType::availableTo($ownerId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $familyMembers = FamilyMember::where('user_id', $ownerId)
            ->select('id', 'user_id', 'name', 'nickname', 'role', 'color')
            ->orderByRaw("CASE WHEN role = 'parent' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        $budgetCategories = $user->budgetCategories()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('calendar/Index', [
            'familyMembers' => $familyMembers,
            'budgetCategories' => $budgetCategories,
            'eventTypes' => Inertia::once(fn () => $eventTypes),
        ]);
    }
}
