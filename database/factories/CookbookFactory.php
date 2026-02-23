<?php

namespace Database\Factories;

use App\Enums\ListVisibility;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cookbook>
 */
class CookbookFactory extends Factory
{
    private const COOKBOOK_NAMES = [
        'Weeknight Dinners',
        'Holiday Favorites',
        'Summer Grilling',
        'Healthy Meal Prep',
        'Comfort Food Classics',
        'Quick Breakfasts',
        'Date Night Recipes',
        'Kid-Friendly Meals',
        'Sunday Baking',
        'One-Pot Wonders',
    ];

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(self::COOKBOOK_NAMES),
            'description' => fake()->optional(0.7)->sentence(),
            'visibility' => ListVisibility::Everyone,
        ];
    }

    public function parentsOnly(): static
    {
        return $this->state(fn () => ['visibility' => ListVisibility::Parents]);
    }

    public function childrenOnly(): static
    {
        return $this->state(fn () => ['visibility' => ListVisibility::Children]);
    }

    public function specific(): static
    {
        return $this->state(fn () => ['visibility' => ListVisibility::Specific]);
    }
}
