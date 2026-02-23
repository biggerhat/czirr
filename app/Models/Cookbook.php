<?php

namespace App\Models;

use App\Enums\ListVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Cookbook extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'visibility',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => ListVisibility::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'cookbook_recipe')
            ->withPivot('position')
            ->withTimestamps();
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(FamilyMember::class, 'cookbook_members')
            ->withTimestamps();
    }
}
