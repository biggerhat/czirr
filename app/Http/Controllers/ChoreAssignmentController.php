<?php

namespace App\Http\Controllers;

use App\Models\Chore;
use App\Models\ChoreAssignment;
use App\Models\FamilyMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChoreAssignmentController extends Controller
{
    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'chore_id' => ['required', 'integer', 'exists:chores,id'],
            'family_member_id' => ['required', 'integer', 'exists:family_members,id'],
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
        ]);

        $user = $request->user();

        $chore = Chore::findOrFail($validated['chore_id']);
        if ($chore->user_id !== $user->id) {
            abort(403);
        }

        $familyMember = FamilyMember::findOrFail($validated['family_member_id']);
        if ($familyMember->user_id !== $user->id) {
            abort(403);
        }

        $existing = ChoreAssignment::where('chore_id', $validated['chore_id'])
            ->where('family_member_id', $validated['family_member_id'])
            ->where('day_of_week', $validated['day_of_week'])
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json(['removed' => true]);
        }

        $assignment = ChoreAssignment::create($validated);

        return response()->json($assignment, 201);
    }
}
