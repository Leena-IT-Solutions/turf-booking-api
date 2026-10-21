<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\BookingDate;
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
use App\Services\BookingsLedgerService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\Concerns\FakesRazorpay;
use Tests\TestCase;

class BookingsLedgerSummaryCardsTest extends TestCase
{
    use RefreshDatabase, FakesRazorpay;

    protected User $owner;
    protected User $customer;
    protected Turf $turf;
    protected Slot $slot1;
    protected Slot $slot2;
    protected Slot $slotNoDuration;
    protected Coupon $coupon;
    protected BookingsLedgerService $service;

    // Fixture bookings A through H
    protected Booking $bookingA;
    protected Booking $bookingB;
    protected Booking $bookingC;
    protected Booking $bookingD;
    protected Booking $bookingE;
    protected Booking $bookingF;
    protected Booking $bookingG;
    protected Booking $bookingH;

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
            'name' => 'Turf Admin Owner',
            'email' => 'admin@turf.com',
            'mobile' => '9888800001',
        ]);
        $this->owner->assignRole('turf-admin');

        $this->customer = User::factory()->create([
            'name' => 'Player One',
            'email' => 'player@turf.com',
            'mobile' => '9777700002',
        ]);
        $this->customer->assignRole('customer');

        $location = Location::create([
            'user_id' => $this->owner->id,
            'name' => 'Central Ground',
            'address' => 'Andheri West',
            'city' => 'Mumbai',
        ]);

        $this->turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Central Turf',
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
            'company_name' => 'Central Turf Ltd',
            'is_gst_billing_active' => false,
        ]);

        $cat = SlotCategory::create(['name' => 'Prime']);

        // Slot 1: 60 mins (duration 60), 1000
        $this->slot1 = Slot::create([
            'slot_category_id' => $cat->id,
            'name' => '6-7 PM',
            'from_time' => '18:00:00',
            'to_time' => '19:00:00',
            'duration' => 60,
        ]);

        // Slot 2: 60 mins (duration 60), 1000
        $this->slot2 = Slot::create([
            'slot_category_id' => $cat->id,
            'name' => '7-8 PM',
            'from_time' => '19:00:00',
            'to_time' => '20:00:00',
            'duration' => 60,
        ]);

        // Slot 3: duration 0 (triggers from_time/to_time fallback: 10:00–11:00 = 60 mins), 500
        $this->slotNoDuration = Slot::create([
            'slot_category_id' => $cat->id,
            'name' => '10-11 AM',
            'from_time' => '10:00:00',
            'to_time' => '11:00:00',
            'duration' => 0,
        ]);

        foreach ([$this->slot1, $this->slot2] as $s) {
            $this->turf->slots()->attach($s->id, [
                'is_active' => true,
                'mon' => 1000, 'tue' => 1000, 'wed' => 1000, 'thu' => 1000, 'fri' => 1000, 'sat' => 1000, 'sun' => 1000,
            ]);
        }
        $this->turf->slots()->attach($this->slotNoDuration->id, [
            'is_active' => true,
            'mon' => 500, 'tue' => 500, 'wed' => 500, 'thu' => 500, 'fri' => 500, 'sat' => 500, 'sun' => 500,
        ]);

        $this->coupon = Coupon::create([
            'turf_id' => $this->turf->id,
            'code' => 'SAVE50',
            'description' => 'Save 50',
            'discount_type' => 'fixed',
            'discount_value' => 50,
            'is_active' => true,
        ]);

        $this->service = app(BookingsLedgerService::class);
        Carbon::setTestNow(Carbon::parse('2026-10-15 10:00:00', 'Asia/Kolkata'));

        $this->buildFixtureAH();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Build the standard A-H fixture through the real API / domain services.
     */
    private function buildFixtureAH(): void
    {
        $cancellationService = app(BookingCancellationService::class);

        // A: Online full payment, active
        $this->bookingA = $this->bookOnline(['2026-10-15'], 'full', [$this->slot1->id]);

        // B: Part payment (online deposit + cash rest), active
        $this->bookingB = $this->bookOnline(['2026-10-16'], 'part', [$this->slot1->id]);
        $bDue = (float) $this->bookingB->fresh()->bookingDates()->first()->balance_amount;
        $this->collectCash($this->bookingB, $bDue);

        // C: Pay at venue, unpaid, active (due)
        $this->bookingC = $this->bookPayAtVenue(['2026-10-17'], [$this->slot1->id]);

        // D: Pay at venue, cash collected, then cancelled with cash refund
        $this->bookingD = $this->bookPayAtVenue(['2026-10-18'], [$this->slot1->id]);
        $this->collectCash($this->bookingD);
        $dDate = $this->bookingD->bookingDates()->first();
        $cancelD = $cancellationService->cancelBookingDates($this->bookingD, [$dDate->id], $this->customer, 'Cancelled D');
        $cancellationD = $cancelD['cancellations']->first();
        $cancellationService->resolveRefund($cancellationD, 'standard_policy', 'offline', $this->owner, null, 'Cash refund at desk');
        $this->bookingD->update(['status' => 'Cancelled']);

        // E: Online, cancelled by player (gateway refund)
        $this->bookingE = $this->bookOnline(['2026-10-19'], 'full', [$this->slot1->id]);
        $eDate = $this->bookingE->bookingDates()->first();
        $cancelE = $cancellationService->cancelBookingDates($this->bookingE, [$eDate->id], $this->customer, 'Cancelled E');
        $cancellationE = $cancelE['cancellations']->first();
        $cancellationService->resolveRefund($cancellationE, 'standard_policy', 'online_gateway', $this->owner);
        $this->bookingE->update(['status' => 'Cancelled']);

        // F: Two sessions, pay at venue unpaid, one session cancelled (partly cancelled)
        $this->bookingF = $this->bookPayAtVenue(['2026-10-20', '2026-10-21'], [$this->slot1->id]);
        $fDates = $this->bookingF->bookingDates()->orderBy('booking_date')->get();
        $fFirstDate = $fDates->first();
        $cancellationService->cancelBookingDates($this->bookingF, [$fFirstDate->id], $this->owner, 'Cancelled session 1', true);
        $this->bookingF->update(['status' => 'Partially Cancelled']);

        // G: A booking with a coupon and duration-null slot
        $bDateG = '2026-10-22';
        $prevG = $this->actingAs($this->customer, 'sanctum')->postJson("/api/turfs/{$this->turf->id}/bookings/preview", [
            'slot_ids' => [$this->slotNoDuration->id],
            'booking_dates' => [$bDateG],
            'booking_type' => 'day',
            'payment_method' => 'App',
            'payment_option' => 'full',
            'coupon_code' => 'SAVE50',
        ])->assertStatus(200)->json();
        $payableG = (float) ($prevG['payable_now'] ?? $prevG['pricing']['payable_now'] ?? 0);

        $resG = $this->actingAs($this->customer, 'sanctum')->postJson("/api/turfs/{$this->turf->id}/bookings", [
            'slot_ids' => [$this->slotNoDuration->id],
            'booking_dates' => [$bDateG],
            'booking_type' => 'day',
            'payment_method' => 'App',
            'payment_option' => 'full',
            'coupon_code' => 'SAVE50',
        ] + $this->razorpayPayment($payableG))->assertStatus(200);
        $this->bookingG = Booking::find($resG->json('booking.id'));

        // H: Staff on-behalf booking with Cash
        $resH = $this->actingAs($this->owner, 'sanctum')->postJson("/api/turfs/{$this->turf->id}/bookings", [
            'slot_ids' => [$this->slot2->id],
            'booking_dates' => ['2026-10-23'],
            'booking_type' => 'day',
            'payment_method' => 'Cash',
            'customer_id' => $this->customer->id,
            'amount_received' => 1010,
        ])->assertStatus(200);
        $this->bookingH = Booking::find($resH->json('booking.id'));
    }

    private function bookOnline(array $dates, string $option = 'full', ?array $slotIds = null): Booking
    {
        $slotIds ??= [$this->slot1->id];
        $preview = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/turfs/{$this->turf->id}/bookings/preview", [
                'slot_ids' => $slotIds,
                'booking_dates' => $dates,
                'booking_type' => count($dates) > 1 ? 'long' : 'day',
                'payment_method' => 'App',
                'payment_option' => $option,
            ])
            ->assertStatus(200)->json();
        $payable = (float) ($preview['payable_now'] ?? $preview['pricing']['payable_now'] ?? 0);

        $response = $this->actingAs($this->customer, 'sanctum')->postJson(
            "/api/turfs/{$this->turf->id}/bookings",
            [
                'slot_ids' => $slotIds,
                'booking_dates' => $dates,
                'booking_type' => count($dates) > 1 ? 'long' : 'day',
                'payment_method' => 'App',
                'payment_option' => $option,
            ] + $this->razorpayPayment($payable)
        )->assertStatus(200);

        return Booking::find($response->json('booking.id'));
    }

    private function bookPayAtVenue(array $dates, ?array $slotIds = null): Booking
    {
        $slotIds ??= [$this->slot1->id];
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/turfs/{$this->turf->id}/bookings", [
                'slot_ids' => $slotIds,
                'booking_dates' => $dates,
                'booking_type' => count($dates) > 1 ? 'long' : 'day',
                'payment_method' => 'offline',
                'payment_option' => 'full',
            ])
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
     * 1. Each Part 1 figure equals an independently computed value from DB.
     */
    public function test_each_part1_figure_equals_independent_db_calculation(): void
    {
        $summary = $this->service->summary([$this->turf->id], [
            'start' => '2026-10-01',
            'end' => '2026-10-31',
            'date_basis' => 'session',
        ]);

        $fixtureIds = [
            $this->bookingA->id, $this->bookingB->id, $this->bookingC->id, $this->bookingD->id,
            $this->bookingE->id, $this->bookingF->id, $this->bookingG->id, $this->bookingH->id,
        ];

        // Status counts
        $expectedActive = Booking::whereIn('id', $fixtureIds)->where('status', 'Confirmed')->count();
        $expectedCancelled = Booking::whereIn('id', $fixtureIds)->where('status', 'Cancelled')->count();
        $expectedPartly = Booking::whereIn('id', $fixtureIds)->where('status', 'Partially Cancelled')->count();

        $this->assertEquals($expectedActive, $summary['count_active']);
        $this->assertEquals($expectedCancelled, $summary['count_cancelled']);
        $this->assertEquals($expectedPartly, $summary['count_partly_cancelled']);

        // Active and Cancelled Values from booking_dates
        $expectedActiveValue = round((float) BookingDate::whereIn('booking_id', $fixtureIds)->where('status', '!=', 'Cancelled')->sum('amount'), 2);
        $expectedCancelledValue = round((float) BookingDate::whereIn('booking_id', $fixtureIds)->where('status', 'Cancelled')->sum('amount'), 2);

        $this->assertEquals($expectedActiveValue, $summary['active_value']);
        $this->assertEquals($expectedCancelledValue, $summary['cancelled_value']);

        // Received
        $expectedOnline = round((float) Payment::whereIn('booking_id', $fixtureIds)->where('status', 'Success')->where('payment_method', 'App')->sum('amount'), 2);
        $expectedVenue = round((float) Payment::whereIn('booking_id', $fixtureIds)->where('status', 'Success')->whereIn('payment_method', ['Cash', 'UPI', 'Other'])->sum('amount'), 2);
        $expectedReceived = round($expectedOnline + $expectedVenue, 2);

        $this->assertEquals($expectedReceived, $summary['received']);

        // Sessions
        $expectedCancelledSessions = BookingDate::whereIn('booking_id', $fixtureIds)->where('status', 'Cancelled')->count();
        $expectedAllSessions = BookingDate::whereIn('booking_id', $fixtureIds)->count();

        $this->assertEquals($expectedCancelledSessions, $summary['cancelled_sessions']);
        $this->assertEquals($expectedAllSessions, $summary['all_sessions']);

        // Cancellation figures
        $expectedCancRefunded = round((float) BookingCancellation::whereIn('booking_id', $fixtureIds)->whereIn('refund_status', ['Refunded', 'Cash / Offline Refund'])->sum('refund_amount'), 2);
        $expectedKeptTurf = round((float) BookingCancellation::whereIn('booking_id', $fixtureIds)->whereNotIn('refund_status', ['Pending', 'Pending Resolution'])->sum('turf_cancellation_fee'), 2);
        $expectedKeptPlatform = round((float) BookingCancellation::whereIn('booking_id', $fixtureIds)->whereNotIn('refund_status', ['Pending', 'Pending Resolution'])->sum(DB::raw('COALESCE(platform_fee_retained, 0) + COALESCE(saas_cancellation_fee, 0)')), 2);

        $this->assertEquals($expectedCancRefunded, $summary['cancellation_refunded']);
        $this->assertEquals($expectedKeptTurf, $summary['cancellation_kept_turf']);
        $this->assertEquals($expectedKeptPlatform, $summary['cancellation_kept_platform']);

        // Discounts
        $expectedCoupon = round((float) Booking::whereIn('id', $fixtureIds)->sum('coupon_discount'), 2);
        $expectedStaff = round((float) Booking::whereIn('id', $fixtureIds)->sum('additional_discount'), 2);

        $this->assertEquals($expectedCoupon, $summary['discount_coupon']);
        $this->assertEquals($expectedStaff, $summary['discount_staff']);

        // Booked by
        $this->assertEquals(Booking::whereIn('id', $fixtureIds)->where('booking_source', 'app')->count(), $summary['booked_by']['app']);
        $this->assertEquals(Booking::whereIn('id', $fixtureIds)->where('booking_source', 'staff')->count(), $summary['booked_by']['staff']);
    }

    /**
     * 2. Reconciliation equalities:
     * - active_value + cancelled_value == booking_value;
     * - received == online_collected + venue_collected;
     * - net_collected == received − refunded;
     * - Cash + UPI + Other == venue_collected.
     */
    public function test_reconciliation_equalities(): void
    {
        $summary = $this->service->summary([$this->turf->id], [
            'start' => '2026-10-01',
            'end' => '2026-10-31',
            'date_basis' => 'session',
        ]);

        // 1. active_value + cancelled_value == booking_value
        $this->assertEquals(
            round($summary['active_value'] + $summary['cancelled_value'], 2),
            $summary['booking_value']
        );

        // 2. received == online_collected + venue_collected
        $this->assertEquals(
            round($summary['online_collected'] + $summary['venue_collected'], 2),
            $summary['received']
        );

        // 3. net_collected == received − refunded
        $this->assertEquals(
            round($summary['received'] - $summary['refunded'], 2),
            $summary['net_collected']
        );

        // 4. Cash + UPI + Other == venue_collected
        $splitSum = round(
            $summary['venue_split']['Cash'] + $summary['venue_split']['UPI'] + $summary['venue_split']['Other'],
            2
        );
        $this->assertEquals($splitSum, $summary['venue_collected']);
    }

    /**
     * 3. Kept fees come only from settled cancellations.
     * Add a cancellation left in Pending Resolution: counted in refund_pending,
     * but not in kept or refunded.
     */
    public function test_kept_fees_come_only_from_settled_cancellations(): void
    {
        $bPending = $this->bookOnline(['2026-10-24'], 'full', [$this->slot1->id]);
        $bpDate = $bPending->bookingDates()->first();

        $cancellationService = app(BookingCancellationService::class);
        $cancelPending = $cancellationService->cancelBookingDates($bPending, [$bpDate->id], $this->customer, 'Under review dispute');
        $cPending = $cancelPending['cancellations']->first();
        $this->assertEquals('Pending Resolution', $cPending->refund_status);

        $summary = $this->service->summary([$this->turf->id], [
            'start' => '2026-10-01',
            'end' => '2026-10-31',
            'date_basis' => 'session',
        ]);

        $this->assertGreaterThan(0.00, $summary['refund_pending']);

        // Kept fees must not include cPending
        $settledKeptTurf = (float) BookingCancellation::where('id', '!=', $cPending->id)
            ->whereNotIn('refund_status', ['Pending', 'Pending Resolution'])
            ->sum('turf_cancellation_fee');
        $settledKeptPlatform = (float) BookingCancellation::where('id', '!=', $cPending->id)
            ->whereNotIn('refund_status', ['Pending', 'Pending Resolution'])
            ->sum(DB::raw('COALESCE(platform_fee_retained, 0) + COALESCE(saas_cancellation_fee, 0)'));

        $this->assertEquals(round($settledKeptTurf, 2), $summary['cancellation_kept_turf']);
        $this->assertEquals(round($settledKeptPlatform, 2), $summary['cancellation_kept_platform']);
    }

    /**
     * 4. Collection rate <= 100%, and equals (active_value - due_at_venue) / active_value * 100.
     */
    public function test_collection_rate_formula_and_bounds(): void
    {
        $summary = $this->service->summary([$this->turf->id], [
            'start' => '2026-10-01',
            'end' => '2026-10-31',
            'date_basis' => 'session',
        ]);

        $this->assertGreaterThan(0.00, $summary['active_value']);
        $this->assertGreaterThanOrEqual(0.0, $summary['collection_rate']);
        $this->assertLessThanOrEqual(100.0, $summary['collection_rate']);

        $expectedRate = round((($summary['active_value'] - $summary['due_at_venue']) / $summary['active_value']) * 100, 1);
        $this->assertEquals($expectedRate, $summary['collection_rate']);
    }

    /**
     * 5. Filters apply to the cards:
     * - channel = "online": Bookings counts online and mixed bookings; At Location shows venue money of mixed.
     * - status = "Cancelled": active_value = 0.
     */
    public function test_filters_apply_to_cards(): void
    {
        // Channel filter: online (shows A, B, E, G)
        $summaryApp = $this->service->summary([$this->turf->id], [
            'start' => '2026-10-01',
            'end' => '2026-10-31',
            'date_basis' => 'session',
            'channel' => 'online',
        ]);

        $this->assertEquals(4, $summaryApp['count']); // A (online), B (mixed), E (online), G (online)
        $this->assertEquals($summaryApp['venue_split']['Cash'], $summaryApp['venue_collected']);
        $this->assertGreaterThan(0.00, $summaryApp['venue_collected']); // Booking B's cash collected

        // Status filter: Cancelled (shows D and E)
        $summaryCancelled = $this->service->summary([$this->turf->id], [
            'start' => '2026-10-01',
            'end' => '2026-10-31',
            'date_basis' => 'session',
            'status' => 'Cancelled',
        ]);

        $this->assertEquals(2, $summaryCancelled['count']);
        $this->assertEquals(0.00, $summaryCancelled['active_value']);
        $this->assertGreaterThan(0.00, $summaryCancelled['cancelled_value']);
    }

    /**
     * 6. Rendering: The page shows the 8 card titles, exact formatted amounts, and the stat strip.
     * "At Location" shows venue_collected, NOT booking_value - online_collected.
     */
    public function test_page_renders_8_cards_and_strip(): void
    {
        $this->actingAs($this->owner);
        session(['active_turf_id' => $this->turf->id]);

        $summary = $this->service->summary([$this->turf->id], [
            'start' => '2026-10-01',
            'end' => '2026-10-31',
            'date_basis' => 'session',
        ]);

        $volt = Volt::test('turf.reports-manager')
            ->call('switchTab', 'bookings');

        // Card Titles
        $volt->assertSee('Bookings')
            ->assertSee('Booking Value')
            ->assertSee('Collected (net)')
            ->assertSee('Due at Venue')
            ->assertSee('By App')
            ->assertSee('At Location')
            ->assertSee('Cancellations')
            ->assertSee('Discounts Given');

        // Numbers on cards
        $volt->assertSee('₹' . number_format($summary['booking_value'], 2))
            ->assertSee('₹' . number_format($summary['net_collected'], 2))
            ->assertSee('₹' . number_format($summary['online_collected'], 2))
            ->assertSee('₹' . number_format($summary['venue_collected'], 2))
            ->assertSee('received');

        // Verify At Location does not display (booking_value - online_collected)
        $diff = round($summary['booking_value'] - $summary['online_collected'], 2);
        if ($diff !== $summary['venue_collected']) {
            $volt->assertDontSee('₹' . number_format($diff, 2));
        }

        // Stat strip
        $volt->assertSee('Hours booked')
            ->assertSee('Avg booking')
            ->assertSee('Collection rate')
            ->assertSee('Cancellation rate')
            ->assertSee('Booked by app');
    }

    /**
     * 7. CSV contains the summary block with these values.
     */
    public function test_csv_export_contains_summary_block(): void
    {
        session(['active_turf_id' => $this->turf->id]);

        $response = $this->actingAs($this->owner)->get(route('reports.export-bookings', [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'date_basis' => 'session',
            'channel' => 'all',
        ]));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('Totals,,,,,,,,', $content);
        $this->assertStringContainsString('Bookings,8', $content);
        $this->assertStringContainsString('Active,5', $content);
        $this->assertStringContainsString('Cancelled,2', $content);
        $this->assertStringContainsString('"Booking value",8580.00', $content);
        $this->assertStringContainsString('"Net collected",3860.00', $content);
        $this->assertStringContainsString('"Due at venue",2015.00', $content);
        $this->assertStringContainsString('"By App received",3035.00', $content);
        $this->assertStringContainsString('"At Location received"', $content);
        $this->assertStringContainsString('"Hours booked",6.0', $content);
        $this->assertStringContainsString('"Collection rate %",63.7%', $content);
        $this->assertStringContainsString('"Cancellation rate %",33.3%', $content);
    }

    /**
     * 8. No growth in queries: Rendering the cards for 50 bookings uses no more queries than for 10.
     */
    public function test_no_growth_in_queries_for_summary_cards(): void
    {
        // Create 50 bookings
        for ($i = 1; $i <= 50; $i++) {
            $b = Booking::create([
                'turf_id' => $this->turf->id,
                'user_id' => $this->customer->id,
                'booking_number' => "BK-CARD-{$i}",
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

        // 10 bookings
        $tenIds = Booking::take(10)->pluck('id')->all();
        DB::flushQueryLog();
        $this->service->summary([$this->turf->id], ['booking_ids' => $tenIds]);
        $count10 = count(DB::getQueryLog());

        // 50 bookings
        $fiftyIds = Booking::take(50)->pluck('id')->all();
        DB::flushQueryLog();
        $this->service->summary([$this->turf->id], ['booking_ids' => $fiftyIds]);
        $count50 = count(DB::getQueryLog());

        $this->assertEquals(
            $count10,
            $count50,
            "Summary card queries must be constant aggregated queries. Got {$count10} vs {$count50}."
        );
    }

    /**
     * 9. Hours: active_hours = sum of active slots' durations.
     * Include slot created with duration null (from_time to to_time).
     * Cancelled sessions' slots are not counted.
     */
    public function test_active_hours_calculation_and_cancellations(): void
    {
        $summary = $this->service->summary([$this->turf->id], [
            'start' => '2026-10-01',
            'end' => '2026-10-31',
            'date_basis' => 'session',
        ]);

        // Active sessions:
        // A: slot1 (60 min = 1.0 hr)
        // B: slot1 (60 min = 1.0 hr)
        // C: slot1 (60 min = 1.0 hr)
        // D: CANCELLED (0 hr)
        // E: CANCELLED (0 hr)
        // F: session 1 CANCELLED, session 2 active on slot1 (60 min = 1.0 hr)
        // G: slotNoDuration (10:00 to 11:00 = 60 min = 1.0 hr)
        // H: slot2 (60 min = 1.0 hr)
        // Total expected = 1.0 + 1.0 + 1.0 + 0 + 0 + 1.0 + 1.0 + 1.0 = 6.0 hours.
        $this->assertEquals(6.0, $summary['active_hours']);
    }
}
