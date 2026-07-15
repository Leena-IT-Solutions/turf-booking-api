<?php

namespace Database\Seeders;

use App\Models\Slot;
use App\Models\SlotCategory;
use Illuminate\Database\Seeder;

class SlotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure categories exist
        $categories = [
            'Morning' => SlotCategory::firstOrCreate(['name' => 'Morning']),
            'Afternoon' => SlotCategory::firstOrCreate(['name' => 'Afternoon']),
            'Evening' => SlotCategory::firstOrCreate(['name' => 'Evening']),
            'Night' => SlotCategory::firstOrCreate(['name' => 'Night']),
        ];

        for ($i = 0; $i < 48; $i++) {
            $startMinutes = $i * 30;
            $endMinutes = ($i + 1) * 30;

            $fromHour = floor($startMinutes / 60);
            $fromMin = $startMinutes % 60;
            $toHour = floor($endMinutes / 60);
            $toMin = $endMinutes % 60;

            $fromTime = sprintf('%02d:%02d', $fromHour, $fromMin);
            $toTime = sprintf('%02d:%02d', $toHour % 24, $toMin);

            // Determine category
            if ($fromHour >= 6 && $fromHour < 12) {
                $categoryName = 'Morning';
            } elseif ($fromHour >= 12 && $fromHour < 17) {
                $categoryName = 'Afternoon';
            } elseif ($fromHour >= 17 && $fromHour < 22) {
                $categoryName = 'Evening';
            } else {
                $categoryName = 'Night';
            }

            $category = $categories[$categoryName];

            Slot::firstOrCreate([
                'slot_category_id' => $category->id,
                'from_time' => $fromTime,
                'to_time' => $toTime,
            ], [
                'duration' => 30,
                'is_active' => true,
            ]);
        }
    }
}
