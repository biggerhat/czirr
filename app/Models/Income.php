<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Carbon\Carbon $start_date
 */
class Income extends Model
{
    use \App\Models\Concerns\HasRecurrence;
    use HasFactory;

    protected $fillable = [
        'name',
        'amount',
        'start_date',
        'frequency',
        'is_active',
        'notes',
        'event_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
            'start_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function occurrencesInRange(Carbon $start, Carbon $end): int
    {
        return $this->countOccurrences($this->start_date, $this->frequency, $start, $end);
    }

    public function hasOccurrenceInRange(Carbon $start, Carbon $end): bool
    {
        return $this->occurrencesInRange($start, $end) > 0;
    }
}
