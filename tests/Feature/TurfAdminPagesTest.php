<?php

namespace Tests\Feature;

use App\Models\User;
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
    }

    public function test_non_turf_admin_cannot_access_turf_pages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $this->actingAs($user)->get('/turf/dashboard')->assertStatus(403);
        $this->actingAs($user)->get('/turf/bookings')->assertStatus(403);
        $this->actingAs($user)->get('/turf/settings')->assertStatus(403);
    }

    public function test_turf_admin_can_access_turf_pages(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('turf-admin');

        $this->actingAs($admin)->get('/turf/dashboard')->assertOk();
        $this->actingAs($admin)->get('/turf/bookings')->assertOk();
        $this->actingAs($admin)->get('/turf/settings')->assertOk();
    }

    public function test_manager_can_access_turf_pages(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $this->actingAs($manager)->get('/turf/dashboard')->assertOk();
        $this->actingAs($manager)->get('/turf/bookings')->assertOk();
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

        Volt::test('turf.dashboard-manager')
            ->assertSee('Mumbai Sports Complex')
            ->assertSee('Football Turf A');
    }

    public function test_turf_admin_with_no_turfs_sees_no_bookings(): void
    {
        // Another owner with turfs and bookings
        $otherOwner = User::factory()->create();
        $otherOwner->assignRole('turf-admin');
        $otherLoc = \App\Models\Location::create([
            'user_id' => $otherOwner->id,
            'name' => 'Other Complex',
            'address' => 'Pune',
        ]);
        $otherTurf = \App\Models\Turf::create([
            'location_id' => $otherLoc->id,
            'name' => 'Other Football Ground',
            'type' => 'Grass',
        ]);
        $customer = User::factory()->create();
        $booking = \App\Models\Booking::create([
            'user_id' => $customer->id,
            'turf_id' => $otherTurf->id,
            'date_of_booking' => now(),
            'booking_type' => 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
        ]);
        \App\Models\BookingDate::create([
            'booking_id' => $booking->id,
            'booking_date' => now()->toDateString(),
            'amount' => 1000.00,
            'status' => 'Confirmed',
        ]);

        // Admin who has NO turfs
        $newAdmin = User::factory()->create();
        $newAdmin->assignRole('turf-admin');

        $this->actingAs($newAdmin);

        Volt::test('turf.booking-manager')
            ->assertDontSee('Other Football Ground')
            ->assertDontSee($customer->name)
            ->assertSee("You don't have any turfs or bookings yet.", false);
    }
}
