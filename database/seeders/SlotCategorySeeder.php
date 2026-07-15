<?php

namespace Database\Seeders;

use App\Models\SlotCategory;
use Illuminate\Database\Seeder;

class SlotCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Morning' => '🌅',
            'Afternoon' => '☀️',
            'Evening' => '🌇',
            'Night' => '🌙',
            'Midnight' => '🌌',
        ];

        foreach ($categories as $name => $icon) {
            SlotCategory::updateOrCreate([
                'name' => $name,
            ], [
                'icon' => $icon,
                'is_active' => true,
            ]);
        }
    }
}
