<?php

namespace Database\Seeders;

use App\Models\Turf;
use App\Models\Facility;
use App\Models\Equipment;
use App\Models\Sport;
use Illuminate\Database\Seeder;

class TurfPivotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $turfs = Turf::all();
        $facilities = Facility::all();
        $equipments = Equipment::all();
        $sports = Sport::all();

        if ($turfs->isEmpty()) {
            return;
        }

        foreach ($turfs as $turf) {
            // Seed random active facilities (sync)
            if ($facilities->isNotEmpty()) {
                $turf->facilities()->sync($facilities->random(rand(2, 4))->pluck('id')->toArray());
            }

            // Seed random active equipments (sync)
            if ($equipments->isNotEmpty()) {
                $turf->turfEquipments()->sync($equipments->random(rand(2, 4))->pluck('id')->toArray());
            }

            // Seed random active sports (sync)
            if ($sports->isNotEmpty()) {
                $turf->sports()->sync($sports->random(rand(1, 2))->pluck('id')->toArray());
            }
        }
    }
}
