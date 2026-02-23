<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->id === $this->route('event')->user_id;
    }

    protected function prepareForValidation(): void
    {
        $timezone = $this->input('timezone', 'UTC');

        if ($this->boolean('is_all_day')) {
            $this->merge([
                'starts_at' => Carbon::parse($this->input('start_date'), $timezone)->startOfDay()->utc()->toDateTimeString(),
                'ends_at' => Carbon::parse($this->input('end_date'), $timezone)->endOfDay()->utc()->toDateTimeString(),
            ]);
        } else {
            $this->merge([
                'starts_at' => Carbon::parse($this->input('start_date') . ' ' . $this->input('start_time'), $timezone)->utc()->toDateTimeString(),
                'ends_at' => Carbon::parse($this->input('end_date') . ' ' . $this->input('end_time'), $timezone)->utc()->toDateTimeString(),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'is_all_day' => ['boolean'],
            'attendee_ids' => ['nullable', 'array'],
            'attendee_ids.*' => ['integer', 'exists:users,id'],
            'family_member_ids' => ['nullable', 'array'],
            'family_member_ids.*' => ['integer', 'exists:family_members,id'],
            'rrule' => ['nullable', 'string', 'max:1000'],
            'edit_mode' => ['nullable', 'string', 'in:single,future,all'],
            'occurrence_start' => ['nullable', 'string'],
        ];
    }
}
