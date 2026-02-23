<?php

namespace App\Http\Controllers;

use App\Models\Income;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncomeController extends Controller
{
    public function __construct(
        private BudgetService $budgetService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'frequency' => ['required', 'string', 'in:once,weekly,biweekly,monthly,quarterly,yearly'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $income = $this->budgetService->createIncomeWithEvent($request->user(), $validated);

        return response()->json($income, 201);
    }

    public function update(Request $request, Income $income): JsonResponse
    {
        if ($income->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'frequency' => ['required', 'string', 'in:once,weekly,biweekly,monthly,quarterly,yearly'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $income = $this->budgetService->updateIncomeEvent($income, $validated);

        return response()->json($income);
    }

    public function destroy(Request $request, Income $income): JsonResponse
    {
        if ($income->user_id !== $request->user()->id) {
            abort(403);
        }

        $this->budgetService->deleteIncomeEvent($income);
        $income->delete();

        return response()->json(null, 204);
    }
}
