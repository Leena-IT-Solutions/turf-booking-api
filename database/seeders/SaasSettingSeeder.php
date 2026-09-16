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
            'app_name' => 'Turf Booking',
            'contact_email' => 'leenaitsolutions@gmail.com',
            'contact_mobile' => '9096189183',
            'address' => 'Ambernath, India',
            'company_name' => 'Leena IT Solutions',
            'company_email' => 'leenaitsolutions@gmail.com',
            'company_phone' => '9096189183',
            'company_address' => 'Kunthu Apartment, Building No 17, A102, Sarvodaya Nagar',
            'pincode' => '421501',
            'city' => 'Thane',
            'state' => 'Maharashtra',
            'country' => 'India',
            'gst_number' => '',
            'udyam_registration_number' => 'UDYAM-MH-33-0290010',
            'subscription_gst_sac' => '998314',
            'subscription_gst_percentage' => 18.00,
            'commission_gst_sac' => '998599',
            'commission_gst_percentage' => 18.00,
            'booking_gst_sac' => '999652',
            'booking_gst_percentage' => 18.00,
            'logo_path' => $logoPath,
            'is_maintenance_mode' => false,
            'whatsapp_token' => '',
            'whatsapp_phone_number_id' => '',
            'whatsapp_business_account_id' => '',
            'whatsapp_otp_template' => 'otp_turf_booking',
            'gemini_api_key' => null,
            'google_maps_api_key' => null,
            'razorpay_key' => null,
            'razorpay_secret' => null,
            'mailgun_domain' => null,
            'mailgun_secret' => null,
            'mailgun_endpoint' => 'api.mailgun.net',
            'turf_search_km' => 15,
            'min_slots_booking' => 2,
            'free_trial_days' => 30,
            'commission_percentage' => 8.00,
        ]);
    }
}
