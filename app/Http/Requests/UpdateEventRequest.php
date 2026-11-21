<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->id === $this->route('event')->user_id;
    }

    protected function prepareForValidation(): void
    {
        $timezone = in_array($this->input('timezone'), timezone_identifiers_list(), true)
            ? $this->input('timezone')
            : 'UTC';

        if ($this->boolean('is_all_day')) {
            $this->merge([
                'starts_at' => Carbon::parse($this->input('start_date'), $timezone)->startOfDay()->utc()->toDateTimeString(),
                'ends_at' => Carbon::parse($this->input('end_date'), $timezone)->endOfDay()->utc()->toDateTimeString(),
            ]);
        } else {
            $this->merge([
                'starts_at' => Carbon::parse($this->input('start_date').' '.$this->input('start_time'), $timezone)->utc()->toDateTimeString(),
                'ends_at' => Carbon::parse($this->input('end_date').' '.$this->input('end_time'), $timezone)->utc()->toDateTimeString(),
            ]);
        }
    }

    public function rules(): array
    {
        $ownerId = $this->user()->familyOwnerId();
        $familyUserIds = \App\Models\FamilyMember::where('user_id', $ownerId)->whereNotNull('linked_user_id')->pluck('linked_user_id')->push($ownerId)->all();

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'is_all_day' => ['boolean'],
            'attendee_ids' => ['nullable', 'array'],
            'attendee_ids.*' => ['integer', Rule::in($familyUserIds)],
            'family_member_ids' => ['nullable', 'array'],
            'family_member_ids.*' => ['integer', Rule::exists('family_members', 'id')->where('user_id', $ownerId)],
            'rrule' => ['nullable', 'string', 'max:1000'],
            'event_type_id' => ['nullable', 'integer', Rule::exists('event_types', 'id')->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $ownerId))],
            'edit_mode' => ['nullable', 'string', 'in:single,future,all'],
            'occurrence_start' => ['nullable', 'string'],
        ];
    }
}
