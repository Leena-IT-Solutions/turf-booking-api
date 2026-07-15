<?php

namespace Database\Seeders;

use App\Models\Turf;
use App\Models\Facility;
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
        $facilities = Facility::all();
        if ($turfs->isEmpty() || $facilities->isEmpty()) {
            return;
        }

        foreach ($turfs as $turf) {
            // Pick 3 random facility IDs for each turf
            $selectedIds = $facilities->random(3)->pluck('id');
            foreach ($selectedIds as $facilityId) {
                TurfFacility::create([
                    'turf_id' => $turf->id,
                    'facility_id' => $facilityId,
                    'is_active' => true,
                ]);
            }
        }
    }
}
