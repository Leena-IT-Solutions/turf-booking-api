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
            'Midnight' => ['icon' => '🌌', 'sort_order' => 1],
            'Morning' => ['icon' => '🌅', 'sort_order' => 2],
            'Afternoon' => ['icon' => '☀️', 'sort_order' => 3],
            'Evening' => ['icon' => '🌇', 'sort_order' => 4],
            'Night' => ['icon' => '🌙', 'sort_order' => 5],
        ];

        foreach ($categories as $name => $data) {
            SlotCategory::updateOrCreate([
                'name' => $name,
            ], [
                'icon' => $data['icon'],
                'sort_order' => $data['sort_order'],
                'is_active' => true,
            ]);
        }
    }
}
