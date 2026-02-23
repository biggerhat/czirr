<?php

namespace Database\Seeders;

use App\Models\Cookbook;
use App\Models\FamilyMember;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Seeder;

class CookbookSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'test@example.com')->first();
        if (!$admin) {
            $admin = User::first();
        }

        $recipes = Recipe::where('user_id', $admin->id)->get();

        // Weeknight Dinners — everyone can see
        $weeknight = Cookbook::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Weeknight Dinners',
            'description' => 'Quick and easy meals for busy evenings.',
        ]);

        $weeknightRecipes = $recipes->filter(
            fn ($r) => in_array($r->name, [
                'Classic Spaghetti Bolognese',
                'Beef Tacos',
                'Thai Green Curry',
                'Chicken Stir-Fry',
                'Grilled Salmon',
            ])
        );

        $weeknight->recipes()->attach(
            $weeknightRecipes->values()->mapWithKeys(fn ($r, $i) => [$r->id => ['position' => $i]])->all()
        );

        // Holiday Favorites — everyone can see
        $holiday = Cookbook::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Holiday Favorites',
            'description' => 'Special occasion recipes the whole family loves.',
        ]);

        $holidayRecipes = $recipes->filter(
            fn ($r) => in_array($r->name, [
                'Mushroom Risotto',
                'French Onion Soup',
                'Chicken Tikka Masala',
            ])
        );

        $holiday->recipes()->attach(
            $holidayRecipes->values()->mapWithKeys(fn ($r, $i) => [$r->id => ['position' => $i]])->all()
        );

        // Kids' Cookbook — specific visibility, assigned to children
        $kids = Cookbook::factory()->specific()->create([
            'user_id' => $admin->id,
            'name' => 'Kid-Friendly Meals',
            'description' => 'Simple recipes the kids can help make.',
        ]);

        $kidRecipes = $recipes->filter(
            fn ($r) => in_array($r->name, [
                'Banana Pancakes',
                'Beef Tacos',
                'Classic Spaghetti Bolognese',
            ])
        );

        $kids->recipes()->attach(
            $kidRecipes->values()->mapWithKeys(fn ($r, $i) => [$r->id => ['position' => $i]])->all()
        );

        $childMembers = FamilyMember::where('user_id', $admin->id)
            ->where('role', 'child')
            ->pluck('id');

        if ($childMembers->isNotEmpty()) {
            $kids->members()->attach($childMembers);
        }
    }
}
