<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $ownerId = $request->user()->familyOwnerId();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'budget_category_id' => ['required', Rule::exists('budget_categories', 'id')->where('user_id', $ownerId)],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'bill_id' => ['nullable', Rule::exists('bills', 'id')->where('user_id', $ownerId)],
        ]);

        $owner = $request->user()->familyOwner();
        $expense = $owner->expenses()->create($validated);
        $expense->load('category');

        return response()->json($expense, 201);
    }

    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        if ($expense->user_id !== $request->user()->familyOwnerId()) {
            abort(403);
        }

        $expense->delete();

        return response()->json(null, 204);
    }
}
