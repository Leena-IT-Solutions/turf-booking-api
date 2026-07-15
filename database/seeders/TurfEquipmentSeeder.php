<?php

namespace Database\Seeders;

use App\Models\Turf;
use App\Models\Equipment;
use App\Models\TurfEquipment;
use Illuminate\Database\Seeder;

class TurfEquipmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TurfEquipment::truncate();

        $turfs = Turf::all();
        $equipments = Equipment::all();
        if ($turfs->isEmpty() || $equipments->isEmpty()) {
            return;
        }

        foreach ($turfs as $turf) {
            // Pick 3 random equipment IDs for each turf
            $selectedIds = $equipments->random(3)->pluck('id');
            foreach ($selectedIds as $equipmentId) {
                TurfEquipment::create([
                    'turf_id' => $turf->id,
                    'equipment_id' => $equipmentId,
                    'is_active' => true,
                ]);
            }
        }
    }
}
