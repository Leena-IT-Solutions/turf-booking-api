<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Turf;
use Illuminate\Database\Seeder;

class TurfSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = Location::all();

        if ($locations->isEmpty()) {
            return;
        }

        foreach ($locations as $location) {
            Turf::firstOrCreate([
                'location_id' => $location->id,
                'name' => 'Main Turf A',
            ], [
                'type' => 'Synthetic',
                'description' => 'Premium high-density synthetic grass turf perfect for 5-a-side football and cricket.',
                'area' => '8,000 sq ft',
                'is_active' => true,
                'equipments' => 'Football, Goals, Bibs, Cricket Bat, Stumps',
            ]);

            Turf::firstOrCreate([
                'location_id' => $location->id,
                'name' => 'Pro Hard Court B',
            ], [
                'type' => 'Hard',
                'description' => 'Professional acrylic hard court designed for tennis, basketball and futsal matchplay.',
                'area' => '6,500 sq ft',
                'is_active' => true,
                'equipments' => 'Tennis Net, Basketball Hoops, Futsal Goals',
            ]);
        }
    }
}
