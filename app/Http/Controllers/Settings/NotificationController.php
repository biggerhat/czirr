<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Notifications', [
            'timezone' => $user->timezone,
            'vapidPublicKey' => config('webpush.vapid.public_key'),
            'calendarFeedUrl' => \App\Http\Controllers\CalendarFeedController::url($user),
            'pushSubscribed' => $user->pushSubscriptions()->exists(),
            'preferences' => collect(User::DEFAULT_NOTIFICATION_PREFERENCES)
                ->mapWithKeys(fn ($_, $key) => [$key => $user->notificationPreference($key)]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'timezone' => ['required', 'timezone:all'],
            'digest_email' => ['required', 'boolean'],
            'digest_push' => ['required', 'boolean'],
            'alerts_push' => ['required', 'boolean'],
            'weekly_recap' => ['required', 'boolean'],
            'digest_hour' => ['required', 'integer', 'between:0,23'],
            'bills_due_days' => ['required', 'integer', 'between:0,14'],
        ]);

        $request->user()->update([
            'timezone' => $data['timezone'],
            'notification_preferences' => collect($data)->except('timezone')->all(),
        ]);

        return back();
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:500'],
            'keys.auth' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string'],
        ]);

        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['contentEncoding'] ?? 'aesgcm',
        );

        return response()->json(['subscribed' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'url']]);

        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->json(['subscribed' => false]);
    }

    /**
     * Adopt the browser's timezone, but only while the user is still on the
     * UTC default — never override a timezone someone chose on purpose.
     */
    public function detectTimezone(Request $request): JsonResponse
    {
        $validated = $request->validate(['timezone' => ['required', 'timezone:all']]);

        $user = $request->user();
        if (! $user->timezone || $user->timezone === 'UTC') {
            $user->update(['timezone' => $validated['timezone']]);
        }

        return response()->json(['timezone' => $user->timezone]);
    }
}
