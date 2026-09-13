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
        if (file_exists(public_path('images/logo.png'))) {
            $publicDisk->put($logoPath, file_get_contents(public_path('images/logo.png')));
        } elseif (extension_loaded('gd')) {
            $im = imagecreatetruecolor(200, 200);
            $bg = imagecolorallocate($im, 79, 71, 228); // Indigo 600
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
            'company_name' => 'TurfBooking Private Limited',
            'company_email' => 'legal@turfbooking.com',
            'company_phone' => '9664588677',
            'company_address' => '101, Sports Arena Tower, Andheri East',
            'pincode' => '400069',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'country' => 'India',
            'gst_number' => '27AAAAA0000A1Z5',
            'udyam_registration_number' => 'UDYAM-MH-01-0000000',
            'subscription_gst_sac' => '998314',
            'subscription_gst_percentage' => 18.00,
            'commission_gst_sac' => '998599',
            'commission_gst_percentage' => 18.00,
            'booking_gst_sac' => '999652',
            'booking_gst_percentage' => 18.00,
            'logo_path' => $logoPath,
            'is_maintenance_mode' => false,
            'whatsapp_token' => 'EAAUBGZBnC77YBSDzLTaqC8WvRPKn2YDPsZBjtmacAtl1fwh3I9kQoZCTzAbponWdU5l97FCwhRfQWI5Mk6ZBvfP3KHqR2qoj3DVrz07QtIa157hkzOLqOh54ixrywHfKGPH3d6ZACx6gwSZBAYNMDZAmGTkEYKYRS0Ccrk7D3U6sKmQ0isARVPxAqiuMZAVdEgZDZD',
            'whatsapp_phone_number_id' => '1235536072981098',
            'whatsapp_business_account_id' => '2934433673575027',
            'whatsapp_otp_template' => 'otp_turf_booking',
            'gemini_api_key' => null,
            'google_maps_api_key' => null,
            'razorpay_key' => null,
            'razorpay_secret' => null,
            'mailgun_domain' => null,
            'mailgun_secret' => null,
            'mailgun_endpoint' => 'api.mailgun.net',
            'turf_search_km' => 10,
            'min_slots_booking' => 2,
            'free_trial_days' => 30,
            'commission_percentage' => 7.00,
        ]);
    }
}
