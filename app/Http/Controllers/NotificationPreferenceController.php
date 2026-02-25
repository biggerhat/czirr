<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferenceController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\NotificationPreference> $preferences */
        $preferences = $user->notificationPreferences->keyBy('type');

        $types = collect(NotificationType::cases())
            ->filter(fn (NotificationType $type) => $user->can($type->requiredPermission()->value))
            ->map(fn (NotificationType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'enabled' => isset($preferences[$type->value])
                    ? $preferences[$type->value]->enabled
                    : true,
            ])
            ->values()
            ->all();

        return Inertia::render('settings/Notifications', [
            'notificationTypes' => $types,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.type' => ['required', 'string', 'in:'.implode(',', array_column(NotificationType::cases(), 'value'))],
            'preferences.*.enabled' => ['required', 'boolean'],
        ]);

        $user = $request->user();

        foreach ($validated['preferences'] as $pref) {
            $type = NotificationType::from($pref['type']);

            if (! $user->can($type->requiredPermission()->value)) {
                continue;
            }

            $user->notificationPreferences()->updateOrCreate(
                ['type' => $type->value],
                ['enabled' => $pref['enabled']],
            );
        }

        return back()->with('success', 'Notification preferences updated.');
    }
}
