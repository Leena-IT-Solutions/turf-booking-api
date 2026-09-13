<?php

namespace Tests\Feature;

use App\Models\SaasSetting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SaasSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SaasSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(SaasSettingSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/saas/settings');
        $response->assertRedirect('/login');

        $response2 = $this->get('/saas/settings/legal');
        $response2->assertRedirect('/login');
    }

    public function test_unauthorized_user_cannot_access_settings(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $response = $this->actingAs($user)->get('/saas/settings');
        $response->assertStatus(403);

        $response2 = $this->actingAs($user)->get('/saas/settings/branding');
        $response2->assertStatus(403);
    }

    public function test_saas_admin_can_access_all_settings_pages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('saas-admin');

        $this->actingAs($user)->get('/saas/settings')->assertOk();
        $this->actingAs($user)->get('/saas/settings/branding')->assertOk();
        $this->actingAs($user)->get('/saas/settings/legal')->assertOk();
        $this->actingAs($user)->get('/saas/settings/application')->assertOk();
        $this->actingAs($user)->get('/saas/settings/credentials')->assertOk();
    }

    public function test_saas_branding_settings_can_be_saved(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        $file = UploadedFile::fake()->image('brand_logo.png');

        Volt::test('saas.settings.branding')
            ->assertSet('app_name', 'TurfBooking')
            ->assertSet('contact_email', 'sandeep198558@gmail.com')
            ->set('app_name', 'TurfBooking Pro')
            ->set('contact_email', 'support@turfbookingpro.com')
            ->set('contact_mobile', '9876543210')
            ->set('address', 'Marine Drive, Mumbai')
            ->set('new_logo', $file)
            ->call('saveSettings')
            ->assertHasNoErrors();

        $setting = SaasSetting::first();
        $this->assertEquals('TurfBooking Pro', $setting->app_name);
        $this->assertEquals('support@turfbookingpro.com', $setting->contact_email);
        $this->assertEquals('9876543210', $setting->contact_mobile);
        $this->assertEquals('Marine Drive, Mumbai', $setting->address);
        $this->assertNotNull($setting->logo_path);
        Storage::disk('public')->assertExists($setting->logo_path);
    }

    public function test_saas_legal_settings_can_be_saved(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        Volt::test('saas.settings.legal')
            ->set('company_name', 'Turf Arena Technologies LLP')
            ->set('company_email', 'finance@turfarena.com')
            ->set('company_phone', '9820098200')
            ->set('company_address', 'Tower 4, Bandra Kurla Complex')
            ->set('pincode', '400051')
            ->set('city', 'Mumbai')
            ->set('state', 'Maharashtra')
            ->set('country', 'India')
            ->set('gst_number', '27bbbbb1111b1z8')
            ->set('udyam_registration_number', 'udyam-mh-01-9999999')
            ->call('saveSettings')
            ->assertHasNoErrors();

        $setting = SaasSetting::first();
        $this->assertEquals('Turf Arena Technologies LLP', $setting->company_name);
        $this->assertEquals('finance@turfarena.com', $setting->company_email);
        $this->assertEquals('9820098200', $setting->company_phone);
        $this->assertEquals('400051', $setting->pincode);
        $this->assertEquals('Mumbai', $setting->city);
        $this->assertEquals('Maharashtra', $setting->state);
        $this->assertEquals('27BBBBB1111B1Z8', $setting->gst_number);
        $this->assertEquals('UDYAM-MH-01-9999999', $setting->udyam_registration_number);
    }

    public function test_saas_application_settings_can_be_saved(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        Volt::test('saas.settings.application')
            ->assertSet('turf_search_km', 10)
            ->assertSet('min_slots_booking', 2)
            ->set('turf_search_km', 25)
            ->set('min_slots_booking', 1)
            ->set('is_maintenance_mode', true)
            ->set('commission_percentage', 10.50)
            ->set('payment_gateway_percentage', 2.50)
            ->set('payout_hours', 48)
            ->set('payout_charges', 50.00)
            ->set('max_commission_due', 3000.00)
            ->set('commission_due_grace_days', 10)
            ->call('saveSettings')
            ->assertHasNoErrors();

        $setting = SaasSetting::first();
        $this->assertEquals(25, $setting->turf_search_km);
        $this->assertEquals(1, $setting->min_slots_booking);
        $this->assertTrue($setting->is_maintenance_mode);
        $this->assertEquals(10.50, $setting->commission_percentage);
        $this->assertEquals(2.50, $setting->payment_gateway_percentage);
        $this->assertEquals(48, $setting->payout_hours);
        $this->assertEquals(50.00, $setting->payout_charges);
        $this->assertEquals(3000.00, $setting->max_commission_due);
        $this->assertEquals(10, $setting->commission_due_grace_days);
    }

    public function test_saas_credentials_settings_can_be_saved(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        Volt::test('saas.settings.credentials')
            ->set('gemini_api_key', 'AIzaSyTestKey123')
            ->set('google_maps_api_key', 'AIzaSyMapsTestKey456')
            ->set('whatsapp_token', 'EAABtestToken789')
            ->set('whatsapp_phone_number_id', '1234567890123')
            ->set('whatsapp_business_account_id', '9876543210987')
            ->set('whatsapp_otp_template', 'custom_otp')
            ->set('razorpay_key', 'rzp_test_key123')
            ->set('razorpay_secret', 'rzp_secret_456')
            ->set('razorpayx_account_number', '7878780080319999')
            ->set('razorpayx_webhook_secret', 'whsec_test789')
            ->set('mailgun_domain', 'mg.testdomain.com')
            ->set('mailgun_secret', 'key-testsecret123')
            ->set('mailgun_endpoint', 'api.eu.mailgun.net')
            ->call('saveSettings')
            ->assertHasNoErrors();

        $setting = SaasSetting::first();
        $this->assertEquals('AIzaSyTestKey123', $setting->gemini_api_key);
        $this->assertEquals('AIzaSyMapsTestKey456', $setting->google_maps_api_key);
        $this->assertEquals('EAABtestToken789', $setting->whatsapp_token);
        $this->assertEquals('custom_otp', $setting->whatsapp_otp_template);
        $this->assertEquals('rzp_test_key123', $setting->razorpay_key);
        $this->assertEquals('7878780080319999', $setting->razorpayx_account_number);
        $this->assertEquals('mg.testdomain.com', $setting->mailgun_domain);
        $this->assertEquals('api.eu.mailgun.net', $setting->mailgun_endpoint);
    }
}
