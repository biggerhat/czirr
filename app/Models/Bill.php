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
    use \App\Models\Concerns\HasRecurrence;
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
        return $this->countOccurrences($this->start_date, $this->frequency, $start, $end);
    }

    public function isDueInRange(Carbon $start, Carbon $end): bool
    {
        return $this->occurrencesInRange($start, $end) > 0;
    }

    public function nextDueDate(CarbonInterface $from): CarbonInterface
    {
        return $this->nextOccurrence($this->start_date, $this->frequency, $from);
    }
}
