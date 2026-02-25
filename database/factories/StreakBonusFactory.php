<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StreakBonus>
 */
class StreakBonusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'days_required' => fake()->numberBetween(3, 30),
            'bonus_points' => fake()->numberBetween(5, 75),
        ];
    }

    public static function defaultMilestones(): array
    {
        return [
            ['days_required' => 3, 'bonus_points' => 5],
            ['days_required' => 7, 'bonus_points' => 15],
            ['days_required' => 14, 'bonus_points' => 30],
            ['days_required' => 30, 'bonus_points' => 75],
        ];
    }
}
