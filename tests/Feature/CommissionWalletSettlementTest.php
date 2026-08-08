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
            'payment_gateway_percentage' => 2.00,
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
        $this->assertEquals(35.00, (float)$offlinePayment->commission_amount); // 5% of 700 (7% - 2% gateway)
        $this->assertEquals(0.00, (float)$offlinePayment->cash_held_amount);
        $this->assertEquals(-35.00, (float)$offlinePayment->turf_payout_amount);

        // Net Wallet Balance = +279.00 (online) - 35.00 (offline) = 244.00
        $this->turfAdmin->refresh();
        $this->assertEquals(244.00, (float)$this->turfAdmin->commission_wallet_balance);
    }
}
