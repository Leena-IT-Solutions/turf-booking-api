<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Coupon;
use Livewire\Volt\Volt;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurfAdminPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_is_redirected_from_turf_pages(): void
    {
        $this->get('/turf/dashboard')->assertRedirect('/login');
        $this->get('/turf/bookings')->assertRedirect('/login');
        $this->get('/turf/settings')->assertRedirect('/login');
        $this->get('/turf/offers')->assertRedirect('/login');
    }

    public function test_non_turf_admin_cannot_access_turf_pages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $this->actingAs($user)->get('/turf/dashboard')->assertStatus(403);
        $this->actingAs($user)->get('/turf/bookings')->assertStatus(403);
        $this->actingAs($user)->get('/turf/settings')->assertStatus(403);
        $this->actingAs($user)->get('/turf/offers')->assertStatus(403);
    }

    public function test_turf_admin_can_access_turf_pages(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('turf-admin');

        $this->actingAs($admin)->get('/turf/dashboard')->assertOk();
        $this->actingAs($admin)->get('/turf/bookings')->assertOk();
        $this->actingAs($admin)->get('/turf/settings')->assertOk();
        $this->actingAs($admin)->get('/turf/offers')->assertOk();
    }

    public function test_manager_can_access_turf_pages(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $this->actingAs($manager)->get('/turf/dashboard')->assertOk();
        $this->actingAs($manager)->get('/turf/bookings')->assertOk();
        $this->actingAs($manager)->get('/turf/offers')->assertOk();
        $this->actingAs($manager)->get('/turf/settings')->assertOk();
    }

    public function test_turf_admin_dashboard_shows_statistics(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('turf-admin');
        $this->actingAs($admin);

        // Seed some locations & coupons to check counts on the dashboard view
        $location = \App\Models\Location::create([
            'user_id' => $admin->id,
            'name' => 'Mumbai Sports Complex',
            'address' => 'Andheri West',
            'description' => 'Great turf place',
            'latitude' => '19.1136',
            'longitude' => '72.8697',
        ]);

        $turf = \App\Models\Turf::create([
            'location_id' => $location->id,
            'name' => 'Football Turf A',
            'type' => 'Synthetic',
            'width' => 20,
            'length' => 40,
        ]);

        Coupon::create([
            'code' => 'DASHBOARD50',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        Volt::test('turf.dashboard-manager')
            ->assertSee('Mumbai Sports Complex')
            ->assertSee('Football Turf A')
            ->assertSee('Active Coupons');
    }
}
