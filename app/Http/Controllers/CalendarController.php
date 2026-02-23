<?php

namespace App\Http\Controllers;

use App\Services\BudgetService;
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

        $familyMembers = $user->familyMembers()
            ->select('id', 'user_id', 'name', 'color')
            ->orderBy('name')
            ->get();

        $budgetCategories = $user->budgetCategories()
            ->ordered()
            ->get();

        return Inertia::render('calendar/Index', [
            'familyMembers' => $familyMembers,
            'budgetCategories' => $budgetCategories,
        ]);
    }
}
