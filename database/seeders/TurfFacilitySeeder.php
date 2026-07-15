<?php

namespace Database\Seeders;

use App\Models\Turf;
use App\Models\TurfFacility;
use Illuminate\Database\Seeder;

class TurfFacilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TurfFacility::truncate();

        $turfs = Turf::all();
        if ($turfs->isEmpty()) {
            return;
        }

        $defaultFacilities = [
            'Locker Rooms',
            'Shower & Washroom',
            'Free Parking',
            'Water Dispenser',
            'Night Lighting',
            'First Aid Station',
        ];

        foreach ($turfs as $turf) {
            // Pick 3 random facilities for each turf
            $selected = (array) array_rand(array_flip($defaultFacilities), 3);
            foreach ($selected as $facility) {
                TurfFacility::create([
                    'turf_id' => $turf->id,
                    'facility' => $facility,
                    'is_active' => true,
                ]);
            }
        }
    }
}
