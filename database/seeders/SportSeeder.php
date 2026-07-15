<?php

namespace Database\Seeders;

use App\Models\Sport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class SportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Sport::truncate();
        Schema::enableForeignKeyConstraints();

        $sports = [
            'Football (5-a-side)',
            'Football (7-a-side)',
            'Box Cricket',
            'Lawn Tennis',
            'Basketball',
            'Volleyball',
            'Kabaddi',
            'Badminton',
            'Table Tennis',
        ];

        foreach ($sports as $name) {
            Sport::create([
                'name' => $name,
                'is_active' => true,
            ]);
        }
    }
}
