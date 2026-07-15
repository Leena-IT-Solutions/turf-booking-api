<?php

namespace Database\Seeders;

use App\Models\Turf;
use App\Models\TurfPhoto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class TurfPhotoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $publicDisk = Storage::disk('public');

        // Clear existing files in public/turf_photos to keep clean storage
        if ($publicDisk->exists('turf_photos')) {
            $publicDisk->deleteDirectory('turf_photos');
        }
        $publicDisk->makeDirectory('turf_photos');

        // Truncate records
        TurfPhoto::truncate();

        $turfs = Turf::all();
        if ($turfs->isEmpty()) {
            return;
        }

        // Color palettes to generate beautiful mock turf images
        $colors = [
            [30, 41, 59],   // Slate 800
            [15, 118, 110], // Teal 700
            [67, 56, 202],  // Indigo 700
            [31, 41, 55],   // Gray 800
        ];

        foreach ($turfs as $index => $turf) {
            for ($i = 1; $i <= 2; $i++) {
                $filename = "turf_{$turf->id}_photo_{$i}.png";
                $filePath = "turf_photos/{$filename}";
                
                $color = $colors[($index + $i) % count($colors)];

                // Generate dummy image if GD is available
                if (extension_loaded('gd')) {
                    $im = imagecreatetruecolor(800, 450);
                    $bg = imagecolorallocate($im, $color[0], $color[1], $color[2]);
                    imagefill($im, 0, 0, $bg);

                    // Add some decorative court-like lines
                    $lineColor = imagecolorallocatealpha($im, 255, 255, 255, 100);
                    // Boundary box
                    imagerectangle($im, 20, 20, 780, 430, $lineColor);
                    // Center line
                    imageline($im, 400, 20, 400, 430, $lineColor);
                    // Center circle
                    imagearc($im, 400, 225, 150, 150, 0, 360, $lineColor);

                    ob_start();
                    imagepng($im);
                    $imageData = ob_get_clean();
                    imagedestroy($im);

                    $publicDisk->put($filePath, $imageData);
                } else {
                    $publicDisk->put($filePath, 'dummy image content');
                }

                TurfPhoto::create([
                    'turf_id' => $turf->id,
                    'photo' => $filePath,
                    'is_active' => true,
                ]);
            }
        }
    }
}
