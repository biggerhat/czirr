<?php

namespace Database\Seeders;

use App\Enums\RecipeDifficulty;
use App\Models\Cuisine;
use App\Models\Recipe;
use App\Models\RecipeTag;
use App\Models\User;
use Illuminate\Database\Seeder;

class RecipeSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'test@example.com')->first();

        if (! $admin) {
            $admin = User::first();
        }

        $cuisines = Cuisine::whereNull('user_id')->pluck('id', 'name');
        $tags = RecipeTag::whereNull('user_id')->pluck('id', 'name');

        $recipes = [
            [
                'name' => 'Classic Spaghetti Bolognese',
                'description' => 'A rich and hearty Italian meat sauce served over spaghetti.',
                'ingredients' => [
                    ['name' => 'spaghetti', 'quantity' => '1', 'unit' => 'lbs', 'notes' => null],
                    ['name' => 'ground beef', 'quantity' => '1', 'unit' => 'lbs', 'notes' => null],
                    ['name' => 'onion', 'quantity' => '1', 'unit' => 'pieces', 'notes' => 'diced'],
                    ['name' => 'garlic', 'quantity' => '3', 'unit' => 'cloves', 'notes' => 'minced'],
                    ['name' => 'crushed tomatoes', 'quantity' => '1', 'unit' => 'can', 'notes' => '28 oz'],
                    ['name' => 'olive oil', 'quantity' => '2', 'unit' => 'tbsp', 'notes' => null],
                ],
                'instructions' => "Heat olive oil in a large pot over medium heat. Add diced onion and cook until softened, about 5 minutes.\n\nAdd garlic and cook 1 minute. Add ground beef and cook until browned, breaking it apart with a spoon.\n\nPour in crushed tomatoes, season with salt and pepper. Simmer for 30 minutes.\n\nCook spaghetti according to package directions. Serve sauce over pasta.",
                'prep_time' => 15,
                'cook_time' => 45,
                'servings' => 4,
                'difficulty' => RecipeDifficulty::Easy,
                'cuisine' => 'Italian',
                'tags' => ['Dinner', 'Comfort Food'],
            ],
            [
                'name' => 'Chicken Tikka Masala',
                'description' => 'Tender chicken in a creamy, spiced tomato sauce.',
                'ingredients' => [
                    ['name' => 'chicken breast', 'quantity' => '1.5', 'unit' => 'lbs', 'notes' => 'cubed'],
                    ['name' => 'yogurt', 'quantity' => '0.5', 'unit' => 'cups', 'notes' => 'plain'],
                    ['name' => 'tomato sauce', 'quantity' => '2', 'unit' => 'cups', 'notes' => null],
                    ['name' => 'heavy cream', 'quantity' => '0.5', 'unit' => 'cups', 'notes' => null],
                    ['name' => 'garam masala', 'quantity' => '2', 'unit' => 'tsp', 'notes' => null],
                    ['name' => 'cumin', 'quantity' => '1', 'unit' => 'tsp', 'notes' => null],
                ],
                'instructions' => "Marinate chicken in yogurt, garam masala, and cumin for at least 1 hour.\n\nGrill or broil chicken until charred and cooked through.\n\nIn a large pan, simmer tomato sauce with remaining spices for 10 minutes. Stir in cream.\n\nAdd cooked chicken to the sauce and simmer 10 more minutes. Serve over basmati rice.",
                'prep_time' => 20,
                'cook_time' => 35,
                'servings' => 4,
                'difficulty' => RecipeDifficulty::Medium,
                'cuisine' => 'Indian',
                'tags' => ['Dinner'],
            ],
            [
                'name' => 'Thai Green Curry',
                'description' => 'Fragrant coconut curry with vegetables and your choice of protein.',
                'ingredients' => [
                    ['name' => 'green curry paste', 'quantity' => '3', 'unit' => 'tbsp', 'notes' => null],
                    ['name' => 'coconut milk', 'quantity' => '1', 'unit' => 'can', 'notes' => '14 oz'],
                    ['name' => 'chicken breast', 'quantity' => '1', 'unit' => 'lbs', 'notes' => 'sliced'],
                    ['name' => 'bell pepper', 'quantity' => '1', 'unit' => 'pieces', 'notes' => 'sliced'],
                    ['name' => 'bamboo shoots', 'quantity' => '0.5', 'unit' => 'cups', 'notes' => null],
                    ['name' => 'fish sauce', 'quantity' => '2', 'unit' => 'tbsp', 'notes' => null],
                ],
                'instructions' => "Heat a tablespoon of coconut cream in a wok over high heat. Add curry paste and fry for 1 minute until fragrant.\n\nAdd chicken and stir-fry until just cooked. Pour in coconut milk and bring to a simmer.\n\nAdd vegetables and fish sauce. Cook for 5-7 minutes until vegetables are tender.\n\nServe over jasmine rice with fresh basil leaves.",
                'prep_time' => 10,
                'cook_time' => 20,
                'servings' => 4,
                'difficulty' => RecipeDifficulty::Easy,
                'cuisine' => 'Thai',
                'tags' => ['Dinner', 'Quick', 'One Pot'],
            ],
            [
                'name' => 'Banana Pancakes',
                'description' => 'Fluffy pancakes with ripe banana folded into the batter.',
                'ingredients' => [
                    ['name' => 'ripe bananas', 'quantity' => '2', 'unit' => 'pieces', 'notes' => 'mashed'],
                    ['name' => 'eggs', 'quantity' => '2', 'unit' => 'pieces', 'notes' => null],
                    ['name' => 'flour', 'quantity' => '1', 'unit' => 'cups', 'notes' => null],
                    ['name' => 'milk', 'quantity' => '0.75', 'unit' => 'cups', 'notes' => null],
                    ['name' => 'butter', 'quantity' => '2', 'unit' => 'tbsp', 'notes' => 'melted'],
                ],
                'instructions' => "Mash bananas in a large bowl. Whisk in eggs, milk, and melted butter.\n\nAdd flour and stir until just combined — a few lumps are fine.\n\nHeat a griddle over medium heat and grease lightly. Pour 1/4 cup batter per pancake.\n\nCook until bubbles form on top, then flip. Cook 1-2 more minutes. Serve with maple syrup.",
                'prep_time' => 10,
                'cook_time' => 15,
                'servings' => 4,
                'difficulty' => RecipeDifficulty::Easy,
                'cuisine' => 'American',
                'tags' => ['Breakfast', 'Quick'],
            ],
            [
                'name' => 'Mushroom Risotto',
                'description' => 'Creamy Italian rice dish with mixed mushrooms and parmesan.',
                'ingredients' => [
                    ['name' => 'arborio rice', 'quantity' => '1.5', 'unit' => 'cups', 'notes' => null],
                    ['name' => 'mixed mushrooms', 'quantity' => '8', 'unit' => 'oz', 'notes' => 'sliced'],
                    ['name' => 'chicken broth', 'quantity' => '4', 'unit' => 'cups', 'notes' => 'warm'],
                    ['name' => 'parmesan', 'quantity' => '0.5', 'unit' => 'cups', 'notes' => 'grated'],
                    ['name' => 'butter', 'quantity' => '3', 'unit' => 'tbsp', 'notes' => null],
                    ['name' => 'white wine', 'quantity' => '0.5', 'unit' => 'cups', 'notes' => 'dry'],
                ],
                'instructions' => "Sauté mushrooms in butter until golden. Remove and set aside.\n\nIn the same pan, toast rice for 2 minutes. Add wine and stir until absorbed.\n\nAdd warm broth one ladle at a time, stirring frequently, waiting until each addition is absorbed before adding the next. This takes about 18-20 minutes.\n\nStir in mushrooms, parmesan, and remaining butter. Season to taste and serve immediately.",
                'prep_time' => 10,
                'cook_time' => 30,
                'servings' => 4,
                'difficulty' => RecipeDifficulty::Hard,
                'cuisine' => 'Italian',
                'tags' => ['Dinner', 'Vegetarian'],
            ],
            [
                'name' => 'Beef Tacos',
                'description' => 'Seasoned ground beef in crispy taco shells with fresh toppings.',
                'ingredients' => [
                    ['name' => 'ground beef', 'quantity' => '1', 'unit' => 'lbs', 'notes' => null],
                    ['name' => 'taco shells', 'quantity' => '8', 'unit' => 'pieces', 'notes' => null],
                    ['name' => 'cheddar cheese', 'quantity' => '1', 'unit' => 'cups', 'notes' => 'shredded'],
                    ['name' => 'lettuce', 'quantity' => '2', 'unit' => 'cups', 'notes' => 'shredded'],
                    ['name' => 'tomatoes', 'quantity' => '2', 'unit' => 'pieces', 'notes' => 'diced'],
                    ['name' => 'sour cream', 'quantity' => '0.5', 'unit' => 'cups', 'notes' => null],
                ],
                'instructions' => "Brown ground beef in a skillet over medium-high heat, breaking it up as it cooks.\n\nDrain excess fat. Add taco seasoning and water according to packet directions. Simmer 5 minutes.\n\nWarm taco shells in the oven at 350°F for 3-4 minutes.\n\nFill shells with meat and top with cheese, lettuce, tomatoes, and sour cream.",
                'prep_time' => 10,
                'cook_time' => 15,
                'servings' => 4,
                'difficulty' => RecipeDifficulty::Easy,
                'cuisine' => 'Mexican',
                'tags' => ['Dinner', 'Quick'],
            ],
            [
                'name' => 'French Onion Soup',
                'description' => 'Deeply caramelized onion soup topped with crusty bread and melted gruyère.',
                'ingredients' => [
                    ['name' => 'onions', 'quantity' => '4', 'unit' => 'pieces', 'notes' => 'thinly sliced'],
                    ['name' => 'butter', 'quantity' => '3', 'unit' => 'tbsp', 'notes' => null],
                    ['name' => 'beef broth', 'quantity' => '4', 'unit' => 'cups', 'notes' => null],
                    ['name' => 'white wine', 'quantity' => '0.5', 'unit' => 'cups', 'notes' => 'dry'],
                    ['name' => 'baguette', 'quantity' => '4', 'unit' => 'pieces', 'notes' => 'thick slices'],
                    ['name' => 'gruyère cheese', 'quantity' => '1.5', 'unit' => 'cups', 'notes' => 'shredded'],
                ],
                'instructions' => "Melt butter in a large pot over medium-low heat. Add onions and cook slowly for 40-50 minutes, stirring occasionally, until deeply caramelized.\n\nDeglaze with wine and cook until mostly evaporated. Add broth and simmer 20 minutes.\n\nLadle soup into oven-safe bowls. Top each with a baguette slice and generous gruyère.\n\nBroil until cheese is bubbly and golden, about 3-4 minutes. Serve immediately.",
                'prep_time' => 15,
                'cook_time' => 75,
                'servings' => 4,
                'difficulty' => RecipeDifficulty::Medium,
                'cuisine' => 'French',
                'tags' => ['Dinner', 'Comfort Food'],
            ],
            [
                'name' => 'Grilled Salmon',
                'description' => 'Simple grilled salmon fillets with a lemon-herb butter glaze.',
                'ingredients' => [
                    ['name' => 'salmon fillets', 'quantity' => '4', 'unit' => 'pieces', 'notes' => '6 oz each'],
                    ['name' => 'lemon', 'quantity' => '1', 'unit' => 'pieces', 'notes' => 'juiced and zested'],
                    ['name' => 'butter', 'quantity' => '2', 'unit' => 'tbsp', 'notes' => 'melted'],
                    ['name' => 'garlic', 'quantity' => '2', 'unit' => 'cloves', 'notes' => 'minced'],
                    ['name' => 'fresh dill', 'quantity' => '2', 'unit' => 'tbsp', 'notes' => 'chopped'],
                ],
                'instructions' => "Mix melted butter, lemon juice, zest, garlic, and dill. Brush over salmon fillets.\n\nPreheat grill to medium-high. Oil the grates.\n\nGrill salmon skin-side down for 4-5 minutes. Flip and cook 3-4 more minutes until fish flakes easily.\n\nServe with remaining lemon-herb butter drizzled on top.",
                'prep_time' => 10,
                'cook_time' => 10,
                'servings' => 4,
                'difficulty' => RecipeDifficulty::Easy,
                'cuisine' => 'Mediterranean',
                'tags' => ['Dinner', 'Healthy', 'Quick', 'Grilled'],
            ],
        ];

        foreach ($recipes as $data) {
            $recipe = Recipe::factory()->create([
                'user_id' => $admin->id,
                'name' => $data['name'],
                'description' => $data['description'],
                'ingredients' => $data['ingredients'],
                'instructions' => $data['instructions'],
                'prep_time' => $data['prep_time'],
                'cook_time' => $data['cook_time'],
                'servings' => $data['servings'],
                'difficulty' => $data['difficulty'],
                'cuisine_id' => $cuisines->get($data['cuisine']),
            ]);

            $tagIds = collect($data['tags'])
                ->map(fn ($name) => $tags->get($name))
                ->filter()
                ->values()
                ->all();

            if ($tagIds) {
                $recipe->tags()->attach($tagIds);
            }
        }
    }
}
