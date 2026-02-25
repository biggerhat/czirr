<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Enums\Permission;
use App\Models\Event;
use App\Models\FamilyMember;
use App\Models\User;
use App\Notifications\EventStartingSoonNotification;
use App\Services\RecurringEventService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendEventReminders extends Command
{
    protected $signature = 'notifications:event-reminders';

    protected $description = 'Send reminders for events starting in ~15 minutes';

    public function handle(RecurringEventService $recurringEventService): void
    {
        $now = Carbon::now();
        $windowStart = $now->copy()->addMinutes(13);
        $windowEnd = $now->copy()->addMinutes(17);

        $this->processNonRecurringEvents($windowStart, $windowEnd);
        $this->processRecurringEvents($recurringEventService, $windowStart, $windowEnd);

        $this->info('Event reminders processed.');
    }

    private function processNonRecurringEvents(Carbon $windowStart, Carbon $windowEnd): void
    {
        Event::whereNull('rrule')
            ->whereNull('recurring_event_id')
            ->where('is_all_day', false)
            ->whereBetween('starts_at', [$windowStart, $windowEnd])
            ->with(['owner', 'attendees', 'familyMembers'])
            ->each(function (Event $event) {
                $cacheKey = 'event-reminder-'.$event->id.'-'.$event->starts_at->toIso8601String();
                if (Cache::has($cacheKey)) {
                    return;
                }

                $this->notifyForEvent([
                    'id' => $event->id,
                    'title' => $event->title,
                    'starts_at' => $event->starts_at->toIso8601String(),
                ], $event->user_id, $event->attendees, $event->familyMembers);

                Cache::put($cacheKey, true, now()->addHour());
            });
    }

    private function processRecurringEvents(RecurringEventService $service, Carbon $windowStart, Carbon $windowEnd): void
    {
        $masters = Event::whereNotNull('rrule')
            ->where('is_all_day', false)
            ->with(['owner', 'attendees', 'familyMembers'])
            ->get();

        $occurrences = $service->expandEventsForRange($masters, $windowStart, $windowEnd, 'UTC');

        foreach ($occurrences as $occurrence) {
            $cacheKey = 'event-reminder-'.$occurrence['master_event_id'].'-'.$occurrence['starts_at'];
            if (Cache::has($cacheKey)) {
                continue;
            }

            $attendees = collect($occurrence['attendees'] ?? []);
            $familyMembers = collect($occurrence['family_members'] ?? []);

            $this->notifyForEvent([
                'id' => $occurrence['master_event_id'],
                'title' => $occurrence['title'],
                'starts_at' => $occurrence['starts_at'],
            ], $occurrence['user_id'], $attendees, $familyMembers);

            Cache::put($cacheKey, true, now()->addHour());
        }
    }

    private function notifyForEvent(array $eventData, int $ownerId, $attendees, $familyMembers): void
    {
        $userIds = collect([$ownerId]);

        // Add attendees (they are Users)
        foreach ($attendees as $attendee) {
            $userIds->push($attendee->id);
        }

        // Add linked users from family members
        foreach ($familyMembers as $member) {
            if ($member->linked_user_id) {
                $userIds->push($member->linked_user_id);
            }
        }

        // Also add all linked family members of the owner
        $linkedMembers = FamilyMember::where('user_id', $ownerId)
            ->whereNotNull('linked_user_id')
            ->pluck('linked_user_id');
        $userIds = $userIds->merge($linkedMembers);

        $userIds = $userIds->unique();

        User::whereIn('id', $userIds)
            ->whereHas('pushSubscriptions')
            ->with('notificationPreferences')
            ->get()
            ->filter(fn (User $user) => $user->can(Permission::EventsView->value))
            ->filter(fn (User $user) => $user->wantsPushNotification(NotificationType::EventReminders))
            ->each(fn (User $user) => $user->notify(new EventStartingSoonNotification($eventData)));
    }
}
