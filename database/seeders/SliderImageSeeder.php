<?php

namespace Database\Seeders;

use App\Models\SliderImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class SliderImageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $publicDisk = Storage::disk('public');
        
        // Clean old default fallback slides
        foreach (['slide_1.png', 'slide_2.png', 'slide_3.png'] as $oldFile) {
            if ($publicDisk->exists('sliders/' . $oldFile)) {
                $publicDisk->delete('sliders/' . $oldFile);
            }
        }
        $publicDisk->makeDirectory('sliders');

        $slides = [
            [
                'title' => 'Own a Sports Turf? List & Grow With Us!',
                'link_url' => '/list-your-turf',
                'order' => 1,
                'color' => [16, 185, 129],
                'filename' => 'list_your_turf.png',
            ],
            [
                'title' => 'Unleash Your Game - Premium Turf Booking',
                'link_url' => 'https://example.com/bookings',
                'order' => 2,
                'color' => [79, 70, 229], // Indigo 600
                'filename' => 'slide_1.png',
            ],
            [
                'title' => 'Exclusive Summer Discounts - Save up to 30%',
                'link_url' => 'https://example.com/offers',
                'order' => 3,
                'color' => [16, 185, 129], // Emerald 500
                'filename' => 'slide_2.png',
            ],
            [
                'title' => 'Rain or Shine - Play on All-Weather Indoors',
                'link_url' => 'https://example.com/locations',
                'order' => 4,
                'color' => [245, 158, 11], // Amber 500
                'filename' => 'slide_3.png',
            ],
        ];

        // Truncate existing slides
        SliderImage::truncate();

        foreach ($slides as $slideData) {
            $filename = $slideData['filename'];
            $targetPath = 'sliders/' . $filename;
            $sourcePath = base_path('images/' . $filename);

            if (file_exists($sourcePath)) {
                $publicDisk->put($targetPath, file_get_contents($sourcePath));
            }

            SliderImage::create([
                'title' => $slideData['title'],
                'image_path' => $targetPath,
                'link_url' => $slideData['link_url'],
                'order' => $slideData['order'],
                'is_active' => true,
            ]);
        }
    }
}
