<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecipeRating extends Model
{
    protected $fillable = ['recipe_id', 'user_id', 'rating'];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }
}
