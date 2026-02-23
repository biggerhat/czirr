<?php

namespace Database\Factories;

use App\Enums\MealType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MealPlanEntry>
 */
class MealPlanEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => fake()->dateTimeBetween('-1 week', '+1 week')->format('Y-m-d'),
            'meal_type' => fake()->randomElement(MealType::cases()),
            'recipe_id' => null,
            'name' => fake()->words(3, true),
            'description' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function breakfast(): static
    {
        return $this->state(fn () => ['meal_type' => MealType::Breakfast]);
    }

    public function lunch(): static
    {
        return $this->state(fn () => ['meal_type' => MealType::Lunch]);
    }

    public function dinner(): static
    {
        return $this->state(fn () => ['meal_type' => MealType::Dinner]);
    }

    public function snack(): static
    {
        return $this->state(fn () => ['meal_type' => MealType::Snack]);
    }
}
