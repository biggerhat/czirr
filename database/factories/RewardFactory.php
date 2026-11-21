<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reward>
 */
class RewardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Extra screen time', 'Pick dinner', 'Ice cream trip', 'Stay up late']),
            'description' => null,
            'cost' => fake()->numberBetween(5, 50),
            'is_active' => true,
        ];
    }
}
