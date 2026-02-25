<?php

namespace Database\Factories;

use App\Models\ChoreAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChoreCompletion>
 */
class ChoreCompletionFactory extends Factory
{
    public function definition(): array
    {
        $assignment = ChoreAssignment::factory()->create();

        return [
            'chore_assignment_id' => $assignment->id,
            'family_member_id' => $assignment->family_member_id,
            'completed_date' => now()->toDateString(),
            'points_earned' => fake()->numberBetween(1, 20),
        ];
    }

    public function forAssignment(ChoreAssignment $assignment): static
    {
        return $this->state(fn () => [
            'chore_assignment_id' => $assignment->id,
            'family_member_id' => $assignment->family_member_id,
        ]);
    }

    public function forDate(\Carbon\Carbon $date): static
    {
        return $this->state(fn () => [
            'completed_date' => $date->toDateString(),
        ]);
    }

    public function withPoints(int $points): static
    {
        return $this->state(fn () => [
            'points_earned' => $points,
        ]);
    }
}
