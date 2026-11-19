<?php

namespace App\Http\Controllers;

use App\Models\PantryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PantryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('pantry/Index', [
            'items' => PantryItem::where('user_id', $user->familyOwnerId())
                ->orderBy('name')->get(['id', 'name']),
            'can' => ['edit' => $user->can('meal-plans.edit')],
        ]);
    }

    /** Accepts one `name` or a list of `names` (used by the quick-add chips). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required_without:names', 'string', 'max:100'],
            'names' => ['required_without:name', 'array', 'max:50'],
            'names.*' => ['nullable', 'string', 'max:100'],
        ]);

        $owner = $request->user()->familyOwner();
        $added = 0;

        foreach ($data['names'] ?? [$data['name']] as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $key = PantryItem::normalize($name);
            if ($key === '' || $owner->pantryItems()->where('name_key', $key)->exists()) {
                continue;
            }
            $owner->pantryItems()->create(['name' => $name]);
            $added++;
        }

        return response()->json(['added' => $added], 201);
    }

    public function destroy(Request $request, PantryItem $pantryItem): JsonResponse
    {
        abort_unless($pantryItem->user_id === $request->user()->familyOwnerId(), 403);

        $pantryItem->delete();

        return response()->json(null, 204);
    }
}
