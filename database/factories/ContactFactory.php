<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contact>
 */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->optional(0.7)->phoneNumber(),
            'email' => fake()->optional(0.5)->safeEmail(),
            'address_line_1' => fake()->optional(0.4)->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->optional(0.4)->city(),
            'state' => fake()->optional(0.4)->stateAbbr(),
            'zip' => fake()->optional(0.4)->postcode(),
            'date_of_birth' => null,
            'notes' => fake()->optional(0.2)->sentence(),
        ];
    }

    public function withBirthday(): static
    {
        return $this->state(fn () => [
            'date_of_birth' => fake()->date(),
        ]);
    }
}
