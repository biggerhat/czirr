<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function __construct(
        private BudgetService $budgetService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'budget_category_id' => ['required', 'exists:budget_categories,id'],
            'start_date' => ['required', 'date'],
            'frequency' => ['required', 'string', 'in:once,weekly,biweekly,monthly,quarterly,yearly'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $bill = $this->budgetService->createBillWithEvent($request->user(), $validated);

        return response()->json($bill, 201);
    }

    public function update(Request $request, Bill $bill): JsonResponse
    {
        if ($bill->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'budget_category_id' => ['required', 'exists:budget_categories,id'],
            'start_date' => ['required', 'date'],
            'frequency' => ['required', 'string', 'in:once,weekly,biweekly,monthly,quarterly,yearly'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $bill = $this->budgetService->updateBillEvent($bill, $validated);

        return response()->json($bill);
    }

    public function destroy(Request $request, Bill $bill): JsonResponse
    {
        if ($bill->user_id !== $request->user()->id) {
            abort(403);
        }

        $this->budgetService->deleteBillEvent($bill);
        $bill->delete();

        return response()->json(null, 204);
    }
}
