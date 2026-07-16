<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Turf;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TurfSettingsTest extends TestCase
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
            'name' => 'Pro turf A',
            'type' => 'Synthetic',
        ]);

        // Put active turf id in session
        session(['active_turf_id' => $this->turf->id]);
    }

    public function test_unauthorized_user_cannot_access_settings(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $response = $this->actingAs($user)->get('/turf/settings');
        $response->assertStatus(403);
    }

    public function test_turf_admin_can_view_settings(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/turf/settings');
        $response->assertOk();

        Volt::test('turf.settings-manager')
            ->assertSee('Payment Settings')
            ->assertSee('Booking & Window Settings')
            ->assertSee('Cancellation Settings');
    }

    public function test_can_save_turf_settings(): void
    {
        $this->actingAs($this->admin);

        Volt::test('turf.settings-manager')
            ->set('is_online_payment_active', true)
            ->set('is_part_payment_active', true)
            ->set('part_payment_type', 'flat')
            ->set('part_payment_value', 350.00)
            ->set('is_pay_at_location_active', false)
            ->set('is_booking_open', true)
            ->set('booking_open_days', 60)
            ->set('is_manager_booking_active', false)
            ->set('is_cancellation_active', true)
            ->set('cancellation_hours', 12)
            ->set('cancellation_fee', 150.00)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('turfs', [
            'id' => $this->turf->id,
            'is_online_payment_active' => true,
            'is_part_payment_active' => true,
            'part_payment_type' => 'flat',
            'part_payment_value' => 350.00,
            'is_pay_at_location_active' => false,
            'is_booking_open' => true,
            'booking_open_days' => 60,
            'is_manager_booking_active' => false,
            'is_cancellation_active' => true,
            'cancellation_hours' => 12,
            'cancellation_fee' => 150.00,
        ]);
    }

    public function test_settings_validation_errors(): void
    {
        $this->actingAs($this->admin);

        // Scenario A: percentage > 100
        Volt::test('turf.settings-manager')
            ->set('is_part_payment_active', true)
            ->set('part_payment_type', 'percentage')
            ->set('part_payment_value', 120) // invalid
            ->call('save')
            ->assertHasErrors(['part_payment_value']);

        // Scenario B: invalid days window selection
        Volt::test('turf.settings-manager')
            ->set('booking_open_days', 45) // invalid options (must be 30, 60, 90)
            ->call('save')
            ->assertHasErrors(['booking_open_days']);
    }
}
