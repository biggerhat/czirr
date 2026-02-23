<?php

namespace App\Models;

use App\Enums\RecipeDifficulty;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'ingredients',
        'instructions',
        'prep_time',
        'cook_time',
        'servings',
        'image_url',
        'source_url',
        'cuisine_id',
        'difficulty',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'ingredients' => 'array',
            'prep_time' => 'integer',
            'cook_time' => 'integer',
            'servings' => 'integer',
            'difficulty' => RecipeDifficulty::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cuisine(): BelongsTo
    {
        return $this->belongsTo(Cuisine::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(RecipeTag::class, 'recipe_recipe_tag')->withTimestamps();
    }

    public function cookbooks(): BelongsToMany
    {
        return $this->belongsToMany(Cookbook::class, 'cookbook_recipe')
            ->withPivot('position')
            ->withTimestamps();
    }
}
