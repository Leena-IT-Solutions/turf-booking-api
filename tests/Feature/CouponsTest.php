<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Location;
use App\Models\Turf;
use App\Models\Coupon;
use Livewire\Volt\Volt;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_is_redirected_from_coupons_page(): void
    {
        $this->get('/turf/coupons')->assertRedirect('/login');
    }

    public function test_non_turf_admin_cannot_access_coupons_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $this->actingAs($user)->get('/turf/coupons')->assertStatus(403);
    }

    public function test_turf_admin_can_access_coupons_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('turf-admin');

        $this->actingAs($admin)->get('/turf/coupons')->assertOk();
    }

    public function test_coupons_manager_volt_component_can_create_coupon_with_nullable_values(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('turf-admin');
        $this->actingAs($admin);

        $location = Location::create([
            'user_id' => $admin->id,
            'name' => 'Mumbai Sports Complex',
            'address' => 'Andheri West',
            'latitude' => '19.1136',
            'longitude' => '72.8697',
        ]);

        $turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Football Turf A',
            'type' => 'Synthetic',
            'width' => 20,
            'length' => 40,
        ]);

        session(['active_turf_id' => $turf->id]);

        Volt::test('turf.coupons-manager')
            ->set('code', 'OPEN50')
            ->set('discount_type', 'fixed')
            ->set('discount_value', 50)
            ->set('max_discount_amount', '') // Empty string to simulate user input
            ->set('minimum_slots_to_be_ordered', 2)
            ->set('usage_limit', '') // Empty string
            ->set('usage_limit_per_user', 100)
            ->set('starts_at', '') // Empty string
            ->set('expires_at', '') // Empty string
            ->set('description', '') // Empty string
            ->call('saveCoupon')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('coupons', [
            'turf_id' => $turf->id,
            'code' => 'OPEN50',
            'discount_type' => 'fixed',
            'discount_value' => 50.00,
            'max_discount_amount' => null,
            'minimum_slots_to_be_ordered' => 2,
            'usage_limit' => null,
            'usage_limit_per_user' => 100,
            'starts_at' => null,
            'expires_at' => null,
            'description' => null,
        ]);
    }
}
