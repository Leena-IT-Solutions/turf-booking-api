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
            'FIFA Pro Soccer Balls' => 'soccer-ball',
            'Colored Training Bibs (Red)' => '🔴',
            'Colored Training Bibs (Blue)' => '🔵',
            'Tennis Rackets' => '🎾',
            'Cricket Bats (English Willow)' => 'cricket-bat',
            'Cricket Leather Balls' => 'cricket-ball',
            'Cricket Wooden Stumps' => 'cricket-stumps',
            'Agility Cones & Ladders' => '📐',
            'Basketballs' => 'basketball-ball',
            'Volleyballs' => '🏐',
            'Goal Post Nets' => '🥅',
            'Tennis Balls' => 'tennis-ball',
        ];

        foreach ($equipments as $name => $icon) {
            Equipment::create([
                'name' => $name,
                'icon' => $icon,
                'is_active' => true,
            ]);
        }
    }
}
