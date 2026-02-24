<?php

namespace App\Http\Controllers;

use App\Enums\ListVisibility;
use App\Models\FamilyList;
use App\Models\FamilyListItem;
use App\Models\FamilyMember;
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
        ]);

        $maxPosition = $familyList->items()->max('position') ?? -1;

        $item = $familyList->items()->create([
            'name' => $validated['name'],
            'quantity' => $validated['quantity'] ?? null,
            'notes' => $validated['notes'] ?? null,
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
        $user = $request->user();

        if ($familyList->user_id === $user->id) {
            return;
        }

        $linkedMember = FamilyMember::where('linked_user_id', $user->id)
            ->where('user_id', $familyList->user_id)
            ->first();

        if (! $linkedMember) {
            abort(403);
        }

        $visibility = $familyList->visibility;
        $memberRole = $linkedMember->role?->value;

        if ($visibility === ListVisibility::Everyone) {
            return;
        }

        if ($visibility === ListVisibility::Parents && $memberRole === 'parent') {
            return;
        }

        if ($visibility === ListVisibility::Children && $memberRole === 'child') {
            return;
        }

        if ($visibility === ListVisibility::Specific) {
            if ($familyList->members()->where('family_member_id', $linkedMember->id)->exists()) {
                return;
            }
        }

        abort(403);
    }
}
