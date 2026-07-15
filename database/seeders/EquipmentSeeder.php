<?php

namespace Database\Seeders;

use App\Models\Equipment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class EquipmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Equipment::truncate();
        Schema::enableForeignKeyConstraints();

        $equipments = [
            'FIFA Pro Soccer Balls',
            'Colored Training Bibs (Red)',
            'Colored Training Bibs (Blue)',
            'Tennis Rackets',
            'Cricket Bats (English Willow)',
            'Cricket Leather Balls',
            'Cricket Wooden Stumps',
            'Agility Cones & Ladders',
            'Basketballs',
            'Volleyballs',
            'Goal Post Nets',
            'Tennis Balls',
        ];

        foreach ($equipments as $name) {
            Equipment::create([
                'name' => $name,
                'is_active' => true,
            ]);
        }
    }
}
