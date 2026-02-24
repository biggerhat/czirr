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
        $incomeStart = $this->start_date;

        if ($this->frequency === 'once') {
            return $incomeStart->between($start, $end) ? 1 : 0;
        }

        // Weekly / biweekly
        if (in_array($this->frequency, ['weekly', 'biweekly'])) {
            $intervalDays = $this->frequency === 'weekly' ? 7 : 14;

            if ($incomeStart->gt($end)) {
                return 0;
            }

            // Find first occurrence on or after range start
            $first = $incomeStart->copy();
            if ($first->lt($start)) {
                $daysDiff = $incomeStart->diffInDays($start);
                $periods = (int) ceil($daysDiff / $intervalDays);
                $first = $incomeStart->copy()->addDays($periods * $intervalDays);
            }

            if ($first->gt($end)) {
                return 0;
            }

            return (int) floor($first->diffInDays($end) / $intervalDays) + 1;
        }

        // Monthly / quarterly / yearly
        $interval = match ($this->frequency) {
            'quarterly' => 3,
            'yearly' => 12,
            default => 1,
        };

        $payDay = $incomeStart->day;
        $count = 0;
        $current = $start->copy()->startOfMonth();

        while ($current->lte($end)) {
            if ($current->gte($incomeStart->copy()->startOfMonth())) {
                $monthsDiff = $incomeStart->copy()->startOfMonth()->diffInMonths($current);

                if ($monthsDiff % $interval === 0) {
                    $day = min($payDay, $current->copy()->endOfMonth()->day);
                    $occDate = $current->copy()->day($day);

                    if ($occDate->between($start, $end)) {
                        $count++;
                    }
                }
            }

            $current->addMonth();
        }

        return $count;
    }

    public function hasOccurrenceInRange(Carbon $start, Carbon $end): bool
    {
        return $this->occurrencesInRange($start, $end) > 0;
    }
}
