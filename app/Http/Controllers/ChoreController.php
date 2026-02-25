<?php

namespace App\Http\Controllers;

use App\Models\Chore;
use App\Models\ChoreCompletion;
use App\Models\FamilyMember;
use Carbon\Carbon;
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

        $today = Carbon::today()->toDateString();
        $todaysCompletions = ChoreCompletion::whereHas('assignment.chore', fn ($q) => $q->where('user_id', $ownerId))
            ->where('completed_date', $today)
            ->get();

        return Inertia::render('chores/Index', [
            'chores' => $chores,
            'familyMembers' => $familyMembers,
            'todaysCompletions' => $todaysCompletions,
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
