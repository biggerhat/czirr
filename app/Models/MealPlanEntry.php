<?php

namespace App\Models;

use App\Enums\MealType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property MealType $meal_type
 * @property \Carbon\Carbon $date
 */
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

    /**
     * Breakfast → lunch → dinner → snack. A portable CASE rather than MySQL's
     * FIELD(), so the app (and its tests) don't depend on a database-specific function.
     *
     * @param  Builder<MealPlanEntry>  $query
     * @return Builder<MealPlanEntry>
     */
    public function scopeOrderByMealType(Builder $query): Builder
    {
        return $query->orderByRaw(
            "CASE meal_type WHEN 'breakfast' THEN 1 WHEN 'lunch' THEN 2 WHEN 'dinner' THEN 3 WHEN 'snack' THEN 4 ELSE 5 END"
        );
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
