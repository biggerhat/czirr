<?php

namespace Database\Seeders;

use App\Models\BudgetCategory;
use App\Models\RecipeTag;
use App\Models\User;
use Illuminate\Database\Seeder;

class BudgetCategorySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();

        // --- Budget categories ---
        $categories = collect([
            ['name' => 'Housing', 'color' => 'blue'],
            ['name' => 'Utilities', 'color' => 'cyan'],
            ['name' => 'Groceries', 'color' => 'emerald'],
            ['name' => 'Transportation', 'color' => 'amber'],
            ['name' => 'Insurance', 'color' => 'violet'],
            ['name' => 'Entertainment', 'color' => 'pink'],
            ['name' => 'Healthcare', 'color' => 'rose'],
            ['name' => 'Subscriptions', 'color' => 'orange'],
        ])->map(fn ($cat, $i) => BudgetCategory::firstOrCreate([
            'user_id' => $admin->id,
            'name' => $cat['name'],
            'color' => $cat['color'],
            'sort_order' => $i,
        ]));
    }
}
