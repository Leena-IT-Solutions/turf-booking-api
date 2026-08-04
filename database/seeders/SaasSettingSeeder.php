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
            'whatsapp_token' => 'EAAUBGZBnC77YBSCAiToHlFLLniZCZAQZBjE8wQUaqKTjnlAIg1S2xE55gyY0yLpB3ZA9UYrx9SPgkszaxVkQfzI86EHP61CWCaeYQY0OZAX5JVeq3PZCFPko9fYi2g5MFcg1hZCy1o9VGDiQf8X8AhzWKN7jODf6tBq67q3NuGf16lF5CPK25oZBCoWQzHggEQb7O3wH4DXg0vY04SFbYZCV20QQsvPyhHZCKyfeySa8TyOSD9pinAgpI4ZBTDlPyfiZCMYfTiBALpHlgsnNrV8gg7BzU',
            'whatsapp_phone_number_id' => '1275286312329223',
            'whatsapp_business_account_id' => '2693008137759947',
            'gemini_api_key' => null,
            'google_maps_api_key' => null,
            'razorpay_key' => null,
            'razorpay_secret' => null,
            'mailgun_domain' => null,
            'mailgun_secret' => null,
            'mailgun_endpoint' => 'api.mailgun.net',
            'turf_search_km' => 10,
            'min_slots_booking' => 2,
            'commission_percentage' => 7.00,
        ]);
    }
}
