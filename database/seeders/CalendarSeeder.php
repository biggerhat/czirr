<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventType;
use App\Models\FamilyMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CalendarSeeder extends Seeder
{
    public int $count = 50;

    private const ONE_OFF_TITLES = [
        'Personal' => ['Dentist appointment', 'Haircut', 'Car wash', 'Grocery run', 'Bank errand'],
        'Work' => ['Team standup', 'Sprint review', 'Client call', '1:1 with manager', 'Workshop'],
        'Health' => ['Doctor checkup', 'Eye exam', 'Physical therapy', 'Flu shot', 'Lab work'],
        'Social' => ['Coffee with Sarah', 'Lunch with Mike', 'Game night', 'Housewarming party', 'Birthday party'],
        'Travel' => ['Airport pickup', 'Pack for trip', 'Road trip', 'Hotel check-in', 'Rental car return'],
        'Family' => ['Family photos', 'Parent-teacher meeting', 'Kids playdate', 'Family BBQ', 'School pickup'],
        'Education' => ['Piano lesson', 'Study group', 'Book club', 'Online course', 'Library visit'],
        'Finance' => ['Tax prep', 'Financial advisor', 'Budget review', 'Insurance renewal', 'Lease signing'],
    ];

    private const RECURRING_TITLES = [
        'Personal' => ['Morning jog', 'Meditation', 'Journal time'],
        'Work' => ['Weekly sync', 'Daily standup', 'Team retrospective'],
        'Health' => ['Gym session', 'Yoga class', 'Swimming'],
        'Social' => ['Board game night', 'Dinner club', 'Volunteer shift'],
        'Family' => ['Family dinner', 'Movie night', 'Weekend chores'],
        'Education' => ['Guitar practice', 'Language lesson', 'Reading hour'],
    ];

    private const WEEKDAYS = ['MO', 'TU', 'WE', 'TH', 'FR'];

    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first() ?? User::first();

        if (! $user) {
            $this->command?->error('No user found. Run DatabaseSeeder first.');

            return;
        }

        $eventTypes = EventType::availableTo($user->id)->pluck('id', 'name');

        if ($eventTypes->isEmpty()) {
            $this->call(EventTypeSeeder::class);
            $eventTypes = EventType::availableTo($user->id)->pluck('id', 'name');
        }

        $familyMemberIds = FamilyMember::where('user_id', $user->id)->pluck('id')->all();

        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $daysInMonth = $now->daysInMonth;

        $recurringCount = (int) round($this->count * 0.2);
        $oneOffCount = $this->count - $recurringCount;

        $typeNames = $eventTypes->keys()->all();

        // --- One-off events ---
        for ($i = 0; $i < $oneOffCount; $i++) {
            $typeName = $typeNames[array_rand($typeNames)];
            $typeId = $eventTypes[$typeName];
            $titles = self::ONE_OFF_TITLES[$typeName] ?? self::ONE_OFF_TITLES['Personal'];
            $title = $titles[array_rand($titles)];

            $isAllDay = fake()->boolean(15);
            $day = $monthStart->copy()->addDays(rand(0, $daysInMonth - 1));

            if ($isAllDay) {
                $event = Event::factory()->allDay()->create([
                    'user_id' => $user->id,
                    'event_type_id' => $typeId,
                    'title' => $title,
                    'starts_at' => $day->copy()->startOfDay(),
                    'ends_at' => $day->copy()->endOfDay(),
                ]);
            } else {
                $hour = rand(7, 20);
                $minute = fake()->randomElement([0, 15, 30, 45]);
                $durationMinutes = fake()->randomElement([30, 45, 60, 90, 120, 180]);

                $start = $day->copy()->setHour($hour)->setMinute($minute)->setSecond(0);
                $end = $start->copy()->addMinutes($durationMinutes);

                $event = Event::factory()->create([
                    'user_id' => $user->id,
                    'event_type_id' => $typeId,
                    'title' => $title,
                    'starts_at' => $start,
                    'ends_at' => $end,
                ]);
            }

            if ($familyMemberIds && fake()->boolean(40)) {
                $memberCount = rand(1, min(3, count($familyMemberIds)));
                $members = fake()->randomElements($familyMemberIds, $memberCount);
                $event->familyMembers()->attach($members);
            }
        }

        // --- Recurring events ---
        $rrulePatterns = [
            fn () => 'FREQ=DAILY',
            fn () => 'FREQ=WEEKLY;BYDAY='.self::WEEKDAYS[array_rand(self::WEEKDAYS)],
            fn () => 'FREQ=WEEKLY;INTERVAL=2;BYDAY='.self::WEEKDAYS[array_rand(self::WEEKDAYS)],
        ];

        $recurringTypeNames = array_intersect($typeNames, array_keys(self::RECURRING_TITLES));
        if (empty($recurringTypeNames)) {
            $recurringTypeNames = $typeNames;
        }
        $recurringTypeNames = array_values($recurringTypeNames);

        for ($i = 0; $i < $recurringCount; $i++) {
            $typeName = $recurringTypeNames[array_rand($recurringTypeNames)];
            $typeId = $eventTypes[$typeName];
            $titles = self::RECURRING_TITLES[$typeName] ?? self::RECURRING_TITLES['Personal'];
            $title = $titles[array_rand($titles)];

            $day = $monthStart->copy()->addDays(rand(0, 6));
            $hour = rand(7, 19);
            $minute = fake()->randomElement([0, 15, 30]);
            $durationMinutes = fake()->randomElement([30, 45, 60, 90, 120]);

            $start = $day->copy()->setHour($hour)->setMinute($minute)->setSecond(0);
            $end = $start->copy()->addMinutes($durationMinutes);

            $rrule = $rrulePatterns[array_rand($rrulePatterns)]();

            $event = Event::factory()->recurring($rrule)->create([
                'user_id' => $user->id,
                'event_type_id' => $typeId,
                'title' => $title,
                'starts_at' => $start,
                'ends_at' => $end,
            ]);

            if ($familyMemberIds && fake()->boolean(50)) {
                $memberCount = rand(1, min(3, count($familyMemberIds)));
                $members = fake()->randomElements($familyMemberIds, $memberCount);
                $event->familyMembers()->attach($members);
            }
        }

        $this->command?->info("Seeded {$this->count} calendar events ({$oneOffCount} one-off, {$recurringCount} recurring) for {$now->format('F Y')}.");
    }
}
