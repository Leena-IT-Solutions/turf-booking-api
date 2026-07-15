<?php

namespace Database\Seeders;

use App\Models\Facility;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class FacilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Facility::truncate();
        Schema::enableForeignKeyConstraints();

        $facilities = [
            'Locker Rooms' => 'key',
            'Shower & Washroom' => 'shower',
            'Free Parking' => 'parking',
            'Water Dispenser' => 'water',
            'Night Lighting (Floodlights)' => 'light',
            'First Aid Station' => 'first-aid',
            'Cafeteria / Canteen' => 'coffee',
            'Spectator Seating' => 'seating',
            'Waiting Lounge' => '🛋️',
            'Wi-Fi Zone' => 'wifi',
        ];

        foreach ($facilities as $name => $icon) {
            Facility::create([
                'name' => $name,
                'icon' => $icon,
                'is_active' => true,
            ]);
        }
    }
}
