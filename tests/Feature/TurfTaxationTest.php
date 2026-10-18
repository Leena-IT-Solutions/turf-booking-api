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
        $response->assertSee('GST Billing');
        $response->assertSee('Maharashtra (27)');
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
            ->set('is_gst_billing_active', true)
            ->set('gst_pricing_type', 'included')
            ->set('gst_percentage', 18.00)
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
            'state_code' => '27',
            'country' => 'India',
            'pincode' => '400001',
            'is_gst_billing_active' => true,
            'gst_pricing_type' => 'included',
            'gst_percentage' => 18.00,
            'gst_number' => '27ABCDE1234F1Z5',
        ]);
    }

    public function test_selecting_state_automatically_sets_and_stores_state_code(): void
    {
        $this->actingAs($this->admin);

        Volt::test('turf.taxation-manager')
            ->set('state', 'Delhi')
            ->assertSet('state_code', '07')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('turf_settings', [
            'turf_id' => $this->turf->id,
            'state' => 'Delhi',
            'state_code' => '07',
        ]);

        Volt::test('turf.taxation-manager')
            ->set('state', 'Karnataka')
            ->assertSet('state_code', '29')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('turf_settings', [
            'turf_id' => $this->turf->id,
            'state' => 'Karnataka',
            'state_code' => '29',
        ]);
    }

    public function test_turf_admin_can_set_gst_excluded(): void
    {
        $this->actingAs($this->admin);

        Volt::test('turf.taxation-manager')
            ->set('is_gst_billing_active', true)
            ->set('gst_pricing_type', 'excluded')
            ->set('gst_percentage', 18.00)
            ->set('gst_number', '27ABCDE1234F1Z5')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('turf_settings', [
            'turf_id' => $this->turf->id,
            'is_gst_billing_active' => true,
            'gst_pricing_type' => 'excluded',
        ]);
    }

    public function test_turf_admin_can_disable_gst_billing(): void
    {
        $this->actingAs($this->admin);

        Volt::test('turf.taxation-manager')
            ->set('state', 'Maharashtra')
            ->set('is_gst_billing_active', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('turf_settings', [
            'turf_id' => $this->turf->id,
            'is_gst_billing_active' => false,
            'state' => 'Maharashtra',
            'state_code' => '27',
        ]);
    }

    public function test_gst_validation_fails_on_invalid_format(): void
    {
        $this->actingAs($this->admin);

        Volt::test('turf.taxation-manager')
            ->set('state', 'Maharashtra')
            ->set('gst_number', 'INVALID123')
            ->call('save')
            ->assertHasErrors(['gst_number']);
    }

    public function test_saving_with_no_state_shows_error_and_does_not_save(): void
    {
        $this->actingAs($this->admin);

        Volt::test('turf.taxation-manager')
            ->set('state', '')
            ->call('save')
            ->assertHasErrors(['state'])
            ->assertSee('Select the state where this turf is located. It decides how GST is charged.');

        $this->assertDatabaseMissing('turf_settings', [
            'turf_id' => $this->turf->id,
            'state' => '',
        ]);
    }

    public function test_gstin_mismatch_with_state_shows_error_and_does_not_save(): void
    {
        $this->actingAs($this->admin);

        Volt::test('turf.taxation-manager')
            ->set('gst_number', '29ABCDE1234F1Z5')
            ->set('state', 'Maharashtra')
            ->call('save')
            ->assertHasErrors(['gst_number'])
            ->assertSee('This GSTIN is registered in Karnataka, but the turf\'s state is Maharashtra');
    }

    public function test_gst_billing_active_requires_gst_number(): void
    {
        $this->actingAs($this->admin);

        Volt::test('turf.taxation-manager')
            ->set('state', 'Maharashtra')
            ->set('is_gst_billing_active', true)
            ->set('gst_number', '')
            ->call('save')
            ->assertHasErrors(['gst_number'])
            ->assertSee('Enter your GSTIN to issue GST invoices.');
    }

    public function test_typing_gstin_switches_state_and_code(): void
    {
        $this->actingAs($this->admin);

        Volt::test('turf.taxation-manager')
            ->set('state', 'Maharashtra')
            ->assertSet('state_code', '27')
            ->set('gst_number', '29ABCDE1234F1Z5')
            ->assertSet('state', 'Karnataka')
            ->assertSet('state_code', '29');
    }
}
