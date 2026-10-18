<?php

namespace Tests\Feature;

use App\Console\Commands\ClearMaturedWalletEntries;
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
use App\Services\BookingCancellationService;
use App\Services\WalletService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesRazorpay;
use Tests\TestCase;

class ImmediateDeductionsDeferredCreditTest extends TestCase
{
    use RefreshDatabase, FakesRazorpay;

    protected User $turfAdmin;
    protected User $customer;
    protected Turf $turf;
    protected Slot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        SaasSetting::create([
            'platform_fee' => 10.00,
            'platform_fee_gst_percentage' => 18.00,
            'commission_percentage' => 10.00,
            'min_slots_booking' => 1,
        ]);

        $this->turfAdmin = User::factory()->create([
            'commission_wallet_balance' => 0.00,
        ]);
        $this->turfAdmin->assignRole('turf-admin');

        $this->customer = User::factory()->create();

        $location = Location::create([
            'user_id' => $this->turfAdmin->id,
            'name' => 'Future Arena',
            'address' => '456 Sports Way',
            'city' => 'Mumbai',
        ]);

        $this->turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Championship Turf',
            'type' => 'Football',
            'is_active' => true,
            'is_part_payment_active' => true,
            'part_payment_type' => 'percentage',
            'part_payment_value' => 50,
            'is_cancellation_active' => true,
            'cancellation_hours' => 1,
            'cancellation_fee' => 0.00,
        ]);

        $category = SlotCategory::create(['name' => 'Evening']);

        $this->slot = Slot::create([
            'slot_category_id' => $category->id,
            'name' => '6-7 PM Slot',
            'from_time' => '18:00:00',
            'to_time' => '19:00:00',
            'duration' => 60,
        ]);

        $this->turf->slots()->attach($this->slot->id, [
            'is_active' => true,
            'mon' => 1000.00, 'tue' => 1000.00, 'wed' => 1000.00,
            'thu' => 1000.00, 'fri' => 1000.00, 'sat' => 1000.00, 'sun' => 1000.00,
        ]);
    }

    public function test_future_online_booking_posts_deductions_immediately_while_credit_remains_pending()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        $futureDate = '2026-10-05'; // 4 days in future
        $response = $this->actingAs($this->customer, 'sanctum')->postJson("/api/turfs/{$this->turf->id}/bookings", [
            'slot_ids' => [$this->slot->id],
            'booking_dates' => [$futureDate],
            'booking_type' => 'day',
            'payment_method' => 'App',
            'payment_option' => 'full',
            ...$this->razorpayPayment(1010.00),
        ]);

        $response->assertStatus(200);
        $bookingId = $response->json('booking.id');

        $payment = Payment::where('booking_id', $bookingId)->first();
        $this->assertNotNull($payment);
        $this->assertNotNull($payment->deductions_settled_at);
        $this->assertNull($payment->wallet_cleared_at);

        // Platform fee: 10.00, Commission: 10% of 1000 = 100.00
        $txs = CommissionWalletTransaction::where('user_id', $this->turfAdmin->id)->get();
        $this->assertTrue($txs->contains('type', 'platform_fee_debit'));
        $this->assertTrue($txs->contains('type', 'commission_debit'));
        $this->assertFalse($txs->contains('type', 'payment_credit'));

        // Turf admin wallet balance should immediately reflect negative deductions
        $this->turfAdmin->refresh();
        $expectedNegative = -110.00; // -(10.00 platform fee + 100.00 commission)
        $this->assertEquals($expectedNegative, (float)$this->turfAdmin->commission_wallet_balance);

        Carbon::setTestNow();
    }

    public function test_deferred_credit_posts_when_matured_and_is_idempotent()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        $futureDate = '2026-10-05';
        $response = $this->actingAs($this->customer, 'sanctum')->postJson("/api/turfs/{$this->turf->id}/bookings", [
            'slot_ids' => [$this->slot->id],
            'booking_dates' => [$futureDate],
            'booking_type' => 'day',
            'payment_method' => 'App',
            'payment_option' => 'full',
            ...$this->razorpayPayment(1010.00),
        ]);

        $response->assertStatus(200);
        $bookingId = $response->json('booking.id');
        $payment = Payment::where('booking_id', $bookingId)->first();

        // 1. Run clear-matured command before maturity date -> nothing cleared
        $this->artisan('wallet:clear-matured-entries')->assertExitCode(0);
        $payment->refresh();
        $this->assertNull($payment->wallet_cleared_at);

        // 2. Advance time to maturity date
        Carbon::setTestNow(Carbon::parse('2026-10-05 08:00:00'));
        $this->artisan('wallet:clear-matured-entries')->assertExitCode(0);

        $payment->refresh();
        $this->assertNotNull($payment->wallet_cleared_at);

        $txs = CommissionWalletTransaction::where('user_id', $this->turfAdmin->id)->get();
        $this->assertTrue($txs->contains('type', 'payment_credit'));

        // Wallet balance: -110.00 + 1010.00 = 900.00
        $this->turfAdmin->refresh();
        $this->assertEquals(900.00, (float)$this->turfAdmin->commission_wallet_balance);

        // 3. Test idempotency: calling clear command or settle methods again should not duplicate transactions
        $countBefore = CommissionWalletTransaction::where('user_id', $this->turfAdmin->id)->count();
        $this->artisan('wallet:clear-matured-entries')->assertExitCode(0);

        $walletService = new WalletService();
        $walletService->settleDeductions($this->turfAdmin, $payment, true);
        $walletService->settleCredit($this->turfAdmin, $payment, true);

        $countAfter = CommissionWalletTransaction::where('user_id', $this->turfAdmin->id)->count();
        $this->assertEquals($countBefore, $countAfter);

        $this->turfAdmin->refresh();
        $this->assertEquals(900.00, (float)$this->turfAdmin->commission_wallet_balance);

        Carbon::setTestNow();
    }

    public function test_cancellation_before_maturity_reverses_nothing_from_wallet()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        $futureDate = '2026-10-05';
        $response = $this->actingAs($this->customer, 'sanctum')->postJson("/api/turfs/{$this->turf->id}/bookings", [
            'slot_ids' => [$this->slot->id],
            'booking_dates' => [$futureDate],
            'booking_type' => 'day',
            'payment_method' => 'App',
            'payment_option' => 'full',
            ...$this->razorpayPayment(1010.00),
        ]);

        $response->assertStatus(200);
        $bookingId = $response->json('booking.id');
        $payment = Payment::where('booking_id', $bookingId)->first();
        $booking = Booking::find($bookingId);
        $bookingDate = $booking->bookingDates()->first();

        $this->turfAdmin->refresh();
        $this->assertEquals(-110.00, (float)$this->turfAdmin->commission_wallet_balance);

        // Cancel via API endpoint
        $cancelResponse = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", [
                'booking_date_ids' => [$bookingDate->id],
            ]);
        $cancelResponse->assertStatus(200);

        // No refund_adjustment should be created because credit was never given!
        $refundTxs = CommissionWalletTransaction::where('user_id', $this->turfAdmin->id)
            ->where('type', 'refund_adjustment')
            ->count();
        $this->assertEquals(0, $refundTxs);

        // Wallet balance has -110.00 initial deductions + 100.00 commission returned on cancellation = -10.00
        $this->turfAdmin->refresh();
        $this->assertEquals(-10.00, (float)$this->turfAdmin->commission_wallet_balance);

        Carbon::setTestNow();
    }

    public function test_cancellation_after_maturity_reverses_credit_correctly()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        $futureDate = '2026-10-05';
        $response = $this->actingAs($this->customer, 'sanctum')->postJson("/api/turfs/{$this->turf->id}/bookings", [
            'slot_ids' => [$this->slot->id],
            'booking_dates' => [$futureDate],
            'booking_type' => 'day',
            'payment_method' => 'App',
            'payment_option' => 'full',
            ...$this->razorpayPayment(1010.00),
        ]);

        $bookingId = $response->json('booking.id');
        $booking = Booking::find($bookingId);
        $bookingDate = $booking->bookingDates()->first();

        // Advance to maturity and release credit
        Carbon::setTestNow(Carbon::parse('2026-10-05 10:00:00'));
        $this->artisan('wallet:clear-matured-entries');

        $this->turfAdmin->refresh();
        $this->assertEquals(900.00, (float)$this->turfAdmin->commission_wallet_balance);

        // Cancel booking after maturity
        $cancelResponse = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", [
                'booking_date_ids' => [$bookingDate->id],
            ]);
        $cancelResponse->assertStatus(200);

        // refund_adjustment reverses the refunded amount (950.00) debited from turf owner
        $refundTx = CommissionWalletTransaction::where('user_id', $this->turfAdmin->id)
            ->where('type', 'refund_adjustment')
            ->first();
        $this->assertNotNull($refundTx);
        $this->assertEquals(-950.00, (float)$refundTx->amount);

        // 900.00 (matured balance) - 950.00 (refund) + 100.00 (commission returned) = 50.00
        $this->turfAdmin->refresh();
        $this->assertEquals(50.00, (float)$this->turfAdmin->commission_wallet_balance);

        Carbon::setTestNow();
    }

    public function test_cancelled_online_booking_releases_only_the_turf_share()
    {
        // ₹1,010 online, cancelled before the session with a ₹200 turf cancellation fee.
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));
        $this->turf->update(['cancellation_fee' => 200.00]);

        $response = $this->actingAs($this->customer, 'sanctum')->postJson("/api/turfs/{$this->turf->id}/bookings", [
            'slot_ids' => [$this->slot->id],
            'booking_dates' => ['2026-10-05'],
            'booking_type' => 'day',
            'payment_method' => 'App',
            'payment_option' => 'full',
            ...$this->razorpayPayment(1010.00),
        ]);
        $booking = Booking::find($response->json('booking.id'));
        $bookingDate = $booking->bookingDates()->first();

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", ['booking_date_ids' => [$bookingDate->id]])
            ->assertStatus(200);

        $cancellation = \App\Models\BookingCancellation::where('booking_id', $booking->id)->first();
        $this->assertEquals(200.00, (float) $cancellation->turf_cancellation_fee);
        $this->assertEquals(50.00, (float) $cancellation->saas_cancellation_fee);
        $this->assertEquals(750.00, (float) $cancellation->refund_amount);

        // Every session is cancelled, so the money is final: released on the next nightly run.
        $this->artisan('wallet:clear-matured-entries');

        // The platform holds 1010 - 750 = 260 online and keeps its own ₹50 cancellation fee,
        // so the turf is credited ₹210.
        $credits = (float) CommissionWalletTransaction::where('user_id', $this->turfAdmin->id)
            ->where('type', 'payment_credit')->sum('amount');
        $this->assertEquals(210.00, $credits);

        // Wallet: -10 platform fee - 100 commission + 100 commission returned + 210 = 200 (full turf cancellation fee).
        $this->turfAdmin->refresh();
        $this->assertEquals(200.00, (float) $this->turfAdmin->commission_wallet_balance);

        $payment = Payment::where('booking_id', $booking->id)->first();
        $this->assertNotNull($payment->wallet_cleared_at);
        $this->assertEquals(200.00, (float) $payment->turf_payout_amount);

        // Idempotent: a second nightly run changes nothing.
        $this->artisan('wallet:clear-matured-entries');
        $this->turfAdmin->refresh();
        $this->assertEquals(200.00, (float) $this->turfAdmin->commission_wallet_balance);
        $this->assertEquals(1, CommissionWalletTransaction::where('type', 'payment_credit')->count());

        Carbon::setTestNow();
    }

    public function test_multi_date_long_booking_posts_deductions_immediately_and_matures_per_date()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        $date1 = '2026-10-03';
        $date2 = '2026-10-07';

        $response = $this->actingAs($this->customer, 'sanctum')->postJson("/api/turfs/{$this->turf->id}/bookings", [
            'slot_ids' => [$this->slot->id],
            'booking_dates' => [$date1, $date2],
            'booking_type' => 'long',
            'payment_method' => 'App',
            'payment_option' => 'full',
            ...$this->razorpayPayment(2010.00),
        ]);

        $response->assertStatus(200);
        $bookingId = $response->json('booking.id');

        $payments = Payment::where('booking_id', $bookingId)->orderBy('id')->get();
        $this->assertCount(2, $payments);

        // Deductions settled for both immediately
        foreach ($payments as $p) {
            $this->assertNotNull($p->deductions_settled_at);
            $this->assertNull($p->wallet_cleared_at);
        }

        // Platform fee charged once (10.00), commission 100 for each date (200 total) -> -210.00
        $this->turfAdmin->refresh();
        $this->assertEquals(-210.00, (float)$this->turfAdmin->commission_wallet_balance);

        // Advance to date 1 -> first payment matures
        Carbon::setTestNow(Carbon::parse('2026-10-03 12:00:00'));
        $this->artisan('wallet:clear-matured-entries');

        $payments[0]->refresh();
        $payments[1]->refresh();

        $this->assertNotNull($payments[0]->wallet_cleared_at);
        $this->assertNull($payments[1]->wallet_cleared_at);

        // One credit of 1005.00: -210.00 + 1005 = 795.00
        $this->turfAdmin->refresh();
        $this->assertEquals(795.00, (float)$this->turfAdmin->commission_wallet_balance);

        // Advance to date 2 -> second payment matures
        Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00'));
        $this->artisan('wallet:clear-matured-entries');

        $payments[1]->refresh();
        $this->assertNotNull($payments[1]->wallet_cleared_at);

        // Second credit: 795.00 + 1005 = 1800.00
        $this->turfAdmin->refresh();
        $this->assertEquals(1800.00, (float)$this->turfAdmin->commission_wallet_balance);

        Carbon::setTestNow();
    }

    public function test_pay_at_location_offline_booking_behavior_unaffected()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        $this->turf->update(['is_pay_at_location_active' => true]);

        $futureDate = '2026-10-05';
        $response = $this->actingAs($this->customer, 'sanctum')->postJson("/api/turfs/{$this->turf->id}/bookings", [
            'slot_ids' => [$this->slot->id],
            'booking_dates' => [$futureDate],
            'booking_type' => 'day',
            'payment_method' => 'offline',
            'payment_option' => 'full',
        ]);

        $response->assertStatus(200);
        $bookingId = $response->json('booking.id');

        $payment = Payment::where('booking_id', $bookingId)->first();
        $this->assertNotNull($payment);
        $this->assertNotNull($payment->deductions_settled_at);
        $this->assertNotNull($payment->wallet_cleared_at);

        // Deductions (-110.00) and offline record (0.00) both posted immediately
        $txs = CommissionWalletTransaction::where('user_id', $this->turfAdmin->id)->get();
        $this->assertTrue($txs->contains('type', 'platform_fee_debit'));
        $this->assertTrue($txs->contains('type', 'commission_debit'));
        $this->assertTrue($txs->contains('type', 'offline_booking_record'));

        $this->turfAdmin->refresh();
        $this->assertEquals(-110.00, (float)$this->turfAdmin->commission_wallet_balance);

        // Later manager records cash collected at venue
        $bookingDate = BookingDate::where('booking_id', $bookingId)->first();
        $recordResponse = $this->actingAs($this->turfAdmin, 'sanctum')->postJson("/api/booking-dates/{$bookingDate->id}/payments", [
            'payment_method' => 'Cash',
            'amount' => 1010.00,
        ]);
        $recordResponse->assertStatus(200);

        // Wallet balance remains unchanged at -110.00 (cash retained by venue, no double commission)
        $this->turfAdmin->refresh();
        $this->assertEquals(-110.00, (float)$this->turfAdmin->commission_wallet_balance);

        Carbon::setTestNow();
    }

    public function test_part_online_part_cash_credits_only_the_online_money()
    {
        // ₹505 online now, ₹505 cash at the venue. Only the ₹505 that passed through the platform may
        // reach the wallet; the cash is already with the turf.
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));
        $response = $this->actingAs($this->customer, 'sanctum')->postJson("/api/turfs/{$this->turf->id}/bookings", [
            'slot_ids' => [$this->slot->id], 'booking_dates' => ['2026-10-05'], 'booking_type' => 'day',
            'payment_method' => 'App', 'payment_option' => 'part', ...$this->razorpayPayment(505.00),
        ])->assertStatus(200);
        $bookingDate = Booking::find($response->json('booking.id'))->bookingDates()->first();
        $this->actingAs($this->turfAdmin, 'sanctum')
            ->postJson("/api/booking-dates/{$bookingDate->id}/payments", ['payment_method' => 'Cash', 'amount' => 505.00])
            ->assertStatus(200);

        Carbon::setTestNow(Carbon::parse('2026-10-06 10:00:00'));
        $this->artisan('wallet:clear-matured-entries');

        $this->assertEquals(505.00, (float) CommissionWalletTransaction::where('type', 'payment_credit')->sum('amount'));
        $this->turfAdmin->refresh();
        $this->assertEquals(395.00, (float) $this->turfAdmin->commission_wallet_balance); // 505 - 10 fee - 100 commission

        Carbon::setTestNow();
    }

    /** Two-session booking, ₹2,010 online. Commission 10% of ₹2,000 = ₹200, platform fee ₹10. */
    private function bookTwoOnlineSessions(): Booking
    {
        $this->turf->update(['cancellation_fee' => 200.00]);
        $response = $this->actingAs($this->customer, 'sanctum')->postJson("/api/turfs/{$this->turf->id}/bookings", [
            'slot_ids' => [$this->slot->id], 'booking_dates' => ['2026-10-05', '2026-10-06'], 'booking_type' => 'long',
            'payment_method' => 'App', 'payment_option' => 'full', ...$this->razorpayPayment(2010.00),
        ])->assertStatus(200);

        return Booking::find($response->json('booking.id'));
    }

    public function test_cancellation_after_other_session_was_paid_out_still_releases_turf_share()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));
        $booking = $this->bookTwoOnlineSessions();
        [$first, $second] = $booking->bookingDates()->orderBy('booking_date')->get()->all();

        // First session's day: its share is paid out.
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));
        $this->artisan('wallet:clear-matured-entries');
        $this->turfAdmin->refresh();
        $this->assertEquals(795.00, (float) $this->turfAdmin->commission_wallet_balance); // 1005 - 210 deductions

        // Second session cancelled the same day, before it starts.
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", ['booking_date_ids' => [$second->id]])
            ->assertStatus(200);
        $cancellation = \App\Models\BookingCancellation::where('booking_date_id', $second->id)->first();
        $this->assertEquals(200.00, (float) $cancellation->turf_cancellation_fee);

        $this->artisan('wallet:clear-matured-entries');

        // Turf is owed ₹1,000 first session + ₹200 fee on second - ₹100 commission on first session = ₹1,100 (+100 commission returned on cancelled session).
        $this->turfAdmin->refresh();
        $this->assertEquals(1100.00, (float) $this->turfAdmin->commission_wallet_balance);
        $this->assertNotNull(Payment::where('booking_date_id', $second->id)->first()->wallet_cleared_at);

        Carbon::setTestNow();
    }

    public function test_cancellation_before_other_session_is_paid_out_in_one_credit()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));
        $booking = $this->bookTwoOnlineSessions();
        [$first, $second] = $booking->bookingDates()->orderBy('booking_date')->get()->all();

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", ['booking_date_ids' => [$second->id]])
            ->assertStatus(200);

        // The remaining session is still in the future: nothing is released yet.
        $this->artisan('wallet:clear-matured-entries');
        $this->assertEquals(0, CommissionWalletTransaction::where('type', 'payment_credit')->count());

        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));
        $this->artisan('wallet:clear-matured-entries');
        $this->artisan('wallet:clear-matured-entries');

        $this->turfAdmin->refresh();
        $this->assertEquals(1100.00, (float) $this->turfAdmin->commission_wallet_balance);
        $this->assertEquals(0, Payment::where('booking_id', $booking->id)->whereNull('wallet_cleared_at')->count());

        Carbon::setTestNow();
    }

    public function test_cancellation_awaiting_admin_decision_holds_the_money()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));
        $this->turf->update(['cancellation_fee' => 200.00]);
        $response = $this->actingAs($this->customer, 'sanctum')->postJson("/api/turfs/{$this->turf->id}/bookings", [
            'slot_ids' => [$this->slot->id], 'booking_dates' => ['2026-10-05'], 'booking_type' => 'day',
            'payment_method' => 'App', 'payment_option' => 'full', ...$this->razorpayPayment(1010.00),
        ]);
        $booking = Booking::find($response->json('booking.id'));
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", ['booking_date_ids' => [$booking->bookingDates()->first()->id]])
            ->assertStatus(200);
        \App\Models\BookingCancellation::where('booking_id', $booking->id)->update(['refund_status' => 'Pending Resolution']);

        $this->artisan('wallet:clear-matured-entries');

        $this->assertEquals(0, CommissionWalletTransaction::where('type', 'payment_credit')->count());
        $this->assertNull(Payment::where('booking_id', $booking->id)->first()->wallet_cleared_at);

        Carbon::setTestNow();
    }
}
