<?php

namespace App\Http\Controllers;

use App\Models\Cuisine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CuisineController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $owner = $request->user()->familyOwner();

        $exists = Cuisine::where('user_id', $owner->id)
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return response()->json(['errors' => ['name' => ['You already have a cuisine with this name.']]], 422);
        }

        $cuisine = $owner->cuisines()->create($validated);

        return response()->json($cuisine, 201);
    }

    public function destroy(Request $request, Cuisine $cuisine): JsonResponse
    {
        if ($cuisine->user_id === null || $cuisine->user_id !== $request->user()->familyOwnerId()) {
            abort(403);
        }

        $cuisine->recipes()->update(['cuisine_id' => null]);
        $cuisine->delete();

        return response()->json(null, 204);
    }
}
