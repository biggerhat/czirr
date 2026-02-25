<?php

namespace Database\Factories;

use App\Models\FamilyMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BonusObjective>
 */
class BonusObjectiveFactory extends Factory
{
    private const OBJECTIVE_NAMES = [
        'Clean the garage',
        'Organize the pantry',
        'Wash the car',
        'Mop all floors',
        'Deep clean the fridge',
        'Weed the garden',
        'Sort recycling',
        'Clean windows',
    ];

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(self::OBJECTIVE_NAMES),
            'description' => fake()->optional(0.5)->sentence(),
            'points' => fake()->numberBetween(10, 100),
            'claimed_by' => null,
            'claimed_at' => null,
        ];
    }

    public function claimed(FamilyMember $member): static
    {
        return $this->state(fn () => [
            'claimed_by' => $member->id,
            'claimed_at' => Carbon::now(),
        ]);
    }
}
