<?php

namespace Database\Seeders;

use App\Models\SaasSetting;
use Illuminate\Database\Seeder;

class SaasSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SaasSetting::firstOrCreate([
            'id' => 1,
        ], [
            'app_name' => 'TurfBooking',
            'contact_email' => 'sandeep198558@gmail.com',
            'contact_mobile' => '9664588677',
            'address' => 'Mumbai, India',
            'is_maintenance_mode' => false,
        ]);
    }
}
