<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Income>
 */
class IncomeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Salary', 'Freelance', 'Side Project', 'Consulting']),
            'amount' => fake()->randomFloat(2, 1000, 5000),
            'start_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'frequency' => 'monthly',
            'is_active' => true,
            'notes' => null,
            'event_id' => null,
        ];
    }

    public function biweekly(): static
    {
        return $this->state(fn () => ['frequency' => 'biweekly']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
