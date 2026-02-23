<?php

namespace Database\Factories;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', '+2 months');
        $startCarbon = Carbon::instance($start)->setHour(fake()->numberBetween(8, 18))->setMinute(0)->setSecond(0);
        $endCarbon = $startCarbon->copy()->addHours(fake()->numberBetween(1, 3));

        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional(0.3)->sentence(),
            'starts_at' => $startCarbon,
            'ends_at' => $endCarbon,
            'is_all_day' => false,
            'rrule' => null,
            'recurring_event_id' => null,
            'original_start' => null,
            'recurrence_exceptions' => null,
            'source' => null,
        ];
    }

    public function allDay(): static
    {
        return $this->state(function () {
            $date = fake()->dateTimeBetween('-1 month', '+2 months');
            $start = Carbon::instance($date)->startOfDay();

            return [
                'starts_at' => $start,
                'ends_at' => $start->copy()->endOfDay(),
                'is_all_day' => true,
            ];
        });
    }

    public function recurring(string $rrule = 'FREQ=WEEKLY;BYDAY=MO'): static
    {
        return $this->state(fn () => ['rrule' => $rrule]);
    }

    public function fromBill(): static
    {
        return $this->state(fn () => ['source' => 'bill']);
    }

    public function fromIncome(): static
    {
        return $this->state(fn () => ['source' => 'income']);
    }
}
