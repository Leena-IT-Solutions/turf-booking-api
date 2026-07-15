<?php

namespace Database\Seeders;

use App\Models\Turf;
use App\Models\Sport;
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
        $sports = Sport::all();
        if ($turfs->isEmpty() || $sports->isEmpty()) {
            return;
        }

        foreach ($turfs as $turf) {
            // Pick 2 random sport IDs for each turf
            $selectedIds = $sports->random(2)->pluck('id');
            foreach ($selectedIds as $sportId) {
                TurfSport::create([
                    'turf_id' => $turf->id,
                    'sport_id' => $sportId,
                    'is_active' => true,
                ]);
            }
        }
    }
}
