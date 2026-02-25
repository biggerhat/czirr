<?php

namespace App\Http\Controllers;

use App\Models\EventType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventTypeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
        ]);

        $ownerId = $request->user()->familyOwnerId();

        $exists = EventType::where('user_id', $ownerId)
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return response()->json(['errors' => ['name' => ['You already have an event type with this name.']]], 422);
        }

        $eventType = EventType::create([
            'user_id' => $ownerId,
            'name' => $validated['name'],
        ]);

        return response()->json($eventType, 201);
    }
}
