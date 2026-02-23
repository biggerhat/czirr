<?php

namespace Database\Factories;

use App\Models\Chore;
use App\Models\FamilyMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChoreAssignment>
 */
class ChoreAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'chore_id' => Chore::factory(),
            'family_member_id' => FamilyMember::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
        ];
    }
}
