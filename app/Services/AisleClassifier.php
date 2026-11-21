<?php

namespace App\Services;

/**
 * Guesses a grocery aisle from an item name using simple keyword matching.
 */
class AisleClassifier
{
    public const OTHER = 'Other';

    /** Display order, roughly the way a store is walked. */
    public const AISLES = [
        'Produce', 'Bakery', 'Meat & Seafood', 'Dairy & Eggs', 'Frozen',
        'Pantry', 'Snacks', 'Beverages', 'Household', self::OTHER,
    ];

    private const KEYWORDS = [
        'Produce' => ['apple', 'banana', 'lettuce', 'spinach', 'kale', 'tomato', 'onion', 'garlic', 'potato', 'carrot', 'celery', 'pepper', 'cucumber', 'broccoli', 'cauliflower', 'avocado', 'lemon', 'lime', 'orange', 'grape', 'berry', 'berries', 'melon', 'mushroom', 'zucchini', 'squash', 'cilantro', 'parsley', 'basil', 'ginger', 'herb', 'salad', 'fruit', 'vegetable', 'corn on', 'pear', 'peach', 'cabbage', 'scallion', 'green onion', 'shallot'],
        'Bakery' => ['bread', 'bagel', 'bun', 'roll', 'tortilla', 'pita', 'croissant', 'muffin', 'baguette', 'naan', 'donut', 'cake'],
        'Meat & Seafood' => ['chicken', 'beef', 'pork', 'steak', 'bacon', 'sausage', 'ham', 'turkey', 'salmon', 'shrimp', 'fish', 'tuna steak', 'ground', 'lamb', 'hot dog', 'meatball', 'ribs', 'brisket'],
        'Dairy & Eggs' => ['milk', 'cheese', 'butter', 'yogurt', 'yoghurt', 'cream', 'egg', 'sour cream', 'cottage', 'half and half', 'half & half', 'creamer', 'margarine'],
        'Frozen' => ['frozen', 'ice cream', 'pizza', 'popsicle', 'waffle'],
        'Pantry' => ['flour', 'sugar', 'salt', 'rice', 'pasta', 'noodle', 'spaghetti', 'oil', 'vinegar', 'sauce', 'ketchup', 'mustard', 'mayo', 'mayonnaise', 'cereal', 'oat', 'bean', 'lentil', 'soup', 'broth', 'stock', 'can ', 'canned', 'spice', 'cinnamon', 'baking', 'vanilla', 'honey', 'syrup', 'peanut butter', 'jam', 'jelly', 'nut', 'seasoning', 'cumin', 'paprika', 'tuna', 'salsa', 'yeast', 'cocoa', 'chocolate chip'],
        'Snacks' => ['chip', 'cracker', 'cookie', 'candy', 'popcorn', 'pretzel', 'granola', 'snack', 'bar'],
        'Beverages' => ['juice', 'soda', 'water', 'coffee', 'tea', 'beer', 'wine', 'lemonade', 'kombucha', 'sparkling'],
        'Household' => ['paper towel', 'toilet', 'tissue', 'soap', 'detergent', 'trash bag', 'foil', 'wrap', 'sponge', 'cleaner', 'shampoo', 'toothpaste', 'diaper', 'wipes', 'battery', 'bulb', 'dish', 'laundry', 'bleach', 'napkin', 'plate', 'cup'],
    ];

    public static function classify(string $name): string
    {
        $needle = ' '.mb_strtolower(trim($name)).' ';

        // Frozen first: "frozen peas" shouldn't land in Produce.
        $order = ['Frozen', ...array_diff(array_keys(self::KEYWORDS), ['Frozen'])];

        foreach ($order as $aisle) {
            foreach (self::KEYWORDS[$aisle] as $keyword) {
                if (str_contains($needle, $keyword)) {
                    return $aisle;
                }
            }
        }

        return self::OTHER;
    }
}
