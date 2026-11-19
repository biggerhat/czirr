<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CalendarFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CalendarFeedController extends Controller
{
    /** Public, token-authenticated feed for calendar apps. */
    public function show(string $token, CalendarFeedService $feed): Response
    {
        $user = User::where('calendar_token', $token)->firstOrFail();

        return response($feed->build($user), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="family.ics"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /** Create or rotate the token. The old URL stops working immediately. */
    public function regenerate(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['calendar_token' => Str::random(48)])->save();

        return response()->json(['url' => $this->url($user)]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->forceFill(['calendar_token' => null])->save();

        return response()->json(['url' => null]);
    }

    public static function url(User $user): ?string
    {
        return $user->calendar_token
            ? route('calendar.feed', ['token' => $user->calendar_token])
            : null;
    }
}
