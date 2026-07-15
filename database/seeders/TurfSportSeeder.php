<?php

namespace Database\Seeders;

use App\Models\Turf;
use App\Models\TurfSport;
use Illuminate\Database\Seeder;

class TurfSportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TurfSport::truncate();

        $turfs = Turf::all();
        if ($turfs->isEmpty()) {
            return;
        }

        $defaultSports = [
            'Football (5-a-side)',
            'Football (7-a-side)',
            'Box Cricket',
            'Lawn Tennis',
            'Volleyball',
            'Kabaddi',
        ];

        foreach ($turfs as $turf) {
            // Pick 2 random sports for each turf
            $selected = (array) array_rand(array_flip($defaultSports), 2);
            foreach ($selected as $sport) {
                TurfSport::create([
                    'turf_id' => $turf->id,
                    'sport' => $sport,
                    'is_active' => true,
                ]);
            }
        }
    }
}
