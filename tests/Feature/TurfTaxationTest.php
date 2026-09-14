<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Turf;
use App\Models\TurfSetting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TurfTaxationTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $location;
    protected $turf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('turf-admin');

        $this->location = Location::create([
            'user_id' => $this->admin->id,
            'name' => 'Mumbai Arena',
            'address' => 'Ghatkopar East',
        ]);

        $this->turf = Turf::create([
            'location_id' => $this->location->id,
            'name' => 'Apex Pro Arena',
            'type' => 'Synthetic',
        ]);

        session(['active_turf_id' => $this->turf->id]);
    }

    public function test_unauthorized_user_cannot_access_taxation(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $response = $this->actingAs($user)->get('/turf/taxation');
        $response->assertStatus(403);
    }

    public function test_turf_admin_can_view_taxation_page(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/turf/taxation');
        $response->assertOk();
        $response->assertSee('Taxation & Legal Details');
        $response->assertSee('Company Legal Identity');
        $response->assertSee('Tax & GST Identification');
        $response->assertSee('Registered Business Address');
    }

    public function test_taxation_navigation_link_is_present(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/turf/taxation');
        $response->assertOk();
        $response->assertSee(route('turf.taxation'));
        $response->assertSee(route('turf.coupons'));
        $response->assertSee(route('turf.settings'));
    }

    public function test_turf_admin_can_save_taxation_settings(): void
    {
        $this->actingAs($this->admin);

        Volt::test('turf.taxation-manager')
            ->set('company_name', 'Apex Sports Arena LLP')
            ->set('company_email', 'tax@apexsports.com')
            ->set('company_phone', '+91 9664588677')
            ->set('address', '101, Sports City Boulevard')
            ->set('city', 'Mumbai')
            ->set('state', 'Maharashtra')
            ->set('country', 'India')
            ->set('pincode', '400001')
            ->set('gst_number', '27ABCDE1234F1Z5')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Taxation & legal details saved successfully.');

        $this->assertDatabaseHas('turf_settings', [
            'turf_id' => $this->turf->id,
            'company_name' => 'Apex Sports Arena LLP',
            'company_email' => 'tax@apexsports.com',
            'company_phone' => '+91 9664588677',
            'address' => '101, Sports City Boulevard',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'country' => 'India',
            'pincode' => '400001',
            'gst_number' => '27ABCDE1234F1Z5',
        ]);
    }

    public function test_gst_validation_fails_on_invalid_format(): void
    {
        $this->actingAs($this->admin);

        Volt::test('turf.taxation-manager')
            ->set('gst_number', 'INVALID123')
            ->call('save')
            ->assertHasErrors(['gst_number']);
    }
}
