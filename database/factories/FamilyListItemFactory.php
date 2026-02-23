<?php

namespace Database\Factories;

use App\Models\FamilyList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FamilyListItem>
 */
class FamilyListItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'family_list_id' => FamilyList::factory(),
            'name' => fake()->words(fake()->numberBetween(1, 3), true),
            'is_completed' => false,
            'quantity' => fake()->optional(0.5)->randomElement(['1', '2', '3', '1 lb', '2 lbs', '6 pack', '1 dozen', '1 bag']),
            'notes' => fake()->optional(0.2)->sentence(),
            'position' => 0,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['is_completed' => true]);
    }
}
