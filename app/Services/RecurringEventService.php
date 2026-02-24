<?php

namespace App\Services;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\Constraint\BetweenConstraint;

class RecurringEventService
{
    private const MAX_OCCURRENCES = 366;

    /**
     * Expand recurring master events into virtual occurrences for a date range.
     */
    public function expandEventsForRange(Collection $masters, Carbon $rangeStart, Carbon $rangeEnd, string $timezone): array
    {
        $occurrences = [];
        $transformer = new ArrayTransformer;

        foreach ($masters as $master) {
            if (! $master->isRecurring()) {
                continue;
            }

            $exceptions = $master->recurrence_exceptions ?? [];
            $duration = $master->getDuration();

            try {
                $dtStart = $master->starts_at->copy()->setTimezone($timezone);
                $rule = new Rule($master->rrule, $dtStart);
                $constraint = new BetweenConstraint(
                    $rangeStart->copy()->setTimezone($timezone),
                    $rangeEnd->copy()->setTimezone($timezone),
                    true
                );

                $recurrences = $transformer->transform($rule, $constraint, self::MAX_OCCURRENCES);

                foreach ($recurrences as $recurrence) {
                    $occStart = Carbon::instance($recurrence->getStart())->setTimezone('UTC');
                    $occStartStr = $occStart->toIso8601String();

                    // Skip excluded dates
                    if (in_array($occStartStr, $exceptions)) {
                        continue;
                    }

                    $occEnd = $occStart->copy()->addSeconds($duration);

                    $occurrences[] = [
                        'id' => $master->id.'__'.$occStart->toIso8601String(),
                        'user_id' => $master->user_id,
                        'title' => $master->title,
                        'description' => $master->description,
                        'starts_at' => $occStart->toIso8601String(),
                        'ends_at' => $occEnd->toIso8601String(),
                        'is_all_day' => $master->is_all_day,
                        'rrule' => $master->rrule,
                        'source' => $master->source,
                        'event_type_id' => $master->event_type_id,
                        'is_occurrence' => true,
                        'is_exception' => false,
                        'master_event_id' => $master->id,
                        'occurrence_start' => $occStart->toIso8601String(),
                        'created_at' => $master->created_at,
                        'updated_at' => $master->updated_at,
                    ];
                }
            } catch (\Exception $e) {
                // Skip malformed RRULE
                continue;
            }
        }

        return $occurrences;
    }

    /**
     * Edit a single occurrence: create an exception instance.
     */
    public function editSingleOccurrence(Event $master, string $occurrenceStart, array $data): Event
    {
        $occDate = Carbon::parse($occurrenceStart);

        // Add to master's exclusion list
        $exceptions = $master->recurrence_exceptions ?? [];
        $exceptions[] = $occDate->toIso8601String();
        $master->update(['recurrence_exceptions' => array_values(array_unique($exceptions))]);

        // Create exception instance
        $exception = $master->replicate(['rrule', 'recurrence_exceptions']);
        $exception->fill($data);
        $exception->recurring_event_id = $master->id;
        $exception->original_start = $occDate;
        $exception->rrule = null;
        $exception->recurrence_exceptions = null;
        $exception->save();

        // Sync relationships from master if not provided
        $exception->attendees()->sync($master->attendees->pluck('id'));
        $exception->familyMembers()->sync($master->familyMembers->pluck('id'));

        $exception->load(['owner:id,name,email', 'attendees:id,name,email', 'familyMembers:id,name,nickname,color']);

        return $exception;
    }

    /**
     * Edit this and all future occurrences: split the series.
     */
    public function editThisAndFuture(Event $master, string $occurrenceStart, array $data, string $timezone): Event
    {
        $splitDate = Carbon::parse($occurrenceStart);

        // Add UNTIL to existing master's RRULE (day before split)
        $untilDate = $splitDate->copy()->subDay();
        $this->addUntilToRRule($master, $untilDate, $timezone);

        // Delete future exception instances
        $master->exceptions()
            ->where('original_start', '>=', $splitDate)
            ->delete();

        // Create new master from split date
        $newMaster = $master->replicate(['recurrence_exceptions']);
        $newMaster->fill($data);
        $newMaster->recurring_event_id = null;
        $newMaster->original_start = null;
        $newMaster->recurrence_exceptions = null;

        // If the data includes rrule, use that; otherwise use the master's rrule without UNTIL
        if (! isset($data['rrule'])) {
            $newMaster->rrule = $this->removeUntilFromRRule($master->getOriginal('rrule') ?? $master->rrule);
        }

        $newMaster->save();

        // Copy relationships
        $newMaster->attendees()->sync($master->attendees->pluck('id'));
        $newMaster->familyMembers()->sync($master->familyMembers->pluck('id'));

        $newMaster->load(['owner:id,name,email', 'attendees:id,name,email', 'familyMembers:id,name,nickname,color']);

        return $newMaster;
    }

    /**
     * Delete a single occurrence.
     */
    public function deleteSingleOccurrence(Event $master, string $occurrenceStart): void
    {
        $occDate = Carbon::parse($occurrenceStart);

        // Add to master's exclusion list
        $exceptions = $master->recurrence_exceptions ?? [];
        $exceptions[] = $occDate->toIso8601String();
        $master->update(['recurrence_exceptions' => array_values(array_unique($exceptions))]);

        // Delete any exception instance for this date
        $master->exceptions()
            ->where('original_start', $occDate)
            ->delete();
    }

    /**
     * Delete this and all future occurrences.
     */
    public function deleteThisAndFuture(Event $master, string $occurrenceStart, string $timezone): void
    {
        $splitDate = Carbon::parse($occurrenceStart);

        // If the split date is the same as the master's start, delete the entire series
        if ($splitDate->equalTo($master->starts_at)) {
            $master->delete();

            return;
        }

        // Add UNTIL to master's RRULE (day before split)
        $untilDate = $splitDate->copy()->subDay();
        $this->addUntilToRRule($master, $untilDate, $timezone);

        // Delete future exception instances
        $master->exceptions()
            ->where('original_start', '>=', $splitDate)
            ->delete();
    }

    /**
     * Add UNTIL clause to an RRULE string.
     */
    private function addUntilToRRule(Event $master, Carbon $untilDate, string $timezone): void
    {
        $rrule = $master->rrule;

        // Remove existing UNTIL or COUNT
        $rrule = preg_replace('/;?(UNTIL|COUNT)=[^;]*/', '', $rrule);

        // Add new UNTIL in UTC
        $untilUtc = $untilDate->copy()->endOfDay()->setTimezone('UTC');
        $rrule .= ';UNTIL='.$untilUtc->format('Ymd\THis\Z');

        $master->update(['rrule' => $rrule]);
    }

    /**
     * Remove UNTIL clause from an RRULE string.
     */
    private function removeUntilFromRRule(string $rrule): string
    {
        return preg_replace('/;?(UNTIL|COUNT)=[^;]*/', '', $rrule);
    }
}
