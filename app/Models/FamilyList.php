<?php

namespace App\Models;

use App\Enums\ListType;
use App\Enums\ListVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FamilyList extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'visibility',
    ];

    protected function casts(): array
    {
        return [
            'type' => ListType::class,
            'visibility' => ListVisibility::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FamilyListItem::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(FamilyMember::class, 'family_list_members')
            ->withTimestamps();
    }
}
