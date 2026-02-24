<?php

namespace App\Http\Controllers;

use App\Models\EventType;
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

        $this->budgetService->seedDefaultCategories($user);

        // Seed default event types if none exist yet
        if (! EventType::whereNull('user_id')->exists()) {
            (new EventTypeSeeder)->run();
        }

        $eventTypes = EventType::availableTo($user->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $familyMembers = $user->familyMembers()
            ->select('id', 'user_id', 'name', 'color')
            ->orderBy('name')
            ->get();

        $budgetCategories = $user->budgetCategories()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('calendar/Index', [
            'familyMembers' => $familyMembers,
            'budgetCategories' => $budgetCategories,
            'eventTypes' => $eventTypes,
        ]);
    }
}
