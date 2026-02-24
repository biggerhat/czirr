<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property \Carbon\Carbon $start_date
 */
class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_category_id',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(BudgetCategory::class, 'budget_category_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function isPaidForMonth(Carbon $month): bool
    {
        return $this->expenses()
            ->whereYear('date', $month->year)
            ->whereMonth('date', $month->month)
            ->exists();
    }

    public function isPaidForRange(Carbon $start, Carbon $end): bool
    {
        return $this->expenses()
            ->whereBetween('date', [$start, $end])
            ->exists();
    }

    public function occurrencesInRange(Carbon $start, Carbon $end): int
    {
        $billStart = $this->start_date;

        if ($this->frequency === 'once') {
            return $billStart->between($start, $end) ? 1 : 0;
        }

        // Weekly / biweekly
        if (in_array($this->frequency, ['weekly', 'biweekly'])) {
            $intervalDays = $this->frequency === 'weekly' ? 7 : 14;

            if ($billStart->gt($end)) {
                return 0;
            }

            // Find first occurrence on or after range start
            $first = $billStart->copy();
            if ($first->lt($start)) {
                $daysDiff = $billStart->diffInDays($start);
                $periods = (int) ceil($daysDiff / $intervalDays);
                $first = $billStart->copy()->addDays($periods * $intervalDays);
            }

            if ($first->gt($end)) {
                return 0;
            }

            return (int) floor($first->diffInDays($end) / $intervalDays) + 1;
        }

        // Monthly / quarterly / yearly
        $dueDay = $billStart->day;

        $interval = match ($this->frequency) {
            'quarterly' => 3,
            'yearly' => 12,
            default => 1,
        };

        $count = 0;
        $current = $start->copy()->startOfMonth();

        while ($current->lte($end)) {
            if ($current->gte($billStart->copy()->startOfMonth())) {
                $monthsDiff = $billStart->copy()->startOfMonth()->diffInMonths($current);

                if ($monthsDiff % $interval === 0) {
                    $day = min($dueDay, $current->copy()->endOfMonth()->day);
                    $dueDate = $current->copy()->day($day);

                    if ($dueDate->between($start, $end)) {
                        $count++;
                    }
                }
            }

            $current->addMonth();
        }

        return $count;
    }

    public function isDueInRange(Carbon $start, Carbon $end): bool
    {
        return $this->occurrencesInRange($start, $end) > 0;
    }

    public function nextDueDate(CarbonInterface $from): CarbonInterface
    {
        $billStart = $this->start_date;

        if ($this->frequency === 'once') {
            return $billStart->gte($from) ? $billStart->copy() : Carbon::create(9999, 12, 31);
        }

        // Weekly / biweekly
        if (in_array($this->frequency, ['weekly', 'biweekly'])) {
            $intervalDays = $this->frequency === 'weekly' ? 7 : 14;
            $next = $billStart->copy();

            if ($next->gte($from)) {
                return $next;
            }

            $daysDiff = $billStart->diffInDays($from);
            $periods = (int) ceil($daysDiff / $intervalDays);
            $next = $billStart->copy()->addDays($periods * $intervalDays);

            return $next;
        }

        // Monthly / quarterly / yearly
        $dueDay = min($this->start_date->day, $from->copy()->endOfMonth()->day);
        $next = $from->copy()->day($dueDay);

        if ($next->lt($from)) {
            match ($this->frequency) {
                'monthly' => $next->addMonth(),
                'quarterly' => $next->addMonths(3),
                'yearly' => $next->addYear(),
                default => $next->addMonth(),
            };
            $dueDay = min($this->start_date->day, $next->copy()->endOfMonth()->day);
            $next->day($dueDay);
        }

        return $next;
    }
}
