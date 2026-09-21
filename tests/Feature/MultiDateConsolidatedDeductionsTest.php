<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\CommissionWalletTransaction;
use App\Models\Location;
use App\Models\Payment;
use App\Models\SaasSetting;
use App\Models\Slot;
use App\Models\SlotCategory;
use App\Models\Turf;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiDateConsolidatedDeductionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $turfAdmin;
    protected User $customer;
    protected Turf $turf;
    protected Slot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        SaasSetting::create([
            'platform_fee' => 2.00,
            'platform_fee_gst_percentage' => 0.00,
            'commission_percentage' => 6.18, // 1.36 on 22
            'min_slots_booking' => 1,
        ]);

        $this->turfAdmin = User::factory()->create([
            'commission_wallet_balance' => 0.00,
        ]);
        $this->turfAdmin->assignRole('turf-admin');

        $this->customer = User::factory()->create();

        $location = Location::create([
            'user_id' => $this->turfAdmin->id,
            'name' => 'Pro Arena',
            'address' => 'Andheri West, Mumbai',
            'city' => 'Mumbai',
        ]);

        $this->turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Green Turf',
            'type' => 'Football',
            'is_active' => true,
            'is_part_payment_active' => true,
            'part_payment_type' => 'percentage',
            'part_payment_value' => 50,
        ]);

        $category = SlotCategory::create(['name' => 'General']);

        $this->slot = Slot::create([
            'slot_category_id' => $category->id,
            'name' => '5-6 PM Slot',
            'from_time' => '17:00:00',
            'to_time' => '18:00:00',
            'duration' => 60,
        ]);

        // Default pricing: 11 for Mon-Sun
        $this->turf->slots()->attach($this->slot->id, [
            'is_active' => true,
            'mon' => 11.00, 'tue' => 11.00, 'wed' => 11.00,
            'thu' => 11.00, 'fri' => 11.00, 'sat' => 11.00, 'sun' => 11.00,
        ]);
    }

    public function test_multi_date_booking_posts_commission_and_pg_charges_once()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        $date1 = '2026-10-02';
        $date2 = '2026-10-03';

        // 2 dates @ 11 each = 22 + 2 platform fee = 24 total
        $response = $this->actingAs($this->customer, 'sanctum')->postJson("/api/turfs/{$this->turf->id}/bookings", [
            'slot_ids' => [$this->slot->id],
            'booking_dates' => [$date1, $date2],
            'booking_type' => 'long',
            'payment_method' => 'App',
            'payment_option' => 'full',
            'amount_received' => 24.00,
            'razorpay_payment_id' => 'pay_test_12345',
        ]);

        $response->assertStatus(200);
        $bookingId = $response->json('booking.id');
        $booking = Booking::find($bookingId);

        // Assert deductions in wallet transactions
        $txs = CommissionWalletTransaction::where('user_id', $this->turfAdmin->id)->get();

        // Exactly ONE platform fee debit
        $feeTxs = $txs->where('type', 'platform_fee_debit');
        $this->assertCount(1, $feeTxs);
        $this->assertEquals(-2.00, (float)$feeTxs->first()->amount);

        // Exactly ONE commission debit (booking-wide total)
        $commTxs = $txs->where('type', 'commission_debit');
        $this->assertCount(1, $commTxs);
        $this->assertEquals(-(float)$booking->commission_amount, (float)$commTxs->first()->amount);

        // Exactly ONE gateway charge debit (if PG charge was calculated)
        $pgTxs = $txs->where('type', 'gateway_charge_debit');
        $this->assertLessThanOrEqual(1, $pgTxs->count());

        // Fast forward to mature both dates
        Carbon::setTestNow(Carbon::parse('2026-10-04 10:00:00'));
        $this->artisan('wallet:clear-matured-entries');

        $clearedTxs = CommissionWalletTransaction::where('user_id', $this->turfAdmin->id)->get();
        $creditTxs = $clearedTxs->where('type', 'payment_credit');

        // Both dates credited proportionally (each date paid 12.00 of the 24.00 total)
        $this->assertCount(2, $creditTxs);
        foreach ($creditTxs as $ctx) {
            $this->assertEquals(12.00, (float)$ctx->amount);
        }

        // Each payment row stores its net payout entitlement
        $payments = Payment::where('booking_id', $bookingId)->orderBy('id')->get();
        $this->assertCount(2, $payments);
        $this->assertEquals($payments[0]->turf_payout_amount, $payments[1]->turf_payout_amount);

        // Final wallet balance equals total paid (24) minus total deductions
        $totalDeductions = (float)$clearedTxs->whereIn('type', ['platform_fee_debit', 'commission_debit', 'gateway_charge_debit'])->sum('amount');
        $this->turfAdmin->refresh();
        $expectedBalance = round(24.00 + $totalDeductions, 2);
        $this->assertEquals($expectedBalance, (float)$this->turfAdmin->commission_wallet_balance);

        Carbon::setTestNow();
    }

    public function test_unequally_priced_dates_split_payout_proportionally()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        // Update slot pricing on pivot for Friday (500) and Saturday (300)
        $this->turf->slots()->updateExistingPivot($this->slot->id, [
            'fri' => 500.00,
            'sat' => 300.00,
        ]);

        SaasSetting::first()->update([
            'platform_fee' => 10.00,
            'commission_percentage' => 10.00,
        ]);

        // 2026-10-02 is Friday (500), 2026-10-03 is Saturday (300).
        // Total slots = 800 + 10 fee = 810.00
        $date1 = '2026-10-02';
        $date2 = '2026-10-03';

        $response = $this->actingAs($this->customer, 'sanctum')->postJson("/api/turfs/{$this->turf->id}/bookings", [
            'slot_ids' => [$this->slot->id],
            'booking_dates' => [$date1, $date2],
            'booking_type' => 'long',
            'payment_method' => 'App',
            'payment_option' => 'full',
            'amount_received' => 810.00,
        ]);

        $response->assertStatus(200);
        $bookingId = $response->json('booking.id');

        // Mature both dates
        Carbon::setTestNow(Carbon::parse('2026-10-04 10:00:00'));
        $this->artisan('wallet:clear-matured-entries');

        $bDates = BookingDate::where('booking_id', $bookingId)->orderBy('id')->get();
        $this->assertEquals(506.25, (float)$bDates[0]->amount); // 500 + 6.25 allocated fee
        $this->assertEquals(303.75, (float)$bDates[1]->amount); // 300 + 3.75 allocated fee

        $payments = Payment::where('booking_id', $bookingId)->orderBy('id')->get();

        // Proportional net payout:
        // Total slots = 800, Commission = 80, Platform fee = 10 -> Net payout = 800 - 80 = 720.00
        // Friday share (500/800 = 62.5%): 720 * 0.625 = 450.00
        // Saturday share (300/800 = 37.5%): 720 * 0.375 = 270.00
        $this->assertEquals(450.00, (float)$payments[0]->turf_payout_amount);
        $this->assertEquals(270.00, (float)$payments[1]->turf_payout_amount);

        // Total wallet balance after both clear: 720.00
        $this->turfAdmin->refresh();
        $this->assertEquals(720.00, (float)$this->turfAdmin->commission_wallet_balance);

        Carbon::setTestNow();
    }

    public function test_part_payment_incremental_release_idempotency()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        SaasSetting::first()->update([
            'platform_fee' => 10.00,
            'commission_percentage' => 10.00,
        ]);

        $this->turf->slots()->updateExistingPivot($this->slot->id, [
            'fri' => 1000.00,
        ]);

        // Customer pays 50% part payment for 1000 + 10 fee = 1010 total -> pays 505.00
        $futureDate = '2026-10-02';
        $response = $this->actingAs($this->customer, 'sanctum')->postJson("/api/turfs/{$this->turf->id}/bookings", [
            'slot_ids' => [$this->slot->id],
            'booking_dates' => [$futureDate],
            'booking_type' => 'day',
            'payment_method' => 'App',
            'payment_option' => 'part',
            'amount_received' => 505.00,
        ]);

        $response->assertStatus(200);
        $bookingId = $response->json('booking.id');
        $booking = Booking::find($bookingId);
        $bookingDate = $booking->bookingDates()->first();

        // Advance to maturity and clear first tranche
        Carbon::setTestNow(Carbon::parse('2026-10-03 10:00:00'));
        $this->artisan('wallet:clear-matured-entries');

        // Customer pays remaining 505 online
        $distributor = new \App\Services\BookingPaymentDistributor();
        $distributor->distribute($booking, 505.00, 'App', 'pay_second_123');

        // Clear again
        $this->artisan('wallet:clear-matured-entries');

        // Total deductions: platform fee (10.00) + commission (100.00) = 110.00
        // Total gross credited across both tranches: 505.00 + 505.00 = 1010.00
        // Final wallet balance = 1010.00 - 110.00 = 900.00
        $this->turfAdmin->refresh();
        $this->assertEquals(900.00, (float)$this->turfAdmin->commission_wallet_balance);

        Carbon::setTestNow();
    }
}
