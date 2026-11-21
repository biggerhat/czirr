<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FamilyListItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'is_completed',
        'quantity',
        'notes',
        'category',
        'position',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            $item->category ??= \App\Services\AisleClassifier::classify($item->name);
        });
    }

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function familyList(): BelongsTo
    {
        return $this->belongsTo(FamilyList::class);
    }
}
