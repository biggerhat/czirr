<?php

namespace App\Http\Controllers;

use App\Models\Chore;
use App\Models\ChoreAssignment;
use App\Models\ChoreCompletion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChoreCompletionController extends Controller
{
    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'chore_assignment_id' => ['required', 'integer', 'exists:chore_assignments,id'],
            'date' => ['required', 'date'],
        ]);

        $assignment = ChoreAssignment::findOrFail($validated['chore_assignment_id']);
        $chore = Chore::findOrFail($assignment->chore_id);

        // Verify ownership
        $ownerId = $request->user()->familyOwnerId();
        if ($chore->user_id !== $ownerId) {
            abort(403);
        }

        $existing = ChoreCompletion::where('chore_assignment_id', $assignment->id)
            ->whereDate('completed_date', $validated['date'])
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json([
                'completed' => false,
                'points_earned' => 0,
            ]);
        }

        $completion = ChoreCompletion::create([
            'chore_assignment_id' => $assignment->id,
            'family_member_id' => $assignment->family_member_id,
            'completed_date' => $validated['date'],
            'points_earned' => $chore->points,
        ]);

        return response()->json([
            'completed' => true,
            'points_earned' => $completion->points_earned,
        ]);
    }
}
