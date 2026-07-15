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
    }

    public function test_unauthorized_user_cannot_access_settings(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $response = $this->actingAs($user)->get('/saas/settings');
        $response->assertStatus(403);
    }

    public function test_saas_admin_can_access_settings(): void
    {
        $user = User::factory()->create();
        $user->assignRole('saas-admin');

        $response = $this->actingAs($user)->get('/saas/settings');
        $response->assertOk();
    }

    public function test_saas_settings_loads_with_default_values(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        Volt::test('saas.settings-manager')
            ->assertSet('app_name', 'TurfBooking')
            ->assertSet('contact_email', 'sandeep198558@gmail.com')
            ->assertSet('contact_mobile', '9664588677')
            ->assertSet('is_maintenance_mode', false);
    }

    public function test_contact_email_must_be_valid_email(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        Volt::test('saas.settings-manager')
            ->set('contact_email', 'invalid-email')
            ->call('saveSettings')
            ->assertHasErrors(['contact_email' => 'email']);
    }

    public function test_saas_admin_can_save_settings_and_upload_logo(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        $file = UploadedFile::fake()->image('logo.png');

        Volt::test('saas.settings-manager')
            ->set('app_name', 'Updated Turf App')
            ->set('contact_email', 'updated@example.com')
            ->set('contact_mobile', '9999999999')
            ->set('address', 'New Delhi, India')
            ->set('is_maintenance_mode', true)
            ->set('new_logo', $file)
            ->call('saveSettings')
            ->assertHasNoErrors();

        $setting = SaasSetting::first();
        $this->assertEquals('Updated Turf App', $setting->app_name);
        $this->assertEquals('updated@example.com', $setting->contact_email);
        $this->assertEquals('9999999999', $setting->contact_mobile);
        $this->assertEquals('New Delhi, India', $setting->address);
        $this->assertTrue($setting->is_maintenance_mode);
        $this->assertNotNull($setting->logo_path);

        Storage::disk('public')->assertExists($setting->logo_path);
    }
}
