<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Carbon\Carbon $completed_date
 * @property string $status
 * @property int $points_earned
 */
class ChoreCompletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'chore_assignment_id',
        'family_member_id',
        'completed_date',
        'points_earned',
        'status',
        'approved_by',
        'approved_at',
    ];

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    /**
     * Only approved completions count towards points, streaks, recaps and the
     * spendable reward balance. Code that needs to see pending ones (the
     * approval queue, the day view) opts out with withoutGlobalScope('approved').
     */
    protected static function booted(): void
    {
        static::addGlobalScope('approved', fn (Builder $q) => $q->where('chore_completions.status', self::APPROVED));
    }

    protected function casts(): array
    {
        return [
            'completed_date' => 'date',
            'points_earned' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ChoreAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ChoreAssignment::class, 'chore_assignment_id');
    }

    /** @return BelongsTo<FamilyMember, $this> */
    public function familyMember(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class);
    }
}
