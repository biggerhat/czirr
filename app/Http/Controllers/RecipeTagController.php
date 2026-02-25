<?php

namespace App\Http\Controllers;

use App\Models\RecipeTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecipeTagController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $owner = $request->user()->familyOwner();

        $exists = RecipeTag::where('user_id', $owner->id)
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return response()->json(['errors' => ['name' => ['You already have a tag with this name.']]], 422);
        }

        $tag = $owner->recipeTags()->create($validated);

        return response()->json($tag, 201);
    }

    public function destroy(Request $request, RecipeTag $recipeTag): JsonResponse
    {
        if ($recipeTag->user_id === null || $recipeTag->user_id !== $request->user()->familyOwnerId()) {
            abort(403);
        }

        $recipeTag->recipes()->detach();
        $recipeTag->delete();

        return response()->json(null, 204);
    }
}
