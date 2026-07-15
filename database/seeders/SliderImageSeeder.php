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
        if (!$publicDisk->exists('sliders')) {
            $publicDisk->makeDirectory('sliders');
        }

        $slides = [
            [
                'title' => 'Unleash Your Game - Premium Turf Booking',
                'link_url' => 'https://example.com/bookings',
                'order' => 1,
                'color' => [79, 70, 229], // Indigo 600
                'filename' => 'slide_1.png',
            ],
            [
                'title' => 'Exclusive Summer Discounts - Save up to 30%',
                'link_url' => 'https://example.com/offers',
                'order' => 2,
                'color' => [16, 185, 129], // Emerald 500
                'filename' => 'slide_2.png',
            ],
            [
                'title' => 'Rain or Shine - Play on All-Weather Indoors',
                'link_url' => 'https://example.com/locations',
                'order' => 3,
                'color' => [245, 158, 11], // Amber 500
                'filename' => 'slide_3.png',
            ],
        ];

        // Truncate existing slides
        SliderImage::truncate();

        foreach ($slides as $slideData) {
            $imagePath = 'sliders/' . $slideData['filename'];

            // Programmatically generate a beautiful placeholder image if it doesn't exist
            if (extension_loaded('gd')) {
                $im = imagecreatetruecolor(1200, 400);
                $bg = imagecolorallocate($im, $slideData['color'][0], $slideData['color'][1], $slideData['color'][2]);
                imagefill($im, 0, 0, $bg);

                // Add simple diagonal design pattern lines
                $lineColor = imagecolorallocatealpha($im, 255, 255, 255, 110);
                for ($i = 0; $i < 1200; $i += 40) {
                    imageline($im, $i, 0, $i - 400, 400, $lineColor);
                }

                ob_start();
                imagepng($im);
                $imageData = ob_get_clean();
                imagedestroy($im);
                
                $publicDisk->put($imagePath, $imageData);
            } else {
                // Fallback dummy file in case GD is missing
                $publicDisk->put($imagePath, 'dummy image content');
            }

            SliderImage::create([
                'title' => $slideData['title'],
                'image_path' => $imagePath,
                'link_url' => $slideData['link_url'],
                'order' => $slideData['order'],
                'is_active' => true,
            ]);
        }
    }
}
