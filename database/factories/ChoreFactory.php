<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chore>
 */
class ChoreFactory extends Factory
{
    private const CHORE_NAMES = [
        'Wash dishes',
        'Vacuum living room',
        'Take out trash',
        'Mow the lawn',
        'Clean bathroom',
        'Do laundry',
        'Wipe kitchen counters',
        'Feed the pets',
        'Set the table',
        'Sweep the porch',
    ];

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(self::CHORE_NAMES),
            'description' => fake()->optional(0.3)->sentence(),
            'is_active' => true,
            'points' => fake()->numberBetween(5, 25),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
