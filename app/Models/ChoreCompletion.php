<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Carbon\Carbon $completed_date
 */
class ChoreCompletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'chore_assignment_id',
        'family_member_id',
        'completed_date',
        'points_earned',
    ];

    protected function casts(): array
    {
        return [
            'completed_date' => 'date',
            'points_earned' => 'integer',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ChoreAssignment::class, 'chore_assignment_id');
    }

    public function familyMember(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class);
    }
}
