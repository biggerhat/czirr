<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use App\Services\RecurringEventService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EventController extends Controller
{
    public function __construct(
        private RecurringEventService $recurringService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $timezone = $request->input('timezone', 'UTC');
        $start = Carbon::parse($request->input('start'), $timezone)->utc();
        $end = Carbon::parse($request->input('end'), $timezone)->utc();

        $userId = $request->user()->id;

        $version = Cache::get("calendar:v:{$userId}", 0);
        $cacheKey = "calendar:{$userId}:{$version}:".md5("{$start->timestamp}:{$end->timestamp}");

        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($userId, $start, $end, $timezone) {
            // Pre-fetch attended event IDs to avoid repeated correlated subqueries
            $attendeeEventIds = DB::table('event_attendees')
                ->where('user_id', $userId)
                ->pluck('event_id');

            // Single query: all relevant events (regular + recurring masters + exceptions)
            // - Regular/exceptions: starts_at <= end AND ends_at >= start
            // - Recurring masters: starts_at <= end (no end filter — occurrences may extend beyond)
            $allUserEvents = Event::with(['owner:id,name,email', 'attendees:id,name,email', 'familyMembers:id,name,nickname,color', 'eventType:id,name'])
                ->where(function ($query) use ($userId, $attendeeEventIds) {
                    $query->where('user_id', $userId);
                    if ($attendeeEventIds->isNotEmpty()) {
                        $query->orWhereIn('id', $attendeeEventIds);
                    }
                })
                ->where('starts_at', '<=', $end)
                ->where(function ($query) use ($start) {
                    $query->where('ends_at', '>=', $start)
                        ->orWhereNotNull('rrule');
                })
                ->orderBy('starts_at')
                ->get();

            // Partition into regular events, recurring masters, and exceptions
            $regularEvents = [];
            $masters = collect();
            $exceptions = [];

            foreach ($allUserEvents as $event) {
                if ($event->isRecurring()) {
                    $masters->push($event);
                } elseif ($event->isException()) {
                    $event->setAttribute('is_occurrence', true);
                    $event->setAttribute('is_exception', true);
                    $event->setAttribute('master_event_id', $event->recurring_event_id);
                    $event->setAttribute('occurrence_start', $event->original_start?->toIso8601String());
                    $exceptions[] = $event;
                } else {
                    $regularEvents[] = $event;
                }
            }

            // Expand recurring masters into virtual occurrences
            $occurrences = $this->recurringService->expandEventsForRange($masters, $start, $end, $timezone);

            // Merge and sort
            return collect($regularEvents)
                ->concat($occurrences)
                ->concat($exceptions)
                ->sortBy('starts_at')
                ->values()
                ->toArray();
        });

        return response()->json($data);
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var Event $event */
        $event = $request->user()->events()->create($request->safe()->only([
            'title', 'description', 'starts_at', 'ends_at', 'is_all_day', 'rrule', 'event_type_id',
        ]));

        $event->attendees()->sync($validated['attendee_ids'] ?? []);
        $event->familyMembers()->sync($validated['family_member_ids'] ?? []);

        $event->load(['owner:id,name,email', 'attendees:id,name,email', 'familyMembers:id,name,nickname,color', 'eventType:id,name']);

        return response()->json($event, 201);
    }

    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        $validated = $request->validated();
        $editMode = $validated['edit_mode'] ?? 'all';
        $occurrenceStart = $validated['occurrence_start'] ?? null;

        $data = $request->safe()->only([
            'title', 'description', 'starts_at', 'ends_at', 'is_all_day', 'rrule', 'event_type_id',
        ]);

        $familyMemberIds = $validated['family_member_ids'] ?? [];

        if ($event->isRecurring() && $occurrenceStart) {
            if ($editMode === 'single') {
                $result = $this->recurringService->editSingleOccurrence($event, $occurrenceStart, $data);
                $result->familyMembers()->sync($familyMemberIds);
                $result->load(['owner:id,name,email', 'attendees:id,name,email', 'familyMembers:id,name,nickname,color', 'eventType:id,name']);

                return response()->json($result);
            }

            if ($editMode === 'future') {
                $timezone = $request->input('timezone', 'UTC');
                $result = $this->recurringService->editThisAndFuture($event, $occurrenceStart, $data, $timezone);
                $result->familyMembers()->sync($familyMemberIds);
                $result->load(['owner:id,name,email', 'attendees:id,name,email', 'familyMembers:id,name,nickname,color', 'eventType:id,name']);

                return response()->json($result);
            }
        }

        // 'all' mode or non-recurring event
        $event->update($data);

        if (array_key_exists('attendee_ids', $validated)) {
            $event->attendees()->sync($validated['attendee_ids'] ?? []);
        }
        $event->familyMembers()->sync($familyMemberIds);

        $event->load(['owner:id,name,email', 'attendees:id,name,email', 'familyMembers:id,name,nickname,color', 'eventType:id,name']);

        return response()->json($event);
    }

    public function destroy(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('delete', $event);

        $deleteMode = $request->input('delete_mode', 'all');
        $occurrenceStart = $request->input('occurrence_start');
        $timezone = $request->input('timezone', 'UTC');

        if ($event->isRecurring() && $occurrenceStart) {
            if ($deleteMode === 'single') {
                $this->recurringService->deleteSingleOccurrence($event, $occurrenceStart);

                return response()->json(null, 204);
            }

            if ($deleteMode === 'future') {
                $this->recurringService->deleteThisAndFuture($event, $occurrenceStart, $timezone);

                return response()->json(null, 204);
            }
        }

        // 'all' mode or non-recurring: delete the event entirely
        $event->delete();

        return response()->json(null, 204);
    }
}
