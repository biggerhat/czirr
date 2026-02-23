<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyListItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_completed',
        'quantity',
        'notes',
        'position',
    ];

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
