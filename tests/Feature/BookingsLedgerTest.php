<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\BookingDate;
use App\Models\BookingReschedule;
use App\Models\BookingSlot;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Role;
use App\Models\SaasSetting;
use App\Models\Slot;
use App\Models\SlotCategory;
use App\Models\StaffMember;
use App\Models\Turf;
use App\Models\TurfSetting;
use App\Models\User;
use App\Services\BookingCancellationService;
use App\Services\BookingRescheduleService;
use App\Services\BookingsLedgerService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\Concerns\FakesRazorpay;
use Tests\TestCase;

class BookingsLedgerTest extends TestCase
{
    use RefreshDatabase, FakesRazorpay;

    protected User $owner;
    protected User $manager;
    protected User $customer;
    protected Turf $turf;
    protected Slot $slot1;
    protected Slot $slot2;
    protected Slot $slotCheap;
    protected BookingsLedgerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->razorpayFeePercent = 0.0;

        Role::firstOrCreate(['name' => 'turf-admin'], ['display_name' => 'Turf Admin']);
        Role::firstOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);
        Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Customer']);

        SaasSetting::create([
            'is_gst_billing_active' => false,
            'platform_fee' => 10.00,
            'cancellation_fee_percentage' => 5.00,
            'commission_percentage' => 10.00,
            'min_slots_booking' => 1,
            'razorpay_key' => 'rzp_test_key',
            'razorpay_secret' => $this->razorpayTestSecret,
        ]);

        $this->owner = User::factory()->create([
            'name' => 'Audit Owner',
            'email' => 'owner@audit.com',
            'mobile' => '9999900001',
            'commission_wallet_balance' => 0.00,
        ]);
        $this->owner->assignRole('turf-admin');

        $this->manager = User::factory()->create([
            'name' => 'Audit Manager',
            'email' => 'manager@audit.com',
            'mobile' => '9999900002',
        ]);
        $this->manager->assignRole('manager');

        $this->customer = User::factory()->create([
            'name' => 'Rohan Player',
            'email' => 'rohan@example.com',
            'mobile' => '9876543210',
        ]);
        $this->customer->assignRole('customer');

        $location = Location::create([
            'user_id' => $this->owner->id,
            'name' => 'Main Complex',
            'address' => 'Plot 42, Andheri West',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
        ]);

        $this->turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Premier Arena',
            'type' => 'Football',
            'is_active' => true,
            'is_online_payment_active' => true,
            'is_pay_at_location_active' => true,
            'is_part_payment_active' => true,
            'part_payment_type' => 'percentage',
            'part_payment_value' => 50,
            'is_cancellation_active' => true,
            'cancellation_hours' => 1,
            'cancellation_fee' => 100.00,
            'is_reschedule_active' => true,
            'max_reschedules' => 3,
        ]);

        TurfSetting::create([
            'turf_id' => $this->turf->id,
            'company_name' => 'Premier Sports Arena',
            'is_gst_billing_active' => false,
        ]);

        StaffMember::create([
            'turf_admin_id' => $this->owner->id,
            'user_id' => $this->manager->id,
            'role' => 'manager',
        ]);
        $this->manager->assignedTurfs()->attach($this->turf->id, ['turf_admin_id' => $this->owner->id]);

        $cat = SlotCategory::create(['name' => 'Standard']);
        $this->slot1 = Slot::create([
            'slot_category_id' => $cat->id,
            'name' => '6-7 PM',
            'from_time' => '18:00:00',
            'to_time' => '19:00:00',
            'duration' => 60,
        ]);
        $this->slot2 = Slot::create([
            'slot_category_id' => $cat->id,
            'name' => '7-8 PM',
            'from_time' => '19:00:00',
            'to_time' => '20:00:00',
            'duration' => 60,
        ]);
        $this->slotCheap = Slot::create([
            'slot_category_id' => $cat->id,
            'name' => '10-11 AM',
            'from_time' => '10:00:00',
            'to_time' => '11:00:00',
            'duration' => 60,
        ]);

        foreach ([$this->slot1, $this->slot2] as $s) {
            $this->turf->slots()->attach($s->id, [
                'is_active' => true,
                'mon' => 1000, 'tue' => 1000, 'wed' => 1000, 'thu' => 1000, 'fri' => 1000,
                'sat' => 1000, 'sun' => 1000,
            ]);
        }
        $this->turf->slots()->attach($this->slotCheap->id, [
            'is_active' => true,
            'mon' => 600, 'tue' => 600, 'wed' => 600, 'thu' => 600, 'fri' => 600,
            'sat' => 600, 'sun' => 600,
        ]);

        $this->service = app(BookingsLedgerService::class);
        Carbon::setTestNow(Carbon::parse('2026-10-15 10:00:00', 'Asia/Kolkata'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- helpers

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

    private function bookOnline(array $dates, string $option = 'full', ?array $slotIds = null, ?User $user = null): Booking
    {
        $slotIds ??= [$this->slot1->id];
        $actingUser = $user ?? $this->customer;

        $preview = $this->actingAs($actingUser, 'sanctum')
            ->postJson("/api/turfs/{$this->turf->id}/bookings/preview", $this->payload($dates, $slotIds, 'App', $option))
            ->assertStatus(200)->json();
        $payable = (float) ($preview['payable_now'] ?? $preview['pricing']['payable_now'] ?? 0);

        $response = $this->actingAs($actingUser, 'sanctum')->postJson(
            "/api/turfs/{$this->turf->id}/bookings",
            $this->payload($dates, $slotIds, 'App', $option) + $this->razorpayPayment($payable)
        )->assertStatus(200);

        return Booking::find($response->json('booking.id'));
    }

    private function bookPayAtVenue(array $dates, ?array $slotIds = null, ?User $user = null): Booking
    {
        $slotIds ??= [$this->slot1->id];
        $actingUser = $user ?? $this->customer;

        $response = $this->actingAs($actingUser, 'sanctum')
            ->postJson("/api/turfs/{$this->turf->id}/bookings", $this->payload($dates, $slotIds, 'offline', 'full'))
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

    // ---------------------------------------------------------------- tests

    /**
     * 1. Channels: online-only, venue collected, mixed, and venue uncollected.
     * Channel badges and channel filtering behavior.
     */
    public function test_channels_and_channel_filters(): void
    {
        $date = '2026-10-20';

        // 1. Online-only booking
        $bOnline = $this->bookOnline([$date], 'full', [$this->slot1->id]);

        // 2. Pay-at-venue with cash collected
        $bVenuePaid = $this->bookPayAtVenue([$date], [$this->slot2->id]);
        $this->collectCash($bVenuePaid);

        // 3. Part payment: online deposit + cash
        $bMixed = $this->bookOnline([$date], 'part', [$this->slotCheap->id]);
        $this->collectCash($bMixed);

        // 4. Pay-at-venue not yet collected
        $bUncollected = $this->bookPayAtVenue(['2026-10-21'], [$this->slot1->id]);

        // Check channel resolution
        $this->assertEquals('online', $this->service->resolveChannel($bOnline->fresh(['payments'])));
        $this->assertEquals('venue', $this->service->resolveChannel($bVenuePaid->fresh(['payments'])));
        $this->assertEquals('mixed', $this->service->resolveChannel($bMixed->fresh(['payments'])));
        $this->assertEquals('venue', $this->service->resolveChannel($bUncollected->fresh(['payments'])));

        // Channel filter: By App (shows online and mixed)
        $onlineQueryIds = $this->service->query([$this->turf->id], ['channel' => 'online'])->pluck('id')->all();
        $this->assertContains($bOnline->id, $onlineQueryIds);
        $this->assertContains($bMixed->id, $onlineQueryIds);
        $this->assertNotContains($bVenuePaid->id, $onlineQueryIds);
        $this->assertNotContains($bUncollected->id, $onlineQueryIds);

        // Channel filter: At Location (shows venue, mixed, and uncollected)
        $venueQueryIds = $this->service->query([$this->turf->id], ['channel' => 'venue'])->pluck('id')->all();
        $this->assertContains($bVenuePaid->id, $venueQueryIds);
        $this->assertContains($bMixed->id, $venueQueryIds);
        $this->assertContains($bUncollected->id, $venueQueryIds);
        $this->assertNotContains($bOnline->id, $venueQueryIds);

        // Channel filter: All (shows all 4)
        $allQueryIds = $this->service->query([$this->turf->id], ['channel' => 'all'])->pluck('id')->all();
        $this->assertContains($bOnline->id, $allQueryIds);
        $this->assertContains($bVenuePaid->id, $allQueryIds);
        $this->assertContains($bMixed->id, $allQueryIds);
        $this->assertContains($bUncollected->id, $allQueryIds);
    }

    /**
     * 2. Paid split: part-payment row shows App ₹(deposit) · Venue ₹(cash),
     * and venue split card shows the Cash amount.
     */
    public function test_paid_split(): void
    {
        $date = '2026-10-20';
        $bMixed = $this->bookOnline([$date], 'part', [$this->slot1->id]);
        $initialOnlinePaid = (float) $bMixed->payments()->where('status', 'Success')->sum('amount');
        $this->assertGreaterThan(0, $initialOnlinePaid);

        $due = (float) $bMixed->fresh()->bookingDates()->first()->balance_amount;
        $this->collectCash($bMixed, $due);

        $metrics = $this->service->rowMetrics($bMixed->fresh(['payments', 'bookingDates', 'bookingCancellations']));
        $this->assertEquals($initialOnlinePaid, $metrics['online_paid']);
        $this->assertEquals($due, $metrics['venue_paid']);
        $this->assertEquals($due, $metrics['venue_split']['Cash']);
        $this->assertEquals('mixed', $metrics['channel']);

        $summary = $this->service->summary([$this->turf->id], ['channel' => 'all']);
        $this->assertEquals($due, $summary['venue_split']['Cash']);
    }

    /**
     * 3. Refunds:
     * - Online cancellation shows Paid App, Refunded, and Net = paid - refund.
     * - Cash refund of online payment still shows refund even with payments.refunded_amount = 0.
     * - Reschedule refund shows its refund.
     * - Both reschedule and cancellation refunds sum together.
     * - Pending Resolution shows Refund Pending and is not deducted from Net.
     */
    public function test_refunds_and_resolutions(): void
    {
        $cancellationService = app(BookingCancellationService::class);

        // 3a: Standard online cancellation
        $b1 = $this->bookOnline(['2026-10-20'], 'full', [$this->slot1->id]);
        $b1Date = $b1->bookingDates()->first();
        $cancelRes1 = $cancellationService->cancelBookingDates($b1, [$b1Date->id], $this->customer, 'Player cancel');
        $this->assertTrue($cancelRes1['success']);
        $cancellation1 = $cancelRes1['cancellations']->first();
        $resolve1 = $cancellationService->resolveRefund($cancellation1, 'standard_policy', 'online_gateway', $this->owner);
        $this->assertTrue($resolve1['success']);

        $m1 = $this->service->rowMetrics($b1->fresh(['payments', 'bookingDates', 'bookingCancellations', 'reschedules']));
        $this->assertEquals((float) $cancellation1->fresh()->refund_amount, $m1['refunded']);
        $this->assertEquals(round($m1['online_paid'] - $m1['refunded'], 2), $m1['net_paid']);

        // 3b: Cash refund of online payment (offline channel resolution)
        $b2 = $this->bookOnline(['2026-10-21'], 'full', [$this->slot1->id]);
        $b2Date = $b2->bookingDates()->first();
        $cancelRes2 = $cancellationService->cancelBookingDates($b2, [$b2Date->id], $this->customer, 'Cash refund requested');
        $cancellation2 = $cancelRes2['cancellations']->first();
        $resolve2 = $cancellationService->resolveRefund(
            $cancellation2,
            'standard_policy',
            'offline',
            $this->owner,
            null,
            'Cash given at counter'
        );
        $this->assertTrue($resolve2['success']);

        // Verify payments.refunded_amount is 0 for offline refund of online payment
        $payment2 = Payment::where('booking_id', $b2->id)->first();
        $this->assertEquals(0.00, (float) $payment2->refunded_amount);

        // But ledger rowMetrics still reports the refund accurately
        $m2 = $this->service->rowMetrics($b2->fresh(['payments', 'bookingDates', 'bookingCancellations', 'reschedules']));
        $this->assertEquals((float) $cancellation2->fresh()->refund_amount, $m2['refunded']);
        $this->assertEquals(round($m2['online_paid'] - $m2['refunded'], 2), $m2['net_paid']);

        // 3c: Reschedule to a cheaper slot (refund)
        $b3 = $this->bookOnline(['2026-10-22'], 'full', [$this->slot1->id]);
        $b3Date = $b3->bookingDates()->first();
        $rescheduleRes = BookingReschedule::create([
            'booking_id' => $b3->id,
            'booking_date_id' => $b3Date->id,
            'from_date' => '2026-10-22',
            'to_date' => '2026-10-22',
            'from_slot_ids' => [$this->slot1->id],
            'to_slot_ids' => [$this->slotCheap->id],
            'old_amount' => 1010.00,
            'new_amount' => 610.00,
            'price_difference' => -400.00,
            'settlement_type' => 'refund',
            'refund_amount' => 400.00,
            'refund_channel' => 'online_gateway',
            'reason' => 'Changed to cheaper morning slot',
            'rescheduled_by_user_id' => $this->owner->id,
        ]);
        $m3 = $this->service->rowMetrics($b3->fresh(['payments', 'bookingDates.reschedules', 'reschedules', 'bookingCancellations']));
        $this->assertEquals(400.00, $m3['refunded']);
        $this->assertEquals(round($m3['online_paid'] - 400.00, 2), $m3['net_paid']);

        // 3d: Booking with BOTH a reschedule refund and a later cancellation refund
        $cancellation3 = BookingCancellation::create([
            'booking_id' => $b3->id,
            'booking_date_id' => $b3Date->id,
            'cancelled_by_user_id' => $this->customer->id,
            'canceller_role' => 'customer',
            'cancellation_scope' => 'full_booking',
            'reason' => 'Cancelled rescheduled session',
            'gross_cancelled_amount' => 610.00,
            'refund_amount' => 500.00,
            'refund_status' => 'Refunded',
            'total_cancellation_fee' => 110.00,
        ]);
        $m3Both = $this->service->rowMetrics($b3->fresh(['payments', 'bookingDates.reschedules', 'reschedules', 'bookingCancellations']));
        $this->assertEquals(900.00, $m3Both['refunded']); // 400 reschedule + 500 cancellation

        // 3e: Cancellation still in 'Pending Resolution'
        $b4 = $this->bookOnline(['2026-10-23'], 'full', [$this->slot1->id]);
        $b4Date = $b4->bookingDates()->first();
        $cancelRes4 = $cancellationService->cancelBookingDates($b4, [$b4Date->id], $this->customer, 'Under review');
        $cancellation4 = $cancelRes4['cancellations']->first();
        $this->assertEquals('Pending Resolution', $cancellation4->refund_status);

        $m4 = $this->service->rowMetrics($b4->fresh(['payments', 'bookingDates', 'bookingCancellations', 'reschedules']));
        $this->assertEquals(0.00, $m4['refunded']);
        $this->assertGreaterThan(0.00, $m4['refund_pending']);
        $this->assertEquals($m4['gross_paid'], $m4['net_paid']);
    }

    /**
     * 4. Cancelled value and unpaid due:
     * - Fully cancelled booking: Value = original total_amount, Due = 0.
     * - Unpaid pay-at-venue booking: Due = total_amount exactly (including platform fee).
     * - Two-session booking with one cancelled: Due = remaining session amount.
     */
    public function test_cancelled_value_and_unpaid_due(): void
    {
        $cancellationService = app(BookingCancellationService::class);
        $date = '2026-10-20';

        // Fully cancelled booking
        $b1 = $this->bookOnline([$date], 'full', [$this->slot1->id]);
        $originalValue = (float) $b1->total_amount;
        $b1Date = $b1->bookingDates()->first();
        $cancellationService->cancelBookingDates($b1, [$b1Date->id], $this->customer, 'Cancel full');
        $b1->update(['status' => 'Cancelled']);

        $m1 = $this->service->rowMetrics($b1->fresh(['payments', 'bookingDates', 'bookingCancellations', 'reschedules']));
        $this->assertEquals($originalValue, $m1['booking_value']);
        $this->assertEquals(0.00, $m1['balance_due']);

        // Unpaid pay-at-venue booking: Due = total_amount exactly (including ₹10 platform fee)
        $b2 = $this->bookPayAtVenue([$date], [$this->slot1->id]);
        $m2 = $this->service->rowMetrics($b2->fresh(['payments', 'bookingDates', 'bookingCancellations', 'reschedules']));
        $this->assertEquals((float) $b2->total_amount, $m2['balance_due']);
        $this->assertEquals(1010.00, $m2['balance_due']); // 1000 slot + 10 platform fee

        // Two-session booking with one session cancelled and no payment
        $b3 = $this->bookPayAtVenue(['2026-10-22', '2026-10-23'], [$this->slot1->id]);
        $dates3 = $b3->bookingDates()->get();
        $firstDate = $dates3->first();
        $secondDate = $dates3->last();

        $cancellationService->cancelBookingDates($b3, [$firstDate->id], $this->owner, 'Rain out', true);
        $firstDate->refresh();
        $this->assertEquals('Cancelled', $firstDate->status);

        $m3 = $this->service->rowMetrics($b3->fresh(['payments', 'bookingDates', 'bookingCancellations', 'reschedules']));
        // Due must equal only the remaining active date amount
        $this->assertEquals((float) $secondDate->amount, $m3['balance_due']);
    }

    /**
     * 5. Summary calculations match payment sums and rules.
     */
    public function test_summary_calculations_match_payments(): void
    {
        $date = '2026-10-20';

        // Create 1 online, 1 venue, 1 mixed
        $b1 = $this->bookOnline([$date], 'full', [$this->slot1->id]);
        $b2 = $this->bookPayAtVenue([$date], [$this->slot2->id]);
        $this->collectCash($b2);
        $b3 = $this->bookOnline([$date], 'part', [$this->slotCheap->id]);
        $this->collectCash($b3);

        $summary = $this->service->summary([$this->turf->id], [
            'start' => '2026-10-01',
            'end' => '2026-10-31',
            'date_basis' => 'session',
        ]);

        $expectedOnline = (float) Payment::where('status', 'Success')->where('payment_method', 'App')->sum('amount');
        $expectedCash = (float) Payment::where('status', 'Success')->where('payment_method', 'Cash')->sum('amount');
        $expectedNet = round(($expectedOnline + $expectedCash) - $summary['refunded'], 2);

        $this->assertEquals(3, $summary['count']);
        $this->assertEquals($expectedOnline, $summary['online_collected']);
        $this->assertEquals($expectedCash, $summary['venue_collected']);
        $this->assertEquals($expectedCash, $summary['venue_split']['Cash']);
        $this->assertEquals($expectedNet, $summary['net_collected']);
        $this->assertEquals(1, $summary['by_channel_count']['online']);
        $this->assertEquals(1, $summary['by_channel_count']['venue']);
        $this->assertEquals(1, $summary['by_channel_count']['mixed']);
    }

    /**
     * 6. Date basis:
     * A booking made on 30 Sep for a 2 Oct session:
     * with session-date basis it appears in October only;
     * with booking-date basis, in September only.
     */
    public function test_date_basis_filtering(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 14:00:00', 'Asia/Kolkata'));
        $booking = $this->bookOnline(['2026-10-02'], 'full', [$this->slot1->id]);

        Carbon::setTestNow(Carbon::parse('2026-10-15 10:00:00', 'Asia/Kolkata'));

        // Session date basis in October
        $octSessionIds = $this->service->query([$this->turf->id], [
            'date_basis' => 'session',
            'start' => '2026-10-01',
            'end' => '2026-10-31',
        ])->pluck('id')->all();
        $this->assertContains($booking->id, $octSessionIds);

        // Session date basis in September (must NOT appear)
        $sepSessionIds = $this->service->query([$this->turf->id], [
            'date_basis' => 'session',
            'start' => '2026-09-01',
            'end' => '2026-09-30',
        ])->pluck('id')->all();
        $this->assertNotContains($booking->id, $sepSessionIds);

        // Booked date basis in September (must appear)
        $sepBookedIds = $this->service->query([$this->turf->id], [
            'date_basis' => 'booked',
            'start' => '2026-09-01',
            'end' => '2026-09-30',
        ])->pluck('id')->all();
        $this->assertContains($booking->id, $sepBookedIds);

        // Booked date basis in October (must NOT appear)
        $octBookedIds = $this->service->query([$this->turf->id], [
            'date_basis' => 'booked',
            'start' => '2026-10-01',
            'end' => '2026-10-31',
        ])->pluck('id')->all();
        $this->assertNotContains($booking->id, $octBookedIds);
    }

    /**
     * 7. Paging: 30 bookings -> Showing 25 of 30 -> load more -> all 30 and End of ledger.
     */
    public function test_paging_and_load_more(): void
    {
        $this->actingAs($this->owner);
        session(['active_turf_id' => $this->turf->id]);

        // Bulk-insert 30 bookings directly
        for ($i = 1; $i <= 30; $i++) {
            $b = Booking::create([
                'turf_id' => $this->turf->id,
                'user_id' => $this->customer->id,
                'booking_number' => "BK-PAGE-{$i}",
                'booking_reference' => "REF-PAGE-{$i}",
                'date_of_booking' => '2026-10-15',
                'total_amount' => 1000.00,
                'status' => 'Confirmed',
                'payment_status' => 'Paid',
                'booking_source' => 'app',
                'created_at' => Carbon::parse('2026-10-15 10:00:00')->addMinutes($i),
            ]);
            BookingDate::create([
                'booking_id' => $b->id,
                'booking_date' => '2026-10-15',
                'amount' => 1000.00,
                'status' => 'Confirmed',
                'payment_status' => 'Paid',
            ]);
        }

        Volt::test('turf.reports-manager')
            ->call('switchTab', 'bookings')
            ->call('setPeriod', 'all')
            ->assertSee('Showing 25 of 30')
            ->assertSee('Load more')
            ->assertDontSee('End of ledger · 30 bookings')
            ->call('loadMoreBookings')
            ->assertSee('End of ledger · 30 bookings');
    }

    /**
     * 8. Search by reference, player mobile, and player name.
     */
    public function test_search_by_reference_mobile_and_name(): void
    {
        $user1 = User::factory()->create(['name' => 'Alice Wonder', 'mobile' => '9111122222']);
        $user2 = User::factory()->create(['name' => 'Bob Builder', 'mobile' => '9333344444']);

        $b1 = Booking::create([
            'turf_id' => $this->turf->id,
            'user_id' => $user1->id,
            'booking_number' => 'BK-REF-101',
            'booking_reference' => 'BK-REF-101',
            'date_of_booking' => '2026-10-15',
            'total_amount' => 1000.00,
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
        ]);
        BookingDate::create([
            'booking_id' => $b1->id,
            'booking_date' => '2026-10-15',
            'amount' => 1000.00,
            'status' => 'Confirmed',
        ]);

        $b2 = Booking::create([
            'turf_id' => $this->turf->id,
            'user_id' => $user2->id,
            'booking_number' => 'BK-REF-202',
            'booking_reference' => 'BK-REF-202',
            'date_of_booking' => '2026-10-15',
            'total_amount' => 1000.00,
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
        ]);
        BookingDate::create([
            'booking_id' => $b2->id,
            'booking_date' => '2026-10-15',
            'amount' => 1000.00,
            'status' => 'Confirmed',
        ]);

        // Search by reference
        $byRef = $this->service->query([$this->turf->id], ['search' => '101'])->pluck('id')->all();
        $this->assertEquals([$b1->id], $byRef);

        // Search by player mobile
        $byMobile = $this->service->query([$this->turf->id], ['search' => '93333'])->pluck('id')->all();
        $this->assertEquals([$b2->id], $byMobile);

        // Search by player name
        $byName = $this->service->query([$this->turf->id], ['search' => 'Alice'])->pluck('id')->all();
        $this->assertEquals([$b1->id], $byName);
    }

    /**
     * 9. Booked by attribution:
     * - Player booking through API gets booking_source = 'app' and their user id.
     * - Staff on-behalf booking gets 'staff' and admin's user id.
     * - The ledger shows "Player (app)" or "Staff: {name}" or "—".
     */
    public function test_booked_by_source_and_attribution(): void
    {
        // 9a: Player booking
        $playerBooking = $this->bookOnline(['2026-10-20'], 'full', [$this->slot1->id], $this->customer);
        $this->assertEquals('app', $playerBooking->booking_source);
        $this->assertEquals($this->customer->id, $playerBooking->booked_by_user_id);
        $mPlayer = $this->service->rowMetrics($playerBooking->fresh(['bookedBy', 'payments', 'bookingDates', 'bookingCancellations']));
        $this->assertEquals('Player (app)', $mPlayer['booked_by_label']);

        // 9b: Staff on-behalf booking
        $response = $this->actingAs($this->owner, 'sanctum')->postJson("/api/turfs/{$this->turf->id}/bookings", [
            'slot_ids' => [$this->slot2->id],
            'booking_dates' => ['2026-10-20'],
            'booking_type' => 'day',
            'payment_method' => 'Cash',
            'customer_id' => $this->customer->id,
            'amount_received' => 1010,
        ])->assertStatus(200);

        $staffBooking = Booking::find($response->json('booking.id'));
        $this->assertEquals('staff', $staffBooking->booking_source);
        $this->assertEquals($this->owner->id, $staffBooking->booked_by_user_id);
        $mStaff = $this->service->rowMetrics($staffBooking->fresh(['bookedBy', 'payments', 'bookingDates', 'bookingCancellations']));
        $this->assertEquals('Staff: ' . $this->owner->name, $mStaff['booked_by_label']);

        // 9c: Legacy booking with null booking_source
        $legacyBooking = Booking::create([
            'turf_id' => $this->turf->id,
            'user_id' => $this->customer->id,
            'booking_number' => 'BK-LEGACY-1',
            'date_of_booking' => '2026-10-15',
            'total_amount' => 1000.00,
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
            'booking_source' => null,
            'booked_by_user_id' => null,
        ]);
        $mLegacy = $this->service->rowMetrics($legacyBooking->fresh(['bookedBy', 'payments', 'bookingDates', 'bookingCancellations']));
        $this->assertEquals('—', $mLegacy['booked_by_label']);
    }

    /**
     * 10. Discount and coupon codes displayed.
     */
    public function test_discount_and_coupon_display(): void
    {
        $coupon = Coupon::create([
            'turf_id' => $this->turf->id,
            'code' => 'TEST10',
            'description' => 'Test discount',
            'discount_type' => 'fixed',
            'discount_value' => 100,
            'is_active' => true,
        ]);

        $booking = Booking::create([
            'turf_id' => $this->turf->id,
            'user_id' => $this->customer->id,
            'booking_number' => 'BK-COUPON-1',
            'date_of_booking' => '2026-10-15',
            'coupon_discount' => 100.00,
            'additional_discount' => 20.00,
            'total_amount' => 880.00,
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
        ]);
        $bDate = BookingDate::create([
            'booking_id' => $booking->id,
            'booking_date' => '2026-10-15',
            'amount' => 880.00,
            'status' => 'Confirmed',
        ]);

        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'user_id' => $this->customer->id,
            'booking_date_id' => $bDate->id,
            'discount_applied' => 100.00,
            'used_at' => Carbon::now(),
        ]);

        $metrics = $this->service->rowMetrics($booking->fresh([
            'bookingDates.couponUsage.coupon',
            'payments',
            'bookingCancellations',
            'reschedules',
        ]));

        $this->assertEquals(120.00, $metrics['discount']);
        $this->assertContains('TEST10', $metrics['coupon_codes']);
    }

    /**
     * 11. CSV export matches exact Part 6 headers, filters applied, and totals row.
     */
    public function test_csv_export_format_and_totals(): void
    {
        $bOnline = $this->bookOnline(['2026-10-20'], 'full', [$this->slot1->id]);
        $bVenue = $this->bookPayAtVenue(['2026-10-20'], [$this->slot2->id]);
        $this->collectCash($bVenue);

        session(['active_turf_id' => $this->turf->id]);

        // Channel filter: online export excludes venue booking
        $response = $this->actingAs($this->owner)->get(route('reports.export-bookings', [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'date_basis' => 'session',
            'channel' => 'online',
        ]));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        // Exact Part 6 Header pattern
        $this->assertStringContainsString('# Bookings Ledger — Premier Arena — 2026-10-01 to 2026-10-31 — by session date — channel App', $content);

        // Column header line verified via CSV parsing
        $lines = explode("\n", trim($content));
        $headerLine = str_getcsv($lines[1]);
        $this->assertEquals([
            'Booking', 'Booked On', 'Player', 'Mobile', 'Turf', 'Sessions', 'Hours', 'Channel',
            'Booking Value (INR)', 'Paid App (INR)', 'Paid Cash (INR)', 'Paid UPI (INR)', 'Paid Other (INR)',
            'Refunded (INR)', 'Net Paid (INR)', 'Due (INR)', 'Discount (INR)', 'Coupon', 'Booked By', 'Status', 'Payment Status'
        ], $headerLine);

        // Online booking is present, venue-only is excluded
        $this->assertStringContainsString($bOnline->booking_reference ?? $bOnline->booking_number, $content);
        $this->assertStringNotContainsString($bVenue->booking_reference ?? $bVenue->booking_number, $content);

        // Totals row at the bottom
        $this->assertStringContainsString('Totals,,,,,,,,', $content);
    }

    /**
     * 12. No N+1 queries per row:
     * Rendering 50 rows must execute no more queries than rendering 10 rows.
     */
    public function test_no_n_plus_one_queries_per_row(): void
    {
        // Create 50 bookings with full relations
        for ($i = 1; $i <= 50; $i++) {
            $b = Booking::create([
                'turf_id' => $this->turf->id,
                'user_id' => $this->customer->id,
                'booking_number' => "BK-PERF-{$i}",
                'date_of_booking' => '2026-10-15',
                'total_amount' => 1000.00,
                'status' => 'Confirmed',
                'payment_status' => 'Paid',
                'booking_source' => 'app',
            ]);
            $bd = BookingDate::create([
                'booking_id' => $b->id,
                'booking_date' => '2026-10-15',
                'amount' => 1000.00,
                'status' => 'Confirmed',
            ]);
            BookingSlot::create([
                'booking_date_id' => $bd->id,
                'slot_id' => $this->slot1->id,
                'price' => 1000.00,
                'status' => 'confirmed',
            ]);
            Payment::create([
                'booking_id' => $b->id,
                'booking_date_id' => $bd->id,
                'amount' => 1000.00,
                'payment_method' => 'App',
                'status' => 'Success',
            ]);
        }

        DB::enableQueryLog();

        // Run for 10 rows
        DB::flushQueryLog();
        $batch10 = $this->service->query([$this->turf->id])->take(10)->get();
        foreach ($batch10 as $b) {
            $this->service->rowMetrics($b);
            $this->service->formatSessions($b);
        }
        $count10 = count(DB::getQueryLog());

        // Run for 50 rows
        DB::flushQueryLog();
        $batch50 = $this->service->query([$this->turf->id])->take(50)->get();
        foreach ($batch50 as $b) {
            $this->service->rowMetrics($b);
            $this->service->formatSessions($b);
        }
        $count50 = count(DB::getQueryLog());

        $this->assertEquals(
            $count10,
            $count50,
            "Rendering 50 rows must execute exactly the same number of queries as 10 rows (no N+1 per row). Got {$count10} vs {$count50}."
        );
    }

    /**
     * 13. Manager permission:
     * A manager assigned to the turf sees the same ledger rows as the owner.
     */
    public function test_manager_scope_matches_owner(): void
    {
        $b1 = $this->bookOnline(['2026-10-15'], 'full', [$this->slot1->id]);
        $b2 = $this->bookPayAtVenue(['2026-10-15'], [$this->slot2->id]);

        $ownerTurfs = Turf::manageable($this->owner)->pluck('id')->toArray();
        $managerTurfs = Turf::manageable($this->manager)->pluck('id')->toArray();

        $this->assertEquals($ownerTurfs, $managerTurfs);

        $ownerRows = $this->service->query($ownerTurfs)->pluck('id')->all();
        $managerRows = $this->service->query($managerTurfs)->pluck('id')->all();

        $this->assertEquals($ownerRows, $managerRows);

        // Also test Livewire component as manager
        $this->actingAs($this->manager);
        session(['active_turf_id' => $this->turf->id]);

        Volt::test('turf.reports-manager')
            ->call('switchTab', 'bookings')
            ->call('setPeriod', 'all')
            ->assertSee($b1->booking_reference ?? $b1->booking_number)
            ->assertSee($b2->booking_reference ?? $b2->booking_number);
    }

    /**
     * 14. Phone layout markers: ledger-card and ledger-table are present.
     */
    public function test_phone_layout_markers_present(): void
    {
        $this->bookOnline(['2026-10-20'], 'full', [$this->slot1->id]);

        $this->actingAs($this->owner);
        session(['active_turf_id' => $this->turf->id]);

        Volt::test('turf.reports-manager')
            ->call('switchTab', 'bookings')
            ->assertSee('ledger-table')
            ->assertSee('ledger-card');
    }
}
