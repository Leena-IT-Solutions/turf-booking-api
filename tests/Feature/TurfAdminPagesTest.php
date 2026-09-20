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

    public function test_pay_at_venue_collection_via_web_dashboard_does_not_recharge_commission(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-12 10:00:00'));

        \App\Models\SaasSetting::create([
            'commission_percentage' => 8.00,
            'platform_fee' => 2.00,
            'platform_fee_type' => 'fixed',
            'platform_fee_gst_percentage' => 0.00,
            'min_slots_booking' => 2,
        ]);

        $turfAdmin = User::factory()->create();
        $turfAdmin->assignRole('turf-admin');

        $location = \App\Models\Location::create([
            'user_id' => $turfAdmin->id,
            'name' => 'Split Payment Arena',
            'address' => 'Mumbai',
        ]);
        $turf = \App\Models\Turf::create([
            'location_id' => $location->id,
            'name' => 'Split Payment Turf',
            'type' => 'Football',
            'is_active' => true,
            'is_part_payment_active' => true,
            'part_payment_type' => 'percentage',
            'part_payment_value' => 50,
        ]);

        $category = \App\Models\SlotCategory::create(['name' => 'Cheap']);
        $slotA = \App\Models\Slot::create(['slot_category_id' => $category->id, 'name' => '6-7 AM', 'from_time' => '06:00:00', 'to_time' => '07:00:00', 'duration' => 60]);
        $slotB = \App\Models\Slot::create(['slot_category_id' => $category->id, 'name' => '7-8 AM', 'from_time' => '07:00:00', 'to_time' => '08:00:00', 'duration' => 60]);
        foreach ([$slotA, $slotB] as $slot) {
            $turf->slots()->attach($slot->id, [
                'is_active' => true,
                'mon' => 5.00, 'tue' => 5.00, 'wed' => 5.00,
                'thu' => 5.00, 'fri' => 5.00, 'sat' => 5.00, 'sun' => 5.00,
            ]);
        }

        $customer = User::factory()->create();
        $bookingDate = now()->addDay()->format('Y-m-d');

        // Base = 2 slots x Rs 5 = Rs 10 (taxable). + Rs 2 platform fee = Rs 12 total. 50% part payment = Rs 6 online.
        $response = $this->actingAs($customer, 'sanctum')->postJson("/api/turfs/{$turf->id}/bookings", [
            'slot_ids' => [$slotA->id, $slotB->id],
            'booking_dates' => [$bookingDate],
            'booking_type' => 'day',
            'payment_method' => 'App',
            'payment_option' => 'part',
            'amount_received' => 6.00,
        ]);
        $response->assertStatus(200);
        $bookingId = $response->json('booking.id');
        $bDate = \App\Models\BookingDate::where('booking_id', $bookingId)->first();

        // Online part payment already carries the full Rs 0.80 commission (8% of the Rs 10 taxable base).
        $this->assertEquals(0.80, (float) \App\Models\Payment::where('booking_date_id', $bDate->id)->sum('commission_amount'));

        // Turf staff collects the remaining Rs 6 at the venue via the web dashboard's "Record Payment" action.
        $this->actingAs($turfAdmin);
        Volt::test('turf.booking-manager')
            ->call('openPaymentModal', $bDate->id)
            ->set('paymentMethod', 'Cash')
            ->call('submitPayment');

        $payments = \App\Models\Payment::where('booking_date_id', $bDate->id)->orderBy('id')->get();
        $this->assertCount(2, $payments);
        $this->assertEquals(0.80, (float)$payments[0]->commission_amount);
        $this->assertEquals(
            0.00,
            (float)$payments[1]->commission_amount,
            'Pay at Venue collection must not re-charge commission already taken on the online part payment.'
        );

        \Carbon\Carbon::setTestNow();
    }
}
