<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use App\Services\RecurringEventService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $userScope = function ($query) use ($userId) {
            $query->where('user_id', $userId)
                ->orWhereHas('attendees', fn ($q) => $q->where('users.id', $userId));
        };

        // 1. Non-recurring, non-exception events in range (existing behavior)
        $regularEvents = Event::with(['owner:id,name,email', 'attendees:id,name,email', 'familyMembers'])
            ->nonRecurring()
            ->where($userScope)
            ->where('starts_at', '<=', $end)
            ->where('ends_at', '>=', $start)
            ->orderBy('starts_at')
            ->get();

        // 2. Recurring masters where starts_at <= range_end
        $masters = Event::with(['owner:id,name,email', 'attendees:id,name,email', 'familyMembers'])
            ->recurring()
            ->where($userScope)
            ->where('starts_at', '<=', $end)
            ->get();

        // 3. Expand masters into virtual occurrences
        $occurrences = $this->recurringService->expandEventsForRange($masters, $start, $end, $timezone);

        // 4. Exception instances in range
        $exceptions = Event::with(['owner:id,name,email', 'attendees:id,name,email', 'familyMembers'])
            ->exceptions()
            ->where($userScope)
            ->where('starts_at', '<=', $end)
            ->where('ends_at', '>=', $start)
            ->orderBy('starts_at')
            ->get()
            ->map(function (Event $event) {
                $event->setAttribute('is_occurrence', true);
                $event->setAttribute('is_exception', true);
                $event->setAttribute('master_event_id', $event->recurring_event_id);
                $event->setAttribute('occurrence_start', $event->original_start?->toIso8601String());

                return $event;
            });

        // 5. Merge and sort by starts_at
        $allEvents = collect($regularEvents)
            ->concat($occurrences)
            ->concat($exceptions)
            ->sortBy('starts_at')
            ->values();

        return response()->json($allEvents);
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var Event $event */
        $event = $request->user()->events()->create($request->safe()->only([
            'title', 'description', 'starts_at', 'ends_at', 'is_all_day', 'rrule',
        ]));

        $event->attendees()->sync($validated['attendee_ids'] ?? []);
        $event->familyMembers()->sync($validated['family_member_ids'] ?? []);

        $event->load(['owner:id,name,email', 'attendees:id,name,email', 'familyMembers']);

        return response()->json($event, 201);
    }

    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        $validated = $request->validated();
        $editMode = $validated['edit_mode'] ?? 'all';
        $occurrenceStart = $validated['occurrence_start'] ?? null;

        $data = $request->safe()->only([
            'title', 'description', 'starts_at', 'ends_at', 'is_all_day', 'rrule',
        ]);

        $familyMemberIds = $validated['family_member_ids'] ?? [];

        if ($event->isRecurring() && $occurrenceStart) {
            if ($editMode === 'single') {
                $result = $this->recurringService->editSingleOccurrence($event, $occurrenceStart, $data);
                $result->familyMembers()->sync($familyMemberIds);
                $result->load(['owner:id,name,email', 'attendees:id,name,email', 'familyMembers']);

                return response()->json($result);
            }

            if ($editMode === 'future') {
                $timezone = $request->input('timezone', 'UTC');
                $result = $this->recurringService->editThisAndFuture($event, $occurrenceStart, $data, $timezone);
                $result->familyMembers()->sync($familyMemberIds);
                $result->load(['owner:id,name,email', 'attendees:id,name,email', 'familyMembers']);

                return response()->json($result);
            }
        }

        // 'all' mode or non-recurring event
        $event->update($data);

        if (array_key_exists('attendee_ids', $validated)) {
            $event->attendees()->sync($validated['attendee_ids'] ?? []);
        }
        $event->familyMembers()->sync($familyMemberIds);

        $event->load(['owner:id,name,email', 'attendees:id,name,email', 'familyMembers']);

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
