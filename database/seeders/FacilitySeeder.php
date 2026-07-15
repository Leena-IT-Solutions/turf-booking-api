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
            'Locker Rooms',
            'Shower & Washroom',
            'Free Parking',
            'Water Dispenser',
            'Night Lighting (Floodlights)',
            'First Aid Station',
            'Cafeteria / Canteen',
            'Spectator Seating',
            'Waiting Lounge',
            'Wi-Fi Zone',
        ];

        foreach ($facilities as $name) {
            Facility::create([
                'name' => $name,
                'is_active' => true,
            ]);
        }
    }
}
