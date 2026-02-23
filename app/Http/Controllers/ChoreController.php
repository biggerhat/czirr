<?php

namespace App\Http\Controllers;

use App\Models\Chore;
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
        $ownerId = $user->id;

        // If user is a linked family member, show the family owner's chores
        $linkedMember = FamilyMember::where('linked_user_id', $user->id)->first();
        if ($linkedMember) {
            $ownerId = $linkedMember->user_id;
        }

        $chores = Chore::where('user_id', $ownerId)
            ->with('assignments.familyMember')
            ->orderBy('name')
            ->get();

        $familyMembers = FamilyMember::where('user_id', $ownerId)
            ->orderBy('name')
            ->get();

        $user = $request->user();

        return Inertia::render('chores/Index', [
            'chores' => $chores,
            'familyMembers' => $familyMembers,
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
        ]);

        $chore = $request->user()->chores()->create($validated);

        return response()->json($chore, 201);
    }

    public function update(Request $request, Chore $chore): JsonResponse
    {
        if ($chore->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $chore->update($validated);

        return response()->json($chore);
    }

    public function destroy(Request $request, Chore $chore): JsonResponse
    {
        if ($chore->user_id !== $request->user()->id) {
            abort(403);
        }

        $chore->delete();

        return response()->json(null, 204);
    }
}
