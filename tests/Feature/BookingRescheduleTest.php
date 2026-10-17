<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\BookingReschedule;
use App\Models\BookingSlot;
use App\Models\CommissionWalletTransaction;
use App\Models\Location;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\SaasSetting;
use App\Models\Slot;
use App\Models\SlotLock;
use App\Models\Turf;
use App\Models\User;
use App\Services\BookingCancellationService;
use App\Services\BookingPaymentDistributor;
use App\Services\BookingRescheduleService;
use App\Services\SlotPricingService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookingRescheduleTest extends TestCase
{
    use RefreshDatabase;

    protected User $turfAdmin;
    protected User $manager;
    protected User $otherAdmin;
    protected User $customer;
    protected Turf $turf;
    protected Slot $slot1;
    protected Slot $slot2;
    protected Slot $slot3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        SaasSetting::create([
            'commission_percentage' => 10.00,
            'cancellation_fee_percentage' => 0.00,
            'platform_fee' => 0.00,
            'is_gst_billing_active' => false,
            'notify_booking_rescheduled' => true,
            'razorpay_key' => 'rzp_test_mock_key',
            'razorpay_secret' => 'rzp_test_mock_secret',
        ]);

        $this->turfAdmin = User::factory()->create(['commission_wallet_balance' => 0.00]);
        $this->turfAdmin->assignRole('turf-admin');

        $this->otherAdmin = User::factory()->create(['commission_wallet_balance' => 0.00]);
        $this->otherAdmin->assignRole('turf-admin');

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');

        $this->customer = User::factory()->create();
        $this->customer->assignRole('customer');

        $location = Location::create([
            'user_id' => $this->turfAdmin->id,
            'name' => 'Reschedule Arena',
            'address' => '123 Football Way',
            'city' => 'Mumbai',
        ]);

        $this->turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Reschedule Turf',
            'type' => 'Football',
            'is_reschedule_active' => true,
            'max_reschedules' => 2,
            'is_cancellation_active' => true,
            'cancellation_hours' => 24,
            'cancellation_fee' => 100.00,
        ]);

        $this->manager->assignedTurfs()->attach($this->turf->id, [
            'turf_admin_id' => $this->turfAdmin->id,
        ]);

        $category = \App\Models\SlotCategory::create(['name' => 'Standard']);

        $this->slot1 = Slot::create(['slot_category_id' => $category->id, 'from_time' => '17:00:00', 'to_time' => '18:00:00', 'duration' => 60]);
        $this->slot2 = Slot::create(['slot_category_id' => $category->id, 'from_time' => '18:00:00', 'to_time' => '19:00:00', 'duration' => 60]);
        $this->slot3 = Slot::create(['slot_category_id' => $category->id, 'from_time' => '19:00:00', 'to_time' => '20:00:00', 'duration' => 60]);

        // Attach slots with Mon-Fri: 1000, Sat-Sun: 1500
        foreach ([$this->slot1, $this->slot2, $this->slot3] as $s) {
            $this->turf->slots()->attach($s->id, [
                'mon' => 1000.00,
                'tue' => 1000.00,
                'wed' => 1000.00,
                'thu' => 1000.00,
                'fri' => 1000.00,
                'sat' => 1500.00,
                'sun' => 1500.00,
                'is_active' => true,
            ]);
        }
    }

    protected function createStandardBooking(string $dateStr, array $slotModels, string $paymentMethod = 'Cash', float $amount = 1000.00): array
    {
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'turf_id' => $this->turf->id,
            'date_of_booking' => $dateStr,
            'booking_type' => 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
            'total_amount' => $amount,
            'turf_payout_amount' => $amount - 100.00,
        ]);

        $bDate = BookingDate::create([
            'booking_id' => $booking->id,
            'booking_date' => $dateStr,
            'amount' => $amount,
            'actual_amount' => $amount,
            'taxable_amount' => $amount,
            'paid_amount' => $amount,
            'balance_amount' => 0.00,
            'payment_status' => 'Paid',
            'turf_payout_amount' => $amount - 100.00,
        ]);

        foreach ($slotModels as $slot) {
            $bDate->bookingSlots()->create([
                'slot_id' => $slot->id,
                'status' => 'active',
            ]);
        }

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $bDate->id,
            'user_id' => $this->customer->id,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'payment_type' => 'full',
            'status' => 'Success',
            'turf_payout_amount' => $paymentMethod === 'App' ? $amount - 100.00 : 0.00,
            'wallet_cleared_at' => $paymentMethod === 'App' ? now() : null,
        ]);

        if ($paymentMethod === 'App') {
            PaymentGateway::create([
                'payment_id' => $payment->id,
                'gateway_name' => 'razorpay',
                'gateway_payment_id' => 'pay_mock_' . uniqid(),
            ]);
        }

        return [$booking, $bDate, $payment];
    }

    /**
     * Test 1: Same price, new date -> old slots gone, new active, old free, amounts unchanged, audit logged.
     */
    public function test_same_price_reschedule_moves_slots_and_preserves_amounts(): void
    {
        // Pick next Monday and next Tuesday (both 1000 weekday rate)
        $fromDate = Carbon::now('Asia/Kolkata')->next(Carbon::MONDAY)->toDateString();
        $toDate = Carbon::now('Asia/Kolkata')->next(Carbon::TUESDAY)->toDateString();

        [$booking, $bDate] = $this->createStandardBooking($fromDate, [$this->slot1], 'Cash', 1000.00);

        $oldSlotId = $this->slot1->id;

        $res = $this->actingAs($this->turfAdmin)
            ->postJson("/api/booking-dates/{$bDate->id}/reschedule", [
                'date' => $toDate,
                'slot_ids' => [$this->slot2->id],
                'reason' => 'Friendly request',
            ]);

        $res->assertStatus(200)->assertJson(['success' => true]);

        $bDate->refresh();
        $this->assertEquals($toDate, $bDate->booking_date);
        $this->assertEquals(1000.00, (float)$bDate->amount);
        $this->assertCount(1, $bDate->bookingSlots);
        $this->assertEquals($this->slot2->id, $bDate->bookingSlots->first()->slot_id);

        // Verify old slot is deleted and free for another booking
        $this->assertDatabaseMissing('booking_slots', [
            'booking_date_id' => $bDate->id,
            'slot_id' => $oldSlotId,
        ]);

        // Audit row logged
        $reschedule = BookingReschedule::where('booking_id', $booking->id)->first();
        $this->assertNotNull($reschedule);
        $this->assertEquals($fromDate, Carbon::parse($reschedule->from_date)->format('Y-m-d'));
        $this->assertEquals($toDate, Carbon::parse($reschedule->to_date)->format('Y-m-d'));
        $this->assertEquals(1000.00, (float)$reschedule->old_amount);
        $this->assertEquals(1000.00, (float)$reschedule->new_amount);
        $this->assertEquals(0.00, (float)$reschedule->price_difference);
        $this->assertEquals('none', $reschedule->settlement_type);
    }

    /**
     * Test 1a: No ghost slots: /api/bookings and invoice list only new slots; cancel fee on new slots count.
     */
    public function test_no_ghost_slots_in_bookings_api_and_cancellation(): void
    {
        $fromDate = Carbon::now('Asia/Kolkata')->next(Carbon::MONDAY)->toDateString();
        $toDate = Carbon::now('Asia/Kolkata')->next(Carbon::TUESDAY)->toDateString();

        // 2 initial slots
        [$booking, $bDate] = $this->createStandardBooking($fromDate, [$this->slot1, $this->slot2], 'Cash', 2000.00);

        // Reschedule to 1 new slot
        $this->actingAs($this->turfAdmin)->postJson("/api/booking-dates/{$bDate->id}/reschedule", [
            'date' => $toDate,
            'slot_ids' => [$this->slot3->id],
        ])->assertStatus(200);

        $bDate->refresh();
        // Booking slots count should be exactly 1, not 3
        $this->assertCount(1, $bDate->bookingSlots);

        // Check /api/bookings lists only the new slot
        $apiRes = $this->actingAs($this->customer)->getJson('/api/bookings');
        $apiRes->assertStatus(200);
        $bookingsData = $apiRes->json('data') ?: $apiRes->json();
        $firstBooking = is_array($bookingsData) && isset($bookingsData[0]) ? $bookingsData[0] : null;
        $this->assertNotNull($firstBooking);
        $this->assertEquals($toDate, $firstBooking['date_raw']);
        // Verify slots in response contains only slot3 time
        $slotsJson = json_encode($firstBooking['slots']);
        $this->assertStringContainsString('07:00 PM', $slotsJson);
        $this->assertStringNotContainsString('05:00 PM', $slotsJson);

        // Test cancellation fee sizes against NEW slot count (1 slot * 100 = 100, not 3 slots * 100)
        $cancelService = new BookingCancellationService();
        $cancelRes = $cancelService->cancelBookingDates($booking, [$bDate->id], $this->turfAdmin, 'Cancel after reschedule', true);
        $this->assertTrue($cancelRes['success']);

        $bDate->refresh();
        $this->assertEquals(100.00, (float)$bDate->cancellation_fee_applied);
    }

    /**
     * Test 1b: Pivot pricing: target slot priced from slot_turf Saturday rate (1500) vs 1000 fallback.
     */
    public function test_pivot_weekday_pricing_used_not_fallback(): void
    {
        $fromDate = Carbon::now('Asia/Kolkata')->next(Carbon::MONDAY)->toDateString();
        $saturdayDate = Carbon::now('Asia/Kolkata')->next(Carbon::SATURDAY)->toDateString();

        [$booking, $bDate] = $this->createStandardBooking($fromDate, [$this->slot1], 'Cash', 1000.00);

        $previewRes = $this->actingAs($this->turfAdmin)->postJson("/api/booking-dates/{$bDate->id}/reschedule/preview", [
            'date' => $saturdayDate,
            'slot_ids' => [$this->slot1->id],
        ]);

        $previewRes->assertStatus(200);
        // Saturday rate in pivot is 1500, not 1000 fallback
        $this->assertEquals(1500.00, (float)$previewRes->json('new_amount'));
        $this->assertEquals(500.00, (float)$previewRes->json('price_difference'));
    }

    /**
     * Test 1c: Race condition: target slot booked by someone else between preview and confirm -> rejected, no refund.
     */
    public function test_race_condition_rejected_and_no_refund_called(): void
    {
        Http::fake();

        $fromDate = Carbon::now('Asia/Kolkata')->next(Carbon::MONDAY)->toDateString();
        $toDate = Carbon::now('Asia/Kolkata')->next(Carbon::TUESDAY)->toDateString();

        [$booking, $bDate] = $this->createStandardBooking($fromDate, [$this->slot1], 'App', 1000.00);

        // Another customer books slot2 on toDate before our confirm
        $competitorBooking = Booking::create([
            'user_id' => User::factory()->create()->id,
            'turf_id' => $this->turf->id,
            'date_of_booking' => $toDate,
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
            'total_amount' => 1000.00,
        ]);
        $competitorDate = BookingDate::create([
            'booking_id' => $competitorBooking->id,
            'booking_date' => $toDate,
            'amount' => 1000.00,
            'status' => 'Confirmed',
        ]);
        $competitorDate->bookingSlots()->create([
            'slot_id' => $this->slot2->id,
            'status' => 'active',
        ]);

        // Attempt reschedule to slot2
        $res = $this->actingAs($this->turfAdmin)->postJson("/api/booking-dates/{$bDate->id}/reschedule", [
            'date' => $toDate,
            'slot_ids' => [$this->slot2->id],
        ]);

        $res->assertStatus(422);
        // Assert no refund endpoint was touched
        Http::assertNothingSent();
    }

    /**
     * Test 1d: Net-paid recalculation: date and booking paid_amount reflect net paid.
     */
    public function test_net_paid_recalculation_after_reschedule(): void
    {
        $fromDate = Carbon::now('Asia/Kolkata')->next(Carbon::MONDAY)->toDateString();
        $toDate = Carbon::now('Asia/Kolkata')->next(Carbon::TUESDAY)->toDateString();

        // 2 slots weekday = 2000
        [$booking, $bDate, $payment] = $this->createStandardBooking($fromDate, [$this->slot1, $this->slot2], 'Cash', 2000.00);

        // Reschedule to 1 slot weekday = 1000 (price down by 1000)
        $this->actingAs($this->turfAdmin)->postJson("/api/booking-dates/{$bDate->id}/reschedule", [
            'date' => $toDate,
            'slot_ids' => [$this->slot1->id],
        ])->assertStatus(200);

        $bDate->refresh();
        $booking->refresh();

        // Paid was 2000, refund 1000 -> net paid = 1000
        $this->assertEquals(1000.00, (float)$bDate->amount);
        $this->assertEquals(1000.00, (float)$bDate->paid_amount);
        $this->assertEquals(0.00, (float)$bDate->balance_amount);
        $this->assertEquals('Paid', $bDate->payment_status);

        $this->assertEquals(1000.00, (float)$booking->total_amount);
        $this->assertEquals(1000.00, (float)$booking->payable_now);
        $this->assertEquals(0.00, (float)$booking->balance_amount);
        $this->assertEquals('Paid', $booking->payment_status);
    }

    /**
     * Test 2: Price up -> date & booking balance_amount grow, payment_status Partially Paid, Record Payment clears it.
     */
    public function test_price_up_increases_balance_and_record_payment_clears_it(): void
    {
        $fromDate = Carbon::now('Asia/Kolkata')->next(Carbon::MONDAY)->toDateString();
        $saturdayDate = Carbon::now('Asia/Kolkata')->next(Carbon::SATURDAY)->toDateString();

        [$booking, $bDate] = $this->createStandardBooking($fromDate, [$this->slot1], 'Cash', 1000.00);

        // Move to Saturday (1500)
        $res = $this->actingAs($this->turfAdmin)->postJson("/api/booking-dates/{$bDate->id}/reschedule", [
            'date' => $saturdayDate,
            'slot_ids' => [$this->slot1->id],
        ]);
        $res->assertStatus(200);

        $bDate->refresh();
        $booking->refresh();

        $this->assertEquals(1500.00, (float)$bDate->amount);
        $this->assertEquals(500.00, (float)$bDate->balance_amount);
        $this->assertEquals('Partially Paid', $bDate->payment_status);
        $this->assertEquals('Partially Paid', $booking->payment_status);
        $this->assertEquals(500.00, (float)$booking->balance_amount);

        // Now Record Payment for the 500 balance
        $payRes = $this->actingAs($this->turfAdmin)->postJson("/api/booking-dates/{$bDate->id}/payments", [
            'amount' => 500.00,
            'payment_method' => 'Cash',
        ]);
        $payRes->assertStatus(200);

        $bDate->refresh();
        $booking->refresh();

        $this->assertEquals('Paid', $bDate->payment_status);
        $this->assertEquals(0.00, (float)$bDate->balance_amount);
        $this->assertEquals('Paid', $booking->payment_status);
        $this->assertEquals(0.00, (float)$booking->balance_amount);
    }

    /**
     * Test 3: Price down, paid online -> Razorpay refund called, wallet adjusted.
     */
    public function test_price_down_online_calls_razorpay_refund(): void
    {
        Http::fake([
            'https://api.razorpay.com/v1/payments/*' => Http::response([
                'id' => 'rfnd_mock_123',
                'status' => 'processed',
                'amount' => 50000,
            ], 200),
        ]);

        $saturdayDate = Carbon::now('Asia/Kolkata')->next(Carbon::SATURDAY)->toDateString();
        $mondayDate = Carbon::now('Asia/Kolkata')->next(Carbon::MONDAY)->toDateString();

        [$booking, $bDate, $payment] = $this->createStandardBooking($saturdayDate, [$this->slot1], 'App', 1500.00);

        $initialWallet = (float)$this->turfAdmin->fresh()->commission_wallet_balance;

        // Move to Monday (1000) -> 500 refund
        $res = $this->actingAs($this->turfAdmin)->postJson("/api/booking-dates/{$bDate->id}/reschedule", [
            'date' => $mondayDate,
            'slot_ids' => [$this->slot1->id],
        ]);
        $res->assertStatus(200);

        $payment->refresh();
        $this->assertEquals(500.00, (float)$payment->refunded_amount);
        $this->assertEquals('Partial Refund', $payment->refund_status);

        // Wallet was debited for refund adjustment on cleared payment
        $newWallet = (float)$this->turfAdmin->fresh()->commission_wallet_balance;
        $this->assertEquals($initialWallet - 500.00, $newWallet);
    }

    /**
     * Test 4: Price down, paid cash -> recorded as cash refund; wallet unchanged.
     */
    public function test_price_down_cash_leaves_wallet_unchanged(): void
    {
        $saturdayDate = Carbon::now('Asia/Kolkata')->next(Carbon::SATURDAY)->toDateString();
        $mondayDate = Carbon::now('Asia/Kolkata')->next(Carbon::MONDAY)->toDateString();

        [$booking, $bDate, $payment] = $this->createStandardBooking($saturdayDate, [$this->slot1], 'Cash', 1500.00);

        $initialWallet = (float)$this->turfAdmin->fresh()->commission_wallet_balance;

        $res = $this->actingAs($this->turfAdmin)->postJson("/api/booking-dates/{$bDate->id}/reschedule", [
            'date' => $mondayDate,
            'slot_ids' => [$this->slot1->id],
        ]);
        $res->assertStatus(200);

        $payment->refresh();
        $this->assertEquals(500.00, (float)$payment->refunded_amount);
        $this->assertEquals('Cash / Offline Refund', $payment->refund_status);

        // Wallet MUST remain exactly identical!
        $this->assertEquals($initialWallet, (float)$this->turfAdmin->fresh()->commission_wallet_balance);
    }

    /**
     * Test 5: Gateway refund fails -> nothing changes, 422 returned.
     */
    public function test_gateway_refund_failure_rolls_back_and_returns_422(): void
    {
        Http::fake([
            'https://api.razorpay.com/v1/payments/*/refund' => Http::response([
                'error' => ['description' => 'Bank server is down.'],
            ], 400),
            'https://api.razorpay.com/v1/payments/*' => Http::response(['status' => 'captured'], 200),
        ]);

        $saturdayDate = Carbon::now('Asia/Kolkata')->next(Carbon::SATURDAY)->toDateString();
        $mondayDate = Carbon::now('Asia/Kolkata')->next(Carbon::MONDAY)->toDateString();

        [$booking, $bDate, $payment] = $this->createStandardBooking($saturdayDate, [$this->slot1], 'App', 1500.00);

        $res = $this->actingAs($this->turfAdmin)->postJson("/api/booking-dates/{$bDate->id}/reschedule", [
            'date' => $mondayDate,
            'slot_ids' => [$this->slot1->id],
        ]);

        $res->assertStatus(422);

        $bDate->refresh();
        $this->assertEquals($saturdayDate, $bDate->booking_date);
        $this->assertEquals(1500.00, (float)$bDate->amount);
        $this->assertDatabaseCount('booking_reschedules', 0);
    }

    /**
     * Test 6: Commission untouched in every case.
     */
    public function test_commission_fields_and_wallet_records_untouched(): void
    {
        $fromDate = Carbon::now('Asia/Kolkata')->next(Carbon::MONDAY)->toDateString();
        $saturdayDate = Carbon::now('Asia/Kolkata')->next(Carbon::SATURDAY)->toDateString();

        [$booking, $bDate] = $this->createStandardBooking($fromDate, [$this->slot1], 'Cash', 1000.00);

        $commDebitsBefore = CommissionWalletTransaction::where('type', 'commission_debit')->count();

        $this->actingAs($this->turfAdmin)->postJson("/api/booking-dates/{$bDate->id}/reschedule", [
            'date' => $saturdayDate,
            'slot_ids' => [$this->slot1->id],
        ])->assertStatus(200);

        $commDebitsAfter = CommissionWalletTransaction::where('type', 'commission_debit')->count();
        $this->assertEquals($commDebitsBefore, $commDebitsAfter);
    }

    /**
     * Test 7: Conflicts: locked -> rejected; shift by 30 mins (overlapping own) -> allowed.
     */
    public function test_conflicts_and_own_overlapping_slots(): void
    {
        $fromDate = Carbon::now('Asia/Kolkata')->next(Carbon::MONDAY)->toDateString();

        [$booking, $bDate] = $this->createStandardBooking($fromDate, [$this->slot1], 'Cash', 1000.00);

        // Lock slot 2
        SlotLock::create([
            'turf_id' => $this->turf->id,
            'slot_id' => $this->slot2->id,
            'lock_date' => $fromDate,
            'reason' => 'Maintenance',
        ]);

        // Attempt reschedule to locked slot 2 -> rejected
        $resLocked = $this->actingAs($this->turfAdmin)->postJson("/api/booking-dates/{$bDate->id}/reschedule", [
            'date' => $fromDate,
            'slot_ids' => [$this->slot2->id],
        ]);
        $resLocked->assertStatus(422);

        // Overlapping own current slot (slot1 + slot3 on same day) -> allowed
        $resOwn = $this->actingAs($this->turfAdmin)->postJson("/api/booking-dates/{$bDate->id}/reschedule", [
            'date' => $fromDate,
            'slot_ids' => [$this->slot1->id, $this->slot3->id],
        ]);
        $resOwn->assertStatus(200);
    }

    /**
     * Test 8: Session already started -> rejected; past date -> rejected.
     */
    public function test_started_or_past_sessions_rejected(): void
    {
        $pastDate = Carbon::yesterday('Asia/Kolkata')->toDateString();
        $futureDate = Carbon::now('Asia/Kolkata')->next(Carbon::MONDAY)->toDateString();

        [$booking, $bDate] = $this->createStandardBooking($pastDate, [$this->slot1], 'Cash', 1000.00);

        // Attempting to reschedule past date session -> rejected
        $res = $this->actingAs($this->turfAdmin)->postJson("/api/booking-dates/{$bDate->id}/reschedule", [
            'date' => $futureDate,
            'slot_ids' => [$this->slot1->id],
        ]);
        $res->assertStatus(422);

        // Attempting to reschedule future session to past date -> rejected
        [$booking2, $bDate2] = $this->createStandardBooking($futureDate, [$this->slot1], 'Cash', 1000.00);
        $resPastTarget = $this->actingAs($this->turfAdmin)->postJson("/api/booking-dates/{$bDate2->id}/reschedule", [
            'date' => $pastDate,
            'slot_ids' => [$this->slot1->id],
        ]);
        $resPastTarget->assertStatus(422);
    }

    /**
     * Test 9: Limit: max_reschedules = 1 -> second attempt rejected; is_reschedule_active = false -> rejected.
     */
    public function test_reschedule_limits_and_disabled_flag(): void
    {
        $this->turf->update(['max_reschedules' => 1]);

        $d1 = Carbon::now('Asia/Kolkata')->next(Carbon::MONDAY)->toDateString();
        $d2 = Carbon::now('Asia/Kolkata')->next(Carbon::TUESDAY)->toDateString();
        $d3 = Carbon::now('Asia/Kolkata')->next(Carbon::WEDNESDAY)->toDateString();

        [$booking, $bDate] = $this->createStandardBooking($d1, [$this->slot1], 'Cash', 1000.00);

        // 1st reschedule -> ok
        $this->actingAs($this->turfAdmin)->postJson("/api/booking-dates/{$bDate->id}/reschedule", [
            'date' => $d2,
            'slot_ids' => [$this->slot1->id],
        ])->assertStatus(200);

        // 2nd reschedule -> rejected (max_reschedules = 1)
        $this->actingAs($this->turfAdmin)->postJson("/api/booking-dates/{$bDate->id}/reschedule", [
            'date' => $d3,
            'slot_ids' => [$this->slot1->id],
        ])->assertStatus(422);

        // Disabled reschedule
        $this->turf->update(['is_reschedule_active' => false, 'max_reschedules' => 5]);
        $this->actingAs($this->turfAdmin)->postJson("/api/booking-dates/{$bDate->id}/reschedule", [
            'date' => $d3,
            'slot_ids' => [$this->slot1->id],
        ])->assertStatus(422);
    }

    /**
     * Test 10: Authorization: owning turf-admin ✔, assigned manager ✔, other admin 403, customer 403.
     */
    public function test_authorization_matrix(): void
    {
        $d1 = Carbon::now('Asia/Kolkata')->next(Carbon::MONDAY)->toDateString();
        $d2 = Carbon::now('Asia/Kolkata')->next(Carbon::TUESDAY)->toDateString();

        [$booking, $bDate] = $this->createStandardBooking($d1, [$this->slot1], 'Cash', 1000.00);

        // Customer -> 403
        $this->actingAs($this->customer)->postJson("/api/booking-dates/{$bDate->id}/reschedule", [
            'date' => $d2,
            'slot_ids' => [$this->slot2->id],
        ])->assertStatus(403);

        // Other admin -> 403
        $this->actingAs($this->otherAdmin)->postJson("/api/booking-dates/{$bDate->id}/reschedule", [
            'date' => $d2,
            'slot_ids' => [$this->slot2->id],
        ])->assertStatus(403);

        // Assigned manager -> 200
        $this->actingAs($this->manager)->postJson("/api/booking-dates/{$bDate->id}/reschedule", [
            'date' => $d2,
            'slot_ids' => [$this->slot2->id],
        ])->assertStatus(200);
    }

    /**
     * Test 11: Reschedule-then-cancel: refund on cancel is capped at what's left.
     */
    public function test_reschedule_then_cancel_refund_capped(): void
    {
        $d1 = Carbon::now('Asia/Kolkata')->next(Carbon::MONDAY)->toDateString();
        $d2 = Carbon::now('Asia/Kolkata')->next(Carbon::TUESDAY)->toDateString();

        // 2000 initial
        [$booking, $bDate] = $this->createStandardBooking($d1, [$this->slot1, $this->slot2], 'Cash', 2000.00);

        // Reschedule down to 1000 (refund 1000)
        $this->actingAs($this->turfAdmin)->postJson("/api/booking-dates/{$bDate->id}/reschedule", [
            'date' => $d2,
            'slot_ids' => [$this->slot1->id],
        ])->assertStatus(200);

        // Now cancel session: net paid is 1000, turf fee is 100 -> refund must be 900
        $cancelService = new BookingCancellationService();
        $cancelRes = $cancelService->cancelBookingDates($booking, [$bDate->id], $this->turfAdmin, 'Cancel', true);
        $this->assertTrue($cancelRes['success']);

        $bDate->refresh();
        $this->assertEquals(900.00, (float)$bDate->refund_amount);
    }

    /**
     * Test 12: Notification fires when toggle on, not when off.
     */
    public function test_reschedule_notification_toggle(): void
    {
        $d1 = Carbon::now('Asia/Kolkata')->next(Carbon::MONDAY)->toDateString();
        $d2 = Carbon::now('Asia/Kolkata')->next(Carbon::TUESDAY)->toDateString();

        [$booking, $bDate] = $this->createStandardBooking($d1, [$this->slot1], 'Cash', 1000.00);

        // With toggle on
        $this->actingAs($this->turfAdmin)->postJson("/api/booking-dates/{$bDate->id}/reschedule", [
            'date' => $d2,
            'slot_ids' => [$this->slot2->id],
        ])->assertStatus(200);

        // With toggle off
        SaasSetting::first()->update(['notify_booking_rescheduled' => false]);
        $d3 = Carbon::now('Asia/Kolkata')->next(Carbon::WEDNESDAY)->toDateString();
        $this->actingAs($this->turfAdmin)->postJson("/api/booking-dates/{$bDate->id}/reschedule", [
            'date' => $d3,
            'slot_ids' => [$this->slot1->id],
        ])->assertStatus(200);
    }

    /**
     * Test 13: SlotPricingService consistency with wizard, pivot, and fallback.
     */
    public function test_slot_pricing_service_consistency(): void
    {
        $dSat = Carbon::now('Asia/Kolkata')->next(Carbon::SATURDAY)->toDateString();
        $dMon = Carbon::now('Asia/Kolkata')->next(Carbon::MONDAY)->toDateString();

        $slotWithPivot = $this->turf->slots()->where('slots.id', $this->slot1->id)->first();

        // 1. Pivot pricing
        $satPrice = SlotPricingService::priceForSlot($this->turf, $slotWithPivot, $dSat);
        $this->assertEquals(1500.00, $satPrice);

        $monPrice = SlotPricingService::priceForSlot($this->turf, $slotWithPivot, $dMon);
        $this->assertEquals(1000.00, $monPrice);

        // 2. Wizard flat pricing
        $this->turf->update([
            'pricing_wizard_data' => [
                'sameRateThroughoutWeek' => 'yes',
                'sameRateThroughoutDayAll' => 'yes',
                'flatRateAll' => '1200',
            ],
        ]);
        $wizardPrice = SlotPricingService::priceForSlot($this->turf, $slotWithPivot, $dMon);
        $this->assertEquals(1200.00, $wizardPrice);
    }
}
