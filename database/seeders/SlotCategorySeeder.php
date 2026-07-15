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
            'Morning',
            'Afternoon',
            'Evening',
            'Night',
        ];

        foreach ($categories as $category) {
            SlotCategory::firstOrCreate([
                'name' => $category,
            ], [
                'is_active' => true,
            ]);
        }
    }
}
