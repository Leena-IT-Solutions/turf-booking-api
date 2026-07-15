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
            'Football (5-a-side)' => 'football',
            'Football (7-a-side)' => 'football',
            'Box Cricket' => 'cricket',
            'Lawn Tennis' => 'tennis',
            'Basketball' => 'basketball',
            'Volleyball' => 'volleyball',
            'Kabaddi' => '🤼',
            'Badminton' => '🏸',
            'Table Tennis' => '🏓',
        ];

        foreach ($sports as $name => $icon) {
            Sport::create([
                'name' => $name,
                'icon' => $icon,
                'is_active' => true,
            ]);
        }
    }
}
