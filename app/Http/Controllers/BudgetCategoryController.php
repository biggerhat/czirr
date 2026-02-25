<?php

namespace App\Http\Controllers;

use App\Models\BudgetCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetCategoryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:20'],
        ]);

        $owner = $request->user()->familyOwner();
        $maxSort = $owner->budgetCategories()->max('sort_order') ?? -1;
        $validated['sort_order'] = $maxSort + 1;

        $category = $owner->budgetCategories()->create($validated);

        return response()->json($category, 201);
    }

    public function update(Request $request, BudgetCategory $budgetCategory): JsonResponse
    {
        if ($budgetCategory->user_id !== $request->user()->familyOwnerId()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:20'],
        ]);

        $budgetCategory->update($validated);

        return response()->json($budgetCategory);
    }

    public function destroy(Request $request, BudgetCategory $budgetCategory): JsonResponse
    {
        if ($budgetCategory->user_id !== $request->user()->familyOwnerId()) {
            abort(403);
        }

        if ($budgetCategory->bills()->exists() || $budgetCategory->expenses()->exists()) {
            return response()->json(['message' => 'Cannot delete category with existing bills or expenses.'], 422);
        }

        $budgetCategory->delete();

        return response()->json(null, 204);
    }
}
