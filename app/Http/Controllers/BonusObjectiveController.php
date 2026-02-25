<?php

namespace App\Http\Controllers;

use App\Models\BonusObjective;
use App\Models\FamilyMember;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BonusObjectiveController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'points' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $ownerId = $request->user()->familyOwnerId();

        $objective = BonusObjective::create([
            ...$validated,
            'user_id' => $ownerId,
        ]);

        return response()->json($objective, 201);
    }

    public function update(Request $request, BonusObjective $bonusObjective): JsonResponse
    {
        $ownerId = $request->user()->familyOwnerId();
        if ($bonusObjective->user_id !== $ownerId) {
            abort(403);
        }

        if ($bonusObjective->claimed_by !== null) {
            abort(422, 'Cannot edit a claimed objective.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'points' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $bonusObjective->update($validated);

        return response()->json($bonusObjective);
    }

    public function destroy(Request $request, BonusObjective $bonusObjective): JsonResponse
    {
        $ownerId = $request->user()->familyOwnerId();
        if ($bonusObjective->user_id !== $ownerId) {
            abort(403);
        }

        $bonusObjective->delete();

        return response()->json(null, 204);
    }

    public function claim(Request $request, BonusObjective $bonusObjective): JsonResponse
    {
        $ownerId = $request->user()->familyOwnerId();
        if ($bonusObjective->user_id !== $ownerId) {
            abort(403);
        }

        if ($bonusObjective->claimed_by !== null) {
            abort(422, 'Already claimed.');
        }

        $validated = $request->validate([
            'family_member_id' => ['required', 'integer', 'exists:family_members,id'],
        ]);

        // Verify the family member belongs to this family
        $member = FamilyMember::where('id', $validated['family_member_id'])
            ->where('user_id', $ownerId)
            ->firstOrFail();

        $bonusObjective->update([
            'claimed_by' => $member->id,
            'claimed_at' => Carbon::now(),
        ]);

        $bonusObjective->load('claimedByMember');

        return response()->json($bonusObjective);
    }
}
