<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BudgetCategory>
 */
class BudgetCategoryFactory extends Factory
{
    private static array $categories = [
        ['name' => 'Housing', 'color' => 'blue'],
        ['name' => 'Utilities', 'color' => 'cyan'],
        ['name' => 'Groceries', 'color' => 'emerald'],
        ['name' => 'Transportation', 'color' => 'amber'],
        ['name' => 'Insurance', 'color' => 'violet'],
        ['name' => 'Entertainment', 'color' => 'pink'],
        ['name' => 'Healthcare', 'color' => 'rose'],
        ['name' => 'Subscriptions', 'color' => 'orange'],
    ];

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->randomElement(array_column(self::$categories, 'name')),
            'color' => 'blue',
            'sort_order' => 0,
        ];
    }

    public function named(string $name, string $color = 'blue', int $sortOrder = 0): static
    {
        return $this->state(fn () => [
            'name' => $name,
            'color' => $color,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * Create the standard set of budget categories for a user.
     */
    public static function standardSet(User $user): void
    {
        foreach (self::$categories as $i => $cat) {
            \App\Models\BudgetCategory::factory()->create([
                'user_id' => $user->id,
                'name' => $cat['name'],
                'color' => $cat['color'],
                'sort_order' => $i,
            ]);
        }
    }
}
