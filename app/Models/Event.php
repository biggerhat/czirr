<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property \Carbon\Carbon $starts_at
 * @property \Carbon\Carbon $ends_at
 * @property \Carbon\Carbon|null $original_start
 */
class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'contact_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'is_all_day',
        'rrule',
        'recurring_event_id',
        'original_start',
        'recurrence_exceptions',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_all_day' => 'boolean',
            'original_start' => 'datetime',
            'recurrence_exceptions' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_attendees')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function familyMembers(): BelongsToMany
    {
        return $this->belongsToMany(FamilyMember::class, 'event_family_member')
            ->withTimestamps();
    }

    public function masterEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'recurring_event_id');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(Event::class, 'recurring_event_id');
    }

    public function isRecurring(): bool
    {
        return !empty($this->rrule);
    }

    public function isException(): bool
    {
        return $this->recurring_event_id !== null;
    }

    public function getDuration(): int
    {
        return (int) $this->starts_at->diffInSeconds($this->ends_at);
    }

    public function scopeRecurring(Builder $query): Builder
    {
        return $query->whereNotNull('rrule');
    }

    public function scopeNonRecurring(Builder $query): Builder
    {
        return $query->whereNull('rrule')->whereNull('recurring_event_id');
    }

    public function scopeExceptions(Builder $query): Builder
    {
        return $query->whereNotNull('recurring_event_id');
    }
}
