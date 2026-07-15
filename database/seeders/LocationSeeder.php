<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = [
            [
                'name' => 'Mumbai Sports Arena',
                'address' => 'Plot 14, Bandra Kurla Complex, Mumbai, Maharashtra 400051',
                'latitude' => 19.06820000,
                'longitude' => 72.87030000,
                'contact_number' => '+91 9876543210',
                'email' => 'bkcarena@sports.in',
            ],
            [
                'name' => 'Goa Turf Hub',
                'address' => 'Fatorda Stadium Outer Ground, Margao, Goa 403602',
                'latitude' => 15.29360000,
                'longitude' => 73.96140000,
                'contact_number' => '+91 9123456789',
                'email' => 'goahub@sports.in',
            ],
        ];

        $user = \App\Models\User::first() ?: \App\Models\User::factory()->create();

        foreach ($locations as $data) {
            $data['user_id'] = $user->id;
            Location::firstOrCreate(
                ['name' => $data['name']],
                $data
            );
        }
    }
}
