<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonusObjective extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'points',
        'claimed_by',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
            'points' => 'integer',
        ];
    }

    public function claimedByMember(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'claimed_by');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereNull('claimed_by');
    }
}
