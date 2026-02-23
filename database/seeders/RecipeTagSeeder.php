<?php

namespace Database\Seeders;

use App\Models\RecipeTag;
use Illuminate\Database\Seeder;

class RecipeTagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            'Quick', 'Healthy', 'Vegetarian', 'Vegan', 'Gluten-Free',
            'Dairy-Free', 'Comfort Food', 'Meal Prep', 'One Pot', 'Grilled',
            'Baked', 'Slow Cooker', 'Breakfast', 'Lunch', 'Dinner',
            'Dessert', 'Snack', 'Holiday',
        ];

        foreach ($tags as $name) {
            RecipeTag::firstOrCreate(
                ['user_id' => null, 'name' => $name],
            );
        }
    }
}
