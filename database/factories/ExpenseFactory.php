<?php

namespace Database\Factories;

use App\Models\BudgetCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'budget_category_id' => BudgetCategory::factory(),
            'bill_id' => null,
            'name' => fake()->words(2, true),
            'amount' => fake()->randomFloat(2, 5, 200),
            'date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'notes' => null,
        ];
    }

    public function forBill(\App\Models\Bill $bill): static
    {
        return $this->state(fn () => [
            'bill_id' => $bill->id,
            'budget_category_id' => $bill->budget_category_id,
            'name' => $bill->name,
            'amount' => $bill->amount,
        ]);
    }
}
