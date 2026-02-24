<?php

namespace App\Models;

use App\Enums\MealType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealPlanEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'meal_type',
        'recipe_id',
        'name',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'meal_type' => MealType::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
