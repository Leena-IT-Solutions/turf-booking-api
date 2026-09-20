<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\Location;
use App\Models\Payment;
use App\Models\SaasSetting;
use App\Models\Slot;
use App\Models\Turf;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionWalletSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected User $turfAdmin;
    protected Turf $turf;
    protected Slot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        SaasSetting::create([
            'commission_percentage' => 7.00,
            'min_slots_booking' => 1,
        ]);



        $this->turfAdmin = User::factory()->create([
            'commission_wallet_balance' => 0.00,
        ]);
        $this->turfAdmin->assignRole('turf-admin');

        $location = Location::create([
            'user_id' => $this->turfAdmin->id,
            'name' => 'City Arena Location',
            'address' => '123 Main St, Mumbai',
            'city' => 'Mumbai',
        ]);


        $this->turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Main Football Turf',
            'type' => 'Football',
            'is_active' => true,
            'is_part_payment_active' => true,
            'part_payment_type' => 'percentage',
            'part_payment_value' => 30,
        ]);




        $category = \App\Models\SlotCategory::create(['name' => 'Regular']);

        $this->slot = Slot::create([
            'slot_category_id' => $category->id,
            'name' => '11-12 PM Slot',
            'from_time' => '23:00:00',
            'to_time' => '23:59:00',
            'duration' => 60,
        ]);

        $this->turf->slots()->attach($this->slot->id, [
            'is_active' => true,
            'mon' => 1000.00, 'tue' => 1000.00, 'wed' => 1000.00,
            'thu' => 1000.00, 'fri' => 1000.00, 'sat' => 1000.00, 'sun' => 1000.00,
        ]);
    }


    public function test_worked_example_end_to_end_wallet_math()
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-12 10:00:00'));
        $customer = User::factory()->create();

        // 1. Customer creates ₹1000 booking with ₹300 online payment
        $bookingDate = now()->format('Y-m-d');
        $response = $this->actingAs($customer, 'sanctum')->postJson("/api/turfs/{$this->turf->id}/bookings", [
            'slot_ids' => [$this->slot->id],
            'booking_dates' => [$bookingDate],
            'booking_type' => 'day',
            'payment_method' => 'App',
            'payment_option' => 'part',
            'amount_received' => 300.00,
        ]);


        $response->assertStatus(200);


        $bookingId = $response->json('booking.id');


        $onlinePayment = Payment::where('booking_id', $bookingId)->first();
        $this->assertEquals(300.00, (float)$onlinePayment->amount);
        $this->assertEquals(21.00, (float)$onlinePayment->commission_amount); // 7% of 300
        $this->assertEquals(300.00, (float)$onlinePayment->cash_held_amount);
        $this->assertEquals(279.00, (float)$onlinePayment->turf_payout_amount); // 300 - 21

        // 2. Manager records remaining ₹700 offline Cash payment
        $bookingDate = BookingDate::where('booking_id', $bookingId)->first();

        $recordResponse = $this->actingAs($this->turfAdmin, 'sanctum')->postJson("/api/booking-dates/{$bookingDate->id}/payments", [
            'payment_method' => 'Cash',
            'amount' => 700.00,
        ]);


        $recordResponse->assertStatus(200);

        $offlinePayment = Payment::where('booking_id', $bookingId)->where('payment_method', 'Cash')->first();
        $this->assertEquals(700.00, (float)$offlinePayment->amount);
        $this->assertEquals(49.00, (float)$offlinePayment->commission_amount); // 7% of 700
        $this->assertEquals(0.00, (float)$offlinePayment->cash_held_amount);
        $this->assertEquals(-49.00, (float)$offlinePayment->turf_payout_amount);

        // Net Wallet Balance = +279.00 (online) - 49.00 (offline) = 230.00
        $this->turfAdmin->refresh();
        $this->assertEquals(230.00, (float)$this->turfAdmin->commission_wallet_balance);

        \Carbon\Carbon::setTestNow();
    }

    public function test_pay_at_location_offline_booking_debits_platform_fee_and_commission_from_wallet()
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-12 10:00:00'));

        SaasSetting::first()->update([
            'platform_fee' => 2.00,
            'platform_fee_type' => 'fixed',
            'platform_fee_gst_percentage' => 0.00,
        ]);

        $this->turf->update([
            'is_pay_at_location_active' => true,
        ]);

        $customer = User::factory()->create();
        $bookingDate = now()->format('Y-m-d');

        // Customer chooses "Pay at Location" (offline)
        $response = $this->actingAs($customer, 'sanctum')->postJson("/api/turfs/{$this->turf->id}/bookings", [
            'slot_ids' => [$this->slot->id],
            'booking_dates' => [$bookingDate],
            'booking_type' => 'day',
            'payment_method' => 'offline',
            'payment_option' => 'full',
        ]);

        $response->assertStatus(200);
        $bookingId = $response->json('booking.id');

        $booking = Booking::with(['bookingDates', 'payments'])->find($bookingId);
        $this->assertEquals('Paid', $booking->payment_status);
        $this->assertEquals(0.00, (float)$booking->balance_amount);
        $this->assertCount(1, $booking->payments);

        $payment = $booking->payments->first();
        $this->assertEquals('offline', $payment->payment_method);
        // Base is 1000. 7% commission is 70. Platform fee is 2. Total SaaS Cut = 72.
        $this->assertEquals(70.00, (float)$payment->commission_amount);
        $this->assertEquals(-72.00, (float)$payment->turf_payout_amount);

        // Turf owner wallet balance should be debited -72.00
        $this->turfAdmin->refresh();
        $this->assertEquals(-72.00, (float)$this->turfAdmin->commission_wallet_balance);

        // CommissionWalletTransaction should be recorded as commission_debit
        $tx = \App\Models\CommissionWalletTransaction::where('user_id', $this->turfAdmin->id)->first();
        $this->assertNotNull($tx);
        $this->assertEquals('commission_debit', $tx->type);
        $this->assertEquals(-72.00, (float)$tx->amount);

        \Carbon\Carbon::setTestNow();
    }
}

