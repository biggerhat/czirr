<?php

namespace Database\Seeders;

use App\Models\Cuisine;
use Illuminate\Database\Seeder;

class CuisineSeeder extends Seeder
{
    public function run(): void
    {
        $cuisines = [
            'Italian', 'Mexican', 'Chinese', 'Japanese', 'Indian',
            'Thai', 'French', 'Mediterranean', 'American', 'Korean',
            'Vietnamese', 'Greek', 'Spanish', 'Middle Eastern', 'Caribbean',
            'Ethiopian', 'Brazilian', 'German', 'British', 'Cajun',
        ];

        foreach ($cuisines as $name) {
            Cuisine::firstOrCreate(
                ['user_id' => null, 'name' => $name],
            );
        }
    }
}
