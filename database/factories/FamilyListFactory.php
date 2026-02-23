<?php

namespace Database\Factories;

use App\Enums\ListType;
use App\Enums\ListVisibility;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FamilyList>
 */
class FamilyListFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(ListType::cases()),
            'visibility' => ListVisibility::Everyone,
        ];
    }

    public function grocery(): static
    {
        return $this->state(fn () => ['type' => ListType::Grocery]);
    }

    public function shopping(): static
    {
        return $this->state(fn () => ['type' => ListType::Shopping]);
    }

    public function todo(): static
    {
        return $this->state(fn () => ['type' => ListType::Todo]);
    }

    public function wishlist(): static
    {
        return $this->state(fn () => ['type' => ListType::Wishlist]);
    }

    public function parentsOnly(): static
    {
        return $this->state(fn () => ['visibility' => ListVisibility::Parents]);
    }

    public function childrenOnly(): static
    {
        return $this->state(fn () => ['visibility' => ListVisibility::Children]);
    }

    public function specific(): static
    {
        return $this->state(fn () => ['visibility' => ListVisibility::Specific]);
    }
}
