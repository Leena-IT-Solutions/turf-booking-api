<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\Location;
use App\Models\SaasSetting;
use App\Models\Slot;
use App\Models\Turf;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtGuardrailTest extends TestCase
{
    use RefreshDatabase;

    protected User $turfAdmin;
    protected Turf $turf;
    protected Slot $slot;
    protected BookingDate $bookingDate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        SaasSetting::create([
            'max_commission_due' => 2000.00,
            'commission_due_grace_days' => 7,
        ]);

        $this->turfAdmin = User::factory()->create([
            'commission_wallet_balance' => -2500.00, // Exceeds max due limit of 2000
            'commission_due_since' => now()->subDays(2),
        ]);
        $this->turfAdmin->assignRole('turf-admin');

        $location = Location::create([
            'user_id' => $this->turfAdmin->id,
            'name' => 'Lock Arena',
            'address' => '789 Eastern Express, Thane',
            'city' => 'Thane',
        ]);


        $this->turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Lock Turf',
            'type' => 'Football',
        ]);


        $category = \App\Models\SlotCategory::create(['name' => 'Regular']);

        $this->slot = Slot::create([
            'turf_id' => $this->turf->id,
            'slot_category_id' => $category->id,
            'name' => '8-9 PM Slot',
            'from_time' => '20:00:00',
            'to_time' => '21:00:00',
            'duration' => 60,
        ]);

        $booking = Booking::create([
            'user_id' => $this->turfAdmin->id,
            'turf_id' => $this->turf->id,
            'date_of_booking' => now()->format('Y-m-d'),
            'booking_type' => 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Part',
        ]);


        $this->bookingDate = BookingDate::create([
            'booking_id' => $booking->id,
            'booking_date' => now()->format('Y-m-d'),
            'amount' => 1000.00,
            'status' => 'Confirmed',
        ]);
    }

    public function test_offline_record_payment_blocked_when_debt_limit_exceeded()
    {
        $response = $this->actingAs($this->turfAdmin, 'sanctum')->postJson("/api/booking-dates/{$this->bookingDate->id}/payments", [
            'payment_method' => 'Cash',
            'amount' => 500.00,
        ]);


        $response->assertStatus(422)
            ->assertJsonPath('message', 'Offline booking locked! Commission due of ₹2,500.00 exceeds limit or grace period. Please settle your due balance from the Business page to record more offline payments.');
    }

    public function test_offline_booking_store_endpoint_blocked_when_debt_limit_exceeded()
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($this->turfAdmin, 'sanctum')->postJson("/api/turfs/{$this->turf->id}/bookings", [
            'slot_ids' => [$this->slot->id],
            'booking_dates' => [now()->format('Y-m-d')],
            'booking_type' => 'day',
            'payment_method' => 'Cash',
            'amount_received' => 1000.00,
        ]);

        $response->assertStatus(422);
    }
}
