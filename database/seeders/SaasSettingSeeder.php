<?php

namespace Database\Seeders;

use App\Models\SaasSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class SaasSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $publicDisk = Storage::disk('public');
        
        // Clean up logos directory to prevent stale storage accumulation
        if ($publicDisk->exists('logos')) {
            $publicDisk->deleteDirectory('logos');
        }
        $publicDisk->makeDirectory('logos');

        $logoPath = 'logos/brand_logo.png';
        if (extension_loaded('gd')) {
            $im = imagecreatetruecolor(200, 200);
            $bg = imagecolorallocate($im, 79, 70, 229); // Indigo 600
            imagefill($im, 0, 0, $bg);
            
            $white = imagecolorallocate($im, 255, 255, 255);
            imagefilledellipse($im, 100, 100, 120, 120, $white);
            
            imagefilledellipse($im, 100, 100, 60, 60, $bg);

            ob_start();
            imagepng($im);
            $imageData = ob_get_clean();
            imagedestroy($im);
            
            $publicDisk->put($logoPath, $imageData);
        } else {
            $publicDisk->put($logoPath, 'dummy logo content');
        }

        SaasSetting::updateOrCreate([
            'id' => 1,
        ], [
            'app_name' => 'TurfBooking',
            'contact_email' => 'sandeep198558@gmail.com',
            'contact_mobile' => '9664588677',
            'address' => 'Mumbai, India',
            'logo_path' => $logoPath,
            'is_maintenance_mode' => false,
            'gemini_api_key' => null,
            'razorpay_key' => null,
            'razorpay_secret' => null,
            'mailgun_domain' => null,
            'mailgun_secret' => null,
            'mailgun_endpoint' => 'api.mailgun.net',
        ]);
    }
}
