<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\BookingDate;
use App\Models\CommissionInvoice;
use App\Models\CommissionWalletTransaction;
use App\Models\Location;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\SaasSetting;
use App\Models\Slot;
use App\Models\SlotCategory;
use App\Models\Turf;
use App\Models\User;
use App\Services\BookingCancellationService;
use App\Services\CommissionInvoiceService;
use App\Services\MoneyAuditService;
use App\Services\ReportService;
use App\Services\WalletService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesRazorpay;
use Tests\TestCase;

class CancellationReversalTest extends TestCase
{
    use RefreshDatabase, FakesRazorpay;

    protected User $owner;
    protected User $customer;
    protected Turf $turf;
    protected Slot $slot;
    protected Slot $slot2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->razorpayFeePercent = 2.0;

        SaasSetting::create([
            'is_gst_billing_active' => false,
            'state_code' => '27',
            'platform_fee' => 10.00,
            'booking_gst_percentage' => 0.00,
            'commission_percentage' => 8.00,
            'commission_gst_percentage' => 0.00,
            'cancellation_fee_percentage' => 5.00,
            'payout_charges' => 40.00,
            'payout_hours' => 24,
            'min_slots_booking' => 1,
            'payout_mode' => 'manual',
        ]);

        $this->owner = User::factory()->create([
            'commission_wallet_balance' => 0.00,
            'payout_method' => 'upi',
            'upi_id' => 'owner@upi',
        ]);
        $this->owner->assignRole('turf-admin');

        $this->customer = User::factory()->create();
        $this->customer->assignRole('customer');

        $location = Location::create([
            'user_id' => $this->owner->id,
            'name' => 'Reversal Arena',
            'address' => 'Station Road',
            'city' => 'Mumbai',
        ]);

        $this->turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Reversal Turf',
            'type' => 'Football',
            'is_active' => true,
            'is_online_payment_active' => true,
            'is_pay_at_location_active' => true,
            'is_part_payment_active' => true,
            'part_payment_type' => 'percentage',
            'part_payment_value' => 50,
            'is_cancellation_active' => true,
            'cancellation_hours' => 1,
            'cancellation_fee' => 40.00,
            'is_reschedule_active' => true,
            'max_reschedules' => 3,
        ]);

        $category = SlotCategory::create(['name' => 'Standard']);
        $this->slot = Slot::create([
            'slot_category_id' => $category->id,
            'name' => '6-7 PM',
            'from_time' => '18:00:00',
            'to_time' => '19:00:00',
            'duration' => 60,
        ]);
        $this->slot2 = Slot::create([
            'slot_category_id' => $category->id,
            'name' => '7-8 PM',
            'from_time' => '19:00:00',
            'to_time' => '20:00:00',
            'duration' => 60,
        ]);

        foreach ([$this->slot, $this->slot2] as $s) {
            $this->turf->slots()->attach($s->id, [
                'is_active' => true,
                'mon' => 600, 'tue' => 600, 'wed' => 600, 'thu' => 600, 'fri' => 600,
                'sat' => 600, 'sun' => 600,
            ]);
        }

        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payload(array $dates, array $slotIds, string $method, string $option): array
    {
        return [
            'slot_ids' => $slotIds,
            'booking_dates' => $dates,
            'booking_type' => count($dates) > 1 ? 'long' : 'day',
            'payment_method' => $method,
            'payment_option' => $option,
        ];
    }

    private function bookOnline(array $dates, string $option = 'full', ?array $slotIds = null): Booking
    {
        $slotIds ??= [$this->slot->id];
        $preview = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/turfs/{$this->turf->id}/bookings/preview", $this->payload($dates, $slotIds, 'App', $option))
            ->assertStatus(200)->json();
        $payable = (float) ($preview['payable_now'] ?? $preview['pricing']['payable_now'] ?? 0);

        $response = $this->actingAs($this->customer, 'sanctum')->postJson(
            "/api/turfs/{$this->turf->id}/bookings",
            $this->payload($dates, $slotIds, 'App', $option) + $this->razorpayPayment($payable)
        )->assertStatus(200);

        return Booking::find($response->json('booking.id'));
    }

    private function bookPayAtVenue(array $dates): Booking
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/turfs/{$this->turf->id}/bookings", $this->payload($dates, [$this->slot->id], 'offline', 'full'))
            ->assertStatus(200);

        return Booking::find($response->json('booking.id'));
    }

    private function collectCash(Booking $booking, ?float $amount = null): void
    {
        foreach ($booking->fresh()->bookingDates as $bDate) {
            $due = $amount ?? (float) $bDate->fresh()->balance_amount;
            if ($due > 0) {
                $this->actingAs($this->owner, 'sanctum')
                    ->postJson("/api/booking-dates/{$bDate->id}/payments", ['payment_method' => 'Cash', 'amount' => $due])
                    ->assertStatus(200);
            }
        }
    }

    private function wallet(): float
    {
        return round((float) $this->owner->fresh()->commission_wallet_balance, 2);
    }

    /**
     * 1. Online full booking, cancelled by the player (matches live test booking #3).
     */
    public function test_1_online_full_booking_cancelled_by_player(): void
    {
        $booking = $this->bookOnline(['2026-10-05']); // 600 slot + 10 platform fee = 610
        $this->assertEquals(610.00, (float) $booking->total_amount);

        // Cancel via player API
        $bDate = $booking->bookingDates()->first();
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", [
                'booking_date_ids' => [$bDate->id],
                'reason' => 'Change of plans',
            ])
            ->assertStatus(200);

        $cancellation = BookingCancellation::where('booking_id', $booking->id)->first();
        $this->assertNotNull($cancellation);

        // Commission returned = 48.00 (8% of 600)
        $this->assertEquals(48.00, (float) $cancellation->commission_reversed_amount);
        $commReversal = CommissionWalletTransaction::where('reference_type', BookingCancellation::class)
            ->where('reference_id', $cancellation->id)
            ->where('type', 'commission_reversal')
            ->first();
        $this->assertNotNull($commReversal);
        $this->assertEquals(48.00, (float) $commReversal->amount);

        // Razorpay fee returned = 14.40 (2% of 610 = 12.20 + 18% GST = 14.40)
        $this->assertEquals(14.40, (float) $cancellation->gateway_fee_reversed_amount);
        $gwReversal = CommissionWalletTransaction::where('reference_type', BookingCancellation::class)
            ->where('reference_id', $cancellation->id)
            ->where('type', 'gateway_charge_reversal')
            ->first();
        $this->assertNotNull($gwReversal);
        $this->assertEquals(14.40, (float) $gwReversal->amount);

        // At this point: -10 platform fee - 48 commission - 14.40 PG + 48 return + 14.40 return = -10.00
        $this->assertEquals(-10.00, $this->wallet());

        // Run clear-matured-entries: true-up releases the retained cancellation online money (+50.00)
        Carbon::setTestNow(Carbon::parse('2026-10-06 01:00:00'));
        $this->artisan('wallet:clear-matured-entries');

        // Net wallet balance = +40.00, exactly the turf cancellation fee
        $this->assertEquals(40.00, $this->wallet());

        // Run money:check for owner and booking
        $auditService = new MoneyAuditService();
        $bookingRows = $auditService->checkBooking($booking->fresh(), false);
        $this->assertTrue(collect($bookingRows)->where('result', 'FAIL')->isEmpty(), 'Booking check must PASS');

        $ownerRows = $auditService->checkOwner($this->owner->fresh(), false);
        $this->assertTrue(collect($ownerRows)->where('result', 'FAIL')->isEmpty(), 'Owner check must PASS');
    }

    /**
     * 2. Pay at venue with cash collected, then cancelled.
     */
    public function test_2_pay_at_venue_with_cash_collected_then_cancelled(): void
    {
        $booking = $this->bookPayAtVenue(['2026-10-05']);
        $this->collectCash($booking);

        // After collecting cash: -10 platform fee - 48 commission = -58.00
        $this->assertEquals(-58.00, $this->wallet());

        $bDate = $booking->bookingDates()->first();
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", [
                'booking_date_ids' => [$bDate->id],
            ])
            ->assertStatus(200);

        $cancellation = BookingCancellation::where('booking_id', $booking->id)->first();
        $this->assertEquals(48.00, (float) $cancellation->commission_reversed_amount);
        $this->assertEquals(0.00, (float) $cancellation->gateway_fee_reversed_amount);

        // Wallet has: -10 platform fee - 48 commission + 48 commission returned - 30 platform cancellation fee debit = -40.00
        $this->assertEquals(-40.00, $this->wallet());

        $auditService = new MoneyAuditService();
        $ownerRows = $auditService->checkOwner($this->owner->fresh(), false);
        $this->assertTrue(collect($ownerRows)->where('result', 'FAIL')->isEmpty());
    }

    /**
     * 3. Pay at venue, cancelled before any cash.
     */
    public function test_3_pay_at_venue_cancelled_before_any_cash(): void
    {
        $booking = $this->bookPayAtVenue(['2026-10-05']);
        // Booking time debits platform fee (-10) and commission (-48) upfront
        $this->assertEquals(-58.00, $this->wallet());

        $bDate = $booking->bookingDates()->first();
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", [
                'booking_date_ids' => [$bDate->id],
            ])
            ->assertStatus(200);

        $cancellation = BookingCancellation::where('booking_id', $booking->id)->first();
        // Commission was debited at booking time, so it is returned (+48)
        $this->assertEquals(48.00, (float) $cancellation->commission_reversed_amount);
        $this->assertEquals(0.00, (float) $cancellation->gateway_fee_reversed_amount);

        // Net wallet balance is now -10.00 (-58 + 48 commission returned = -10)
        $this->assertEquals(-10.00, $this->wallet());

        $auditService = new MoneyAuditService();
        $ownerRows = $auditService->checkOwner($this->owner->fresh(), false);
        $this->assertTrue(collect($ownerRows)->where('result', 'FAIL')->isEmpty());
    }

    /**
     * 4. Two-session online booking, one session cancelled.
     */
    public function test_4_two_session_online_booking_one_session_cancelled(): void
    {
        $dates = ['2026-10-05', '2026-10-06'];
        $booking = $this->bookOnline($dates, 'full');
        $bDates = $booking->bookingDates()->orderBy('booking_date')->get();

        // Total 1200 + 10 = 1210. Commission = 96.00 (48.00 per session).
        // Cancel date 1 (on 2026-10-01)
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", [
                'booking_date_ids' => [$bDates[0]->id],
            ])
            ->assertStatus(200);

        $c1 = BookingCancellation::where('booking_date_id', $bDates[0]->id)->first();
        $this->assertEquals(48.00, (float) $c1->commission_reversed_amount);
        $this->assertGreaterThan(0, (float) $c1->gateway_fee_reversed_amount);

        // Advance to 2026-10-06 10:00 (before session 2 starts at 18:00)
        Carbon::setTestNow(Carbon::parse('2026-10-06 10:00:00'));

        // Cancel date 2
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", [
                'booking_date_ids' => [$bDates[1]->id],
            ])
            ->assertStatus(200);

        $c2 = BookingCancellation::where('booking_date_id', $bDates[1]->id)->first();
        $this->assertEquals(48.00, (float) $c2->commission_reversed_amount);

        $totalCommReturned = (float) BookingCancellation::where('booking_id', $booking->id)->sum('commission_reversed_amount');
        $this->assertEquals(96.00, $totalCommReturned);
    }

    /**
     * 5. Part payment (online deposit plus cash), cancelled.
     */
    public function test_5_part_payment_cancelled(): void
    {
        // 50% part payment: 300 deposit + 5 platform fee share = 305 online
        $booking = $this->bookOnline(['2026-10-05'], 'part');
        $bDate = $booking->bookingDates()->first();

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", [
                'booking_date_ids' => [$bDate->id],
            ])
            ->assertStatus(200);

        $cancellation = BookingCancellation::where('booking_id', $booking->id)->first();
        // Commission for session (48.00) comes back
        $this->assertEquals(48.00, (float) $cancellation->commission_reversed_amount);

        // Gateway fee on 305: 2% of 305 = 6.10 + 18% GST = 7.20 comes back
        $this->assertEquals(7.20, (float) $cancellation->gateway_fee_reversed_amount);
    }

    /**
     * 6. Admin path (BookingCancellationService::cancelBookingDates, Pending Resolution).
     */
    public function test_6_admin_path_pending_resolution(): void
    {
        $booking = $this->bookOnline(['2026-10-05']);
        $bDate = $booking->bookingDates()->first();

        $service = app(BookingCancellationService::class);
        $service->cancelBookingDates($booking, [$bDate->id], $this->owner, 'Weather delay');

        $cancellation = BookingCancellation::where('booking_id', $booking->id)->first();
        $this->assertNotNull($cancellation);
        $this->assertEquals('Pending Resolution', $cancellation->refund_status);

        // Immediate reversal
        $this->assertEquals(48.00, (float) $cancellation->commission_reversed_amount);
        $this->assertEquals(14.40, (float) $cancellation->gateway_fee_reversed_amount);

        $initialTxCount = CommissionWalletTransaction::where('reference_type', BookingCancellation::class)
            ->where('reference_id', $cancellation->id)
            ->count();

        // Later resolve refund
        $service->resolveRefund($cancellation->fresh(), 'full_compensation', 'online_gateway', $this->owner);

        // Does NOT create a second reversal
        $afterResolveTxCount = CommissionWalletTransaction::where('reference_type', BookingCancellation::class)
            ->where('reference_id', $cancellation->id)
            ->count();

        $this->assertEquals($initialTxCount, $afterResolveTxCount);
    }

    /**
     * 7. Idempotency.
     */
    public function test_7_idempotency(): void
    {
        $booking = $this->bookOnline(['2026-10-05']);
        $bDate = $booking->bookingDates()->first();

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", ['booking_date_ids' => [$bDate->id]])
            ->assertStatus(200);

        $cancellation = BookingCancellation::where('booking_id', $booking->id)->first();
        $walletService = new WalletService();

        $txCountBefore = CommissionWalletTransaction::where('reference_type', BookingCancellation::class)
            ->where('reference_id', $cancellation->id)
            ->count();

        // Call again
        $walletService->reverseDeductionsForCancellation($cancellation);
        $this->assertEquals($txCountBefore, CommissionWalletTransaction::where('reference_type', BookingCancellation::class)->where('reference_id', $cancellation->id)->count());

        // Run console command
        $this->artisan('wallet:reverse-cancelled-commissions')->assertExitCode(0);
        $this->assertEquals($txCountBefore, CommissionWalletTransaction::where('reference_type', BookingCancellation::class)->where('reference_id', $cancellation->id)->count());
    }

    /**
     * 8. Part 6 console command (--dry-run and live).
     */
    public function test_8_part_6_command(): void
    {
        $booking = $this->bookOnline(['2026-10-05']);
        $bDate = $booking->bookingDates()->first();

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", ['booking_date_ids' => [$bDate->id]])
            ->assertStatus(200);

        $cancellation = BookingCancellation::where('booking_id', $booking->id)->first();

        // Simulate an old cancellation before this migration: delete reversal rows and reset amounts
        CommissionWalletTransaction::where('reference_type', BookingCancellation::class)
            ->where('reference_id', $cancellation->id)
            ->delete();
        $cancellation->update([
            'commission_reversed_amount' => 0.00,
            'gateway_fee_reversed_amount' => 0.00,
        ]);
        $this->owner->update(['commission_wallet_balance' => -72.40]);

        // Dry run
        $this->artisan('wallet:reverse-cancelled-commissions', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN COMPLETE')
            ->assertExitCode(0);

        // Confirm nothing was written
        $this->assertEquals(0, CommissionWalletTransaction::where('reference_type', BookingCancellation::class)->where('reference_id', $cancellation->id)->count());

        // Live run
        $this->artisan('wallet:reverse-cancelled-commissions')
            ->expectsOutputToContain('Total commission returned: ₹48.00')
            ->expectsOutputToContain('Total Razorpay fees returned: ₹14.40')
            ->assertExitCode(0);

        $this->assertEquals(2, CommissionWalletTransaction::where('reference_type', BookingCancellation::class)->where('reference_id', $cancellation->id)->count());

        // Second run creates none
        $this->artisan('wallet:reverse-cancelled-commissions')
            ->expectsOutputToContain('No unreversed cancellations found')
            ->assertExitCode(0);
    }

    /**
     * 9. Commission invoice.
     */
    public function test_9_commission_invoice(): void
    {
        $invoiceService = new CommissionInvoiceService();

        // Booking 1 in Sept, cancelled in Sept -> net commission 0 -> Sept invoice skipped
        Carbon::setTestNow(Carbon::parse('2026-09-10 10:00:00'));
        $booking1 = $this->bookOnline(['2026-09-15']);
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking1->id}/cancel", ['booking_date_ids' => [$booking1->bookingDates()->first()->id]])
            ->assertStatus(200);

        // Sept invoice in Oct: net 0 -> returns null
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));
        $septInvoice = $invoiceService->generateForTurf($this->turf, Carbon::parse('2026-09-01'));
        $this->assertNull($septInvoice);

        // Booking 2 in Oct (session in late Nov) -> Oct invoice generated in Nov with 48.00
        $booking2 = $this->bookOnline(['2026-11-25']);
        Carbon::setTestNow(Carbon::parse('2026-11-01 10:00:00'));
        $octInvoice = $invoiceService->generateForTurf($this->turf, Carbon::parse('2026-10-01'));
        $this->assertNotNull($octInvoice);
        $this->assertEquals(48.00, (float) $octInvoice->total_amount);

        // Cancel booking 2 in Nov (session is late Nov, so cancellation is allowed)
        Carbon::setTestNow(Carbon::parse('2026-11-05 10:00:00'));
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking2->id}/cancel", ['booking_date_ids' => [$booking2->bookingDates()->first()->id]])
            ->assertStatus(200);

        // Two bookings in Nov (booking 3 and booking 4: 48 + 48 = 96) + Nov reversal of booking 2 (-48) = net 48
        $booking3 = $this->bookOnline(['2026-11-12']);
        $booking4 = $this->bookOnline(['2026-11-14']);

        Carbon::setTestNow(Carbon::parse('2026-12-01 10:00:00'));
        $novInvoice = $invoiceService->generateForTurf($this->turf, Carbon::parse('2026-11-01'));
        $this->assertNotNull($novInvoice);
        $this->assertEquals(48.00, (float) $novInvoice->total_amount); // 48 + 48 - 48 = 48
    }

    /**
     * 10. Reports.
     */
    public function test_10_reports(): void
    {
        $booking = $this->bookOnline(['2026-10-05']);
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", ['booking_date_ids' => [$booking->bookingDates()->first()->id]])
            ->assertStatus(200);

        $reportService = new ReportService();
        $summary = $reportService->getCommissionFeeSummary($this->owner, '2026-10-01', '2026-10-31');

        $monthData = collect($summary['months'])->firstWhere('month', '2026-10');
        $this->assertNotNull($monthData);
        // Commission net of reversal: 48 - 48 = 0.00
        $this->assertEquals(0.00, (float) $monthData['commission']);
        // PG charges net of reversal: 14.40 - 14.40 = 0.00
        $this->assertEquals(0.00, (float) $monthData['pg_charges']);
    }

    /**
     * 11. money:check catches errors.
     */
    public function test_11_money_check_catches_errors(): void
    {
        $booking = $this->bookOnline(['2026-10-05']);
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", ['booking_date_ids' => [$booking->bookingDates()->first()->id]])
            ->assertStatus(200);

        $cancellation = BookingCancellation::where('booking_id', $booking->id)->first();
        $auditService = new MoneyAuditService();

        // 1. Delete commission_reversal and adjust wallet -> check 26 FAILs
        $commReversal = CommissionWalletTransaction::where('reference_type', BookingCancellation::class)
            ->where('reference_id', $cancellation->id)
            ->where('type', 'commission_reversal')
            ->first();
        $commReversal->delete();

        $rows = $auditService->checkBooking($booking->fresh(), false);
        $check26 = collect($rows)->firstWhere('check', '26. Commission returned for cancelled sessions');
        $this->assertNotNull($check26);
        $this->assertEquals('FAIL', $check26['result']);

        // Restore commission_reversal using applyDelta
        $walletService = new WalletService();
        $walletService->applyDelta($this->owner, 48.00, 'commission_reversal', $cancellation);

        // 2. Double gateway_charge_reversal -> check 27 FAILs
        $gwReversal = CommissionWalletTransaction::where('reference_type', BookingCancellation::class)
            ->where('reference_id', $cancellation->id)
            ->where('type', 'gateway_charge_reversal')
            ->first();
        $gwReversal->update(['amount' => 28.80]);

        $rows2 = $auditService->checkBooking($booking->fresh(), false);
        $check27 = collect($rows2)->firstWhere('check', '27. Razorpay fee returned for cancelled sessions');
        $this->assertNotNull($check27);
        $this->assertEquals('FAIL', $check27['result']);
    }

    /**
     * 12. Player's refund is unchanged.
     */
    public function test_12_player_refund_is_unchanged(): void
    {
        $booking = $this->bookOnline(['2026-10-05']); // 610 total
        $bDate = $booking->bookingDates()->first();

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", ['booking_date_ids' => [$bDate->id]])
            ->assertStatus(200);

        $cancellation = BookingCancellation::where('booking_id', $booking->id)->first();
        $this->assertEquals(530.00, (float) $cancellation->refund_amount);
        $this->assertEquals(80.00, (float) $cancellation->total_cancellation_fee);
        $this->assertEquals(30.00, (float) $cancellation->saas_cancellation_fee);
        $this->assertEquals(40.00, (float) $cancellation->turf_cancellation_fee);
        $this->assertEquals(10.00, (float) $cancellation->platform_fee_retained);
    }
}
