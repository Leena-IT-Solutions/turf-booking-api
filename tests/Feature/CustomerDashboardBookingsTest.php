<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\Location;
use App\Models\Turf;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CustomerDashboardBookingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_dashboard_renders_empty_state_when_no_bookings_exist(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('You dont have booking yet!');
    }

    public function test_customer_bookings_livewire_component_shows_empty_message(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('dashboard.customer-bookings')
            ->assertSee('You dont have booking yet!');
    }

    public function test_customer_can_see_their_bookings_on_dashboard(): void
    {
        $user = User::factory()->create();
        $location = Location::create([
            'name' => 'Mumbai Central',
            'user_id' => $user->id,
            'address' => 'Mumbai'
        ]);

        $turf = Turf::create([
            'user_id' => $user->id,
            'name' => 'Royal Cricket Arena',
            'location_id' => $location->id,
            'address' => 'Marine Drive, Mumbai',
            'type' => 'Cricket',
        ]);

        $booking = Booking::create([
            'user_id' => $user->id,
            'turf_id' => $turf->id,
            'date_of_booking' => Carbon::now(),
            'booking_type' => 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
        ]);

        BookingDate::create([
            'booking_id' => $booking->id,
            'booking_date' => Carbon::today('Asia/Kolkata')->toDateString(),
            'amount' => 1500.00,
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
        ]);

        Livewire::actingAs($user)
            ->test('dashboard.customer-bookings')
            ->assertSee('Royal Cricket Arena')
            ->assertSee('Confirmed')
            ->assertSee('Paid')
            ->assertDontSee('You dont have booking yet!');
    }

    public function test_search_and_status_filtering_works_correctly(): void
    {
        $user = User::factory()->create();
        $location = Location::create([
            'name' => 'Andheri East',
            'user_id' => $user->id,
            'address' => 'Andheri'
        ]);

        $turf1 = Turf::create([
            'user_id' => $user->id,
            'name' => 'Champions Arena',
            'location_id' => $location->id,
            'address' => 'Andheri West',
            'type' => 'Football',
        ]);

        $turf2 = Turf::create([
            'user_id' => $user->id,
            'name' => 'Premier Sports Park',
            'location_id' => $location->id,
            'address' => 'Juhu',
            'type' => 'Cricket',
        ]);

        $booking1 = Booking::create([
            'user_id' => $user->id,
            'turf_id' => $turf1->id,
            'date_of_booking' => Carbon::now(),
            'booking_type' => 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
        ]);

        BookingDate::create([
            'booking_id' => $booking1->id,
            'booking_date' => Carbon::today('Asia/Kolkata')->toDateString(),
            'amount' => 1200.00,
            'status' => 'Confirmed',
        ]);

        $booking2 = Booking::create([
            'user_id' => $user->id,
            'turf_id' => $turf2->id,
            'date_of_booking' => Carbon::now(),
            'booking_type' => 'day',
            'status' => 'Cancelled',
            'payment_status' => 'Unpaid',
        ]);

        BookingDate::create([
            'booking_id' => $booking2->id,
            'booking_date' => Carbon::today('Asia/Kolkata')->addDays(3)->toDateString(),
            'amount' => 1800.00,
            'status' => 'Cancelled',
        ]);

        // Filter by Search
        Livewire::actingAs($user)
            ->test('dashboard.customer-bookings')
            ->set('search', 'Champions')
            ->assertSee('Champions Arena')
            ->assertDontSee('Premier Sports Park');

        // Filter by Status: Confirmed
        Livewire::actingAs($user)
            ->test('dashboard.customer-bookings')
            ->set('statusFilter', 'Confirmed')
            ->assertSee('Champions Arena')
            ->assertDontSee('Premier Sports Park');

        // Filter by Status: Cancelled
        Livewire::actingAs($user)
            ->test('dashboard.customer-bookings')
            ->set('statusFilter', 'Cancelled')
            ->assertSee('Premier Sports Park')
            ->assertDontSee('Champions Arena');

        // Filter with non-matching search displays empty message
        Livewire::actingAs($user)
            ->test('dashboard.customer-bookings')
            ->set('search', 'NonExistentTurfName1234')
            ->assertSee('You dont have booking yet!');
    }
}
