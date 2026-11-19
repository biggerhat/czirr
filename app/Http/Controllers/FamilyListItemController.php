<?php

namespace App\Http\Controllers;

use App\Models\FamilyList;
use App\Models\FamilyListItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyListItemController extends Controller
{
    public function store(Request $request, FamilyList $familyList): JsonResponse
    {
        $this->authorizeListAccess($request, $familyList);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:50'],
        ]);

        $maxPosition = $familyList->items()->max('position') ?? -1;

        $item = $familyList->items()->create([
            'name' => $validated['name'],
            'quantity' => $validated['quantity'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'category' => $validated['category'] ?? null,
            'position' => $maxPosition + 1,
        ]);

        return response()->json($item, 201);
    }

    public function update(Request $request, FamilyListItem $familyListItem): JsonResponse
    {
        $this->authorizeListAccess($request, $familyListItem->familyList);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $familyListItem->update($validated);

        return response()->json($familyListItem);
    }

    public function toggleComplete(Request $request, FamilyListItem $familyListItem): JsonResponse
    {
        $this->authorizeListAccess($request, $familyListItem->familyList);

        $familyListItem->update([
            'is_completed' => ! $familyListItem->is_completed,
        ]);

        return response()->json($familyListItem);
    }

    public function clearCompleted(Request $request, FamilyList $familyList): JsonResponse
    {
        $this->authorizeListAccess($request, $familyList);

        $familyList->items()->where('is_completed', true)->delete();

        return response()->json(null, 204);
    }

    public function destroy(Request $request, FamilyListItem $familyListItem): JsonResponse
    {
        $this->authorizeListAccess($request, $familyListItem->familyList);

        $familyListItem->delete();

        return response()->json(null, 204);
    }

    private function authorizeListAccess(Request $request, FamilyList $familyList): void
    {
        abort_unless($familyList->isVisibleTo($request->user()), 403);
    }
}
