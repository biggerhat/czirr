<?php

namespace Database\Factories;

use App\Models\BudgetCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bill>
 */
class BillFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'budget_category_id' => BudgetCategory::factory(),
            'name' => fake()->words(2, true),
            'amount' => fake()->randomFloat(2, 10, 500),
            'start_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'frequency' => 'monthly',
            'is_active' => true,
            'notes' => null,
            'event_id' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function quarterly(): static
    {
        return $this->state(fn () => ['frequency' => 'quarterly']);
    }

    public function yearly(): static
    {
        return $this->state(fn () => ['frequency' => 'yearly']);
    }
}
