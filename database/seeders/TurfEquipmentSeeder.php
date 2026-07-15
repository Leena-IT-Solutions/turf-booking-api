<?php

namespace Database\Seeders;

use App\Models\Turf;
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
        if ($turfs->isEmpty()) {
            return;
        }

        $defaultEquipments = [
            'FIFA Pro Soccer Balls',
            'Colored Training Bibs',
            'Tennis Rackets',
            'Cricket Wooden Stumps',
            'Cricket Leather Balls',
            'Cricket Bats',
            'Agility Ladders & Cones',
        ];

        foreach ($turfs as $turf) {
            // Pick 3 random equipment items for each turf
            $selected = (array) array_rand(array_flip($defaultEquipments), 3);
            foreach ($selected as $equipment) {
                TurfEquipment::create([
                    'turf_id' => $turf->id,
                    'equipment' => $equipment,
                    'is_active' => true,
                ]);
            }
        }
    }
}
