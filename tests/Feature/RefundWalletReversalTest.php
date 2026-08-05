<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\Location;
use App\Models\Payment;
use App\Models\SaasSetting;
use App\Models\Turf;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundWalletReversalTest extends TestCase
{
    use RefreshDatabase;

    protected User $turfAdmin;
    protected Turf $turf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        SaasSetting::create([
            'commission_percentage' => 7.00,
            'payment_gateway_percentage' => 2.00,
        ]);

        $this->turfAdmin = User::factory()->create([
            'commission_wallet_balance' => 930.00,
        ]);
        $this->turfAdmin->assignRole('turf-admin');

        $location = Location::create([
            'user_id' => $this->turfAdmin->id,
            'name' => 'Refund Arena',
            'address' => '101 Marine Drive, Mumbai',
            'city' => 'Mumbai',
        ]);


        $this->turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Refund Turf',
            'type' => 'Football',
            'is_cancellation_active' => true,
            'cancellation_hours' => 24,
            'cancellation_fee' => 0.00,
        ]);


    }

    public function test_cancelling_matured_booking_reverses_cleared_wallet_contribution()
    {
        $bookingDateStr = now()->addDays(2)->format('Y-m-d');

        $booking = Booking::create([
            'user_id' => $this->turfAdmin->id,
            'turf_id' => $this->turf->id,
            'date_of_booking' => $bookingDateStr,
            'booking_type' => 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
        ]);


        $bDate = BookingDate::create([
            'booking_id' => $booking->id,
            'booking_date' => $bookingDateStr,
            'amount' => 1000.00,
            'status' => 'Confirmed',
        ]);

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $bDate->id,
            'payment_method' => 'App',
            'amount' => 1000.00,
            'commission_percentage' => 7.00,
            'commission_amount' => 70.00,
            'cash_held_amount' => 1000.00,
            'turf_payout_amount' => 930.00,
            'status' => 'Success',
            'wallet_cleared_at' => now(), // Cleared into wallet
        ]);

        // Full cancellation refund
        $response = $this->actingAs($this->turfAdmin, 'sanctum')->postJson("/api/bookings/{$booking->id}/cancel", [
            'reason' => 'Customer requested cancellation',
        ]);

        $response->assertStatus(200);

        // Wallet balance reversed: 930.00 (initial) - 930.00 (reversal) = 0.00
        $this->turfAdmin->refresh();
        $this->assertEquals(0.00, (float)$this->turfAdmin->commission_wallet_balance);
    }
}
