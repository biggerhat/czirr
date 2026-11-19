<?php

namespace App\Http\Controllers;

use App\Models\Chore;
use App\Models\ChoreCompletion;
use App\Models\FamilyMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChoreController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $ownerId = $user->familyOwnerId();

        $chores = Chore::where('user_id', $ownerId)
            ->with('assignments.familyMember')
            ->orderBy('name')
            ->get();

        $familyMembers = FamilyMember::where('user_id', $ownerId)
            ->orderBy('name')
            ->get();

        $today = $request->user()->localNow()->toDateString();
        // Include pending ones so a kid's tick stays ticked while it awaits approval.
        $todaysCompletions = ChoreCompletion::withoutGlobalScope('approved')
            ->whereHas('assignment.chore', fn ($q) => $q->where('user_id', $ownerId))
            ->where('completed_date', $today)
            ->get();

        $pendingApprovals = $user->can('chores.edit')
            ? ChoreCompletion::withoutGlobalScope('approved')
                ->where('status', ChoreCompletion::PENDING)
                ->whereHas('assignment.chore', fn ($q) => $q->where('user_id', $ownerId))
                ->with(['assignment.chore:id,name', 'familyMember:id,name,nickname,color'])
                ->orderBy('completed_date')
                ->get()
                ->map(fn (ChoreCompletion $c) => [
                    'id' => $c->id,
                    'chore' => $c->assignment->chore->name,
                    'member' => $c->familyMember->display_name,
                    'color' => $c->familyMember->color,
                    'date' => $c->completed_date->toDateString(),
                    'points' => $c->points_earned,
                ])->values()
            : [];

        return Inertia::render('chores/Index', [
            'chores' => $chores,
            'familyMembers' => $familyMembers,
            'todaysCompletions' => $todaysCompletions,
            'pendingApprovals' => $pendingApprovals,
            'can' => [
                'create' => $user->can('chores.create'),
                'edit' => $user->can('chores.edit'),
                'delete' => $user->can('chores.delete'),
                'assign' => $user->can('chores.assign'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'points' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'requires_approval' => ['sometimes', 'boolean'],
        ]);

        $owner = $request->user()->familyOwner();
        $chore = $owner->chores()->create($validated);

        return response()->json($chore, 201);
    }

    public function update(Request $request, Chore $chore): JsonResponse
    {
        if ($chore->user_id !== $request->user()->familyOwnerId()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'points' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'requires_approval' => ['sometimes', 'boolean'],
        ]);

        $chore->update($validated);

        return response()->json($chore);
    }

    public function destroy(Request $request, Chore $chore): JsonResponse
    {
        if ($chore->user_id !== $request->user()->familyOwnerId()) {
            abort(403);
        }

        $chore->delete();

        return response()->json(null, 204);
    }
}
