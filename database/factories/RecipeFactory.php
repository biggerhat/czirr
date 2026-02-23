<?php

namespace Database\Factories;

use App\Enums\RecipeDifficulty;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Recipe>
 */
class RecipeFactory extends Factory
{
    private const RECIPE_NAMES = [
        'Classic Spaghetti Bolognese',
        'Chicken Tikka Masala',
        'Beef Tacos',
        'Margherita Pizza',
        'Thai Green Curry',
        'Caesar Salad',
        'Mushroom Risotto',
        'Fish and Chips',
        'Pad Thai',
        'Chicken Stir-Fry',
        'French Onion Soup',
        'Grilled Salmon',
        'Beef Stroganoff',
        'Vegetable Lasagna',
        'Shrimp Scampi',
        'BBQ Pulled Pork',
        'Greek Moussaka',
        'Chicken Fajitas',
        'Tom Yum Soup',
        'Banana Pancakes',
    ];

    private const INGREDIENT_NAMES = [
        'chicken breast', 'ground beef', 'salmon fillet', 'shrimp',
        'onion', 'garlic', 'bell pepper', 'tomatoes', 'mushrooms', 'spinach',
        'olive oil', 'butter', 'soy sauce', 'lemon juice',
        'rice', 'pasta', 'flour', 'bread crumbs',
        'salt', 'black pepper', 'cumin', 'paprika', 'oregano',
        'cheese', 'cream', 'milk', 'eggs',
    ];

    private const UNITS = ['cups', 'tbsp', 'tsp', 'oz', 'lbs', 'cloves', 'pieces', 'bunch', 'can'];

    public function definition(): array
    {
        $ingredientCount = fake()->numberBetween(3, 6);
        $ingredients = [];
        $usedNames = [];

        for ($i = 0; $i < $ingredientCount; $i++) {
            do {
                $name = fake()->randomElement(self::INGREDIENT_NAMES);
            } while (in_array($name, $usedNames));

            $usedNames[] = $name;
            $ingredients[] = [
                'name' => $name,
                'quantity' => (string) fake()->randomElement([0.25, 0.5, 1, 1.5, 2, 3, 4]),
                'unit' => fake()->randomElement(self::UNITS),
                'notes' => fake()->optional(0.2)->randomElement(['diced', 'minced', 'sliced', 'chopped', 'to taste']),
            ];
        }

        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(self::RECIPE_NAMES),
            'description' => fake()->sentence(),
            'ingredients' => $ingredients,
            'instructions' => implode("\n\n", fake()->paragraphs(3)),
            'prep_time' => fake()->numberBetween(5, 60),
            'cook_time' => fake()->numberBetween(10, 120),
            'servings' => fake()->numberBetween(2, 8),
            'image_url' => null,
            'source_url' => null,
            'cuisine_id' => null,
            'difficulty' => fake()->randomElement(RecipeDifficulty::cases()),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function easy(): static
    {
        return $this->state(fn () => ['difficulty' => RecipeDifficulty::Easy]);
    }

    public function medium(): static
    {
        return $this->state(fn () => ['difficulty' => RecipeDifficulty::Medium]);
    }

    public function hard(): static
    {
        return $this->state(fn () => ['difficulty' => RecipeDifficulty::Hard]);
    }
}
