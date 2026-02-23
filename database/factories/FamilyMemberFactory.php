<?php

namespace Database\Factories;

use App\Enums\FamilyRole;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FamilyMember>
 */
class FamilyMemberFactory extends Factory
{
    protected $model = FamilyMember::class;

    private static array $colors = ['rose', 'orange', 'amber', 'emerald', 'cyan', 'blue', 'violet', 'pink'];

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->firstName(),
            'nickname' => fake()->optional(0.4)->firstName(),
            'role' => FamilyRole::Child,
            'color' => fake()->randomElement(self::$colors),
            'linked_user_id' => null,
        ];
    }

    public function parent(): static
    {
        return $this->state(fn () => ['role' => FamilyRole::Parent]);
    }

    public function linked(User $linkedUser): static
    {
        return $this->state(fn () => [
            'linked_user_id' => $linkedUser->id,
            'name' => $linkedUser->name,
        ]);
    }
}
