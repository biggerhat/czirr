<?php

namespace Database\Seeders;

use App\Models\EventType;
use Illuminate\Database\Seeder;

class EventTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Personal',
            'Work',
            'Health',
            'Social',
            'Travel',
            'Family',
            'Education',
            'Finance',
        ];

        foreach ($types as $i => $name) {
            EventType::firstOrCreate(
                ['user_id' => null, 'name' => $name],
                ['sort_order' => $i],
            );
        }
    }
}
