<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\BookingDate;
use App\Models\CommissionWalletTransaction;
use App\Models\Location;
use App\Models\Payment;
use App\Models\SaasSetting;
use App\Models\Slot;
use App\Models\SlotCategory;
use App\Models\StaffMember;
use App\Models\Turf;
use App\Models\TurfPayout;
use App\Models\User;
use App\Services\WalletLedgerService;
use App\Services\WalletService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

class WalletPassbookTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $manager;
    protected User $customer;
    protected Turf $turfAlpha;
    protected Turf $turfBeta;
    protected Slot $slot;
    protected WalletService $walletService;
    protected WalletLedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        SaasSetting::create([
            'commission_percentage' => 10.00,
            'platform_fee' => 5.00,
            'platform_fee_type' => 'fixed',
            'platform_fee_gst_percentage' => 0.00,
            'min_slots_booking' => 1,
        ]);

        $this->owner = User::factory()->create([
            'name' => 'Turf Admin Owner',
            'email' => 'owner@example.com',
            'mobile' => '9888800001',
            'commission_wallet_balance' => 0.00,
        ]);
        $this->owner->assignRole('turf-admin');

        $this->manager = User::factory()->create([
            'name' => 'Manager Bob',
            'email' => 'bob@example.com',
            'mobile' => '9888800002',
            'commission_wallet_balance' => 0.00,
        ]);
        $this->manager->assignRole('manager');

        // Link manager to owner via StaffMember
        StaffMember::create([
            'turf_admin_id' => $this->owner->id,
            'user_id' => $this->manager->id,
            'role' => 'manager',
        ]);

        $this->customer = User::factory()->create([
            'name' => 'Customer Joe',
            'email' => 'joe@example.com',
            'mobile' => '9888800003',
        ]);
        $this->customer->assignRole('customer');

        $location = Location::create([
            'user_id' => $this->owner->id,
            'name' => 'City Arena',
            'address' => 'Andheri West',
            'city' => 'Mumbai',
        ]);

        $this->turfAlpha = Turf::create([
            'location_id' => $location->id,
            'name' => 'Turf Alpha',
            'type' => 'Football',
            'is_active' => true,
        ]);

        $this->turfBeta = Turf::create([
            'location_id' => $location->id,
            'name' => 'Turf Beta',
            'type' => 'Cricket',
            'is_active' => true,
        ]);

        $cat = SlotCategory::create(['name' => 'Regular']);
        $this->slot = Slot::create([
            'slot_category_id' => $cat->id,
            'name' => 'Morning 6-7 AM',
            'from_time' => '06:00:00',
            'to_time' => '07:00:00',
            'duration' => 60,
        ]);

        $this->walletService = app(WalletService::class);
        $this->ledgerService = app(WalletLedgerService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Helper to create a minimal booking with booking date.
     */
    protected function createBooking(Turf $turf, string $date = '2026-10-15', float $amount = 1000.00): array
    {
        static $refIndex = 1;
        $booking = Booking::create([
            'turf_id' => $turf->id,
            'user_id' => $this->customer->id,
            'booking_number' => 'BK-PASSBOOK-' . str_pad($refIndex, 4, '0', STR_PAD_LEFT),
            'booking_reference' => 'TB-202610-' . str_pad($refIndex++, 5, '0', STR_PAD_LEFT),
            'date_of_booking' => $date,
            'total_amount' => $amount,
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
            'booking_source' => 'app',
        ]);

        $bookingDate = BookingDate::create([
            'booking_id' => $booking->id,
            'booking_date' => $date,
            'amount' => $amount,
            'status' => 'Confirmed',
        ]);

        return [$booking, $bookingDate];
    }

    /**
     * 1. Newest first. 30 entries in one month. The passbook's first row is the newest.
     * "Showing 25 of 30". After loadMorePassbook, all 30 show and "End of passbook" appears.
     */
    public function test_1_newest_first_and_load_more(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31 12:00:00', 'Asia/Kolkata'));

        // Create 30 entries on consecutive days in October 2026
        for ($i = 1; $i <= 30; $i++) {
            $day = str_pad($i, 2, '0', STR_PAD_LEFT);
            Carbon::setTestNow(Carbon::parse("2026-10-{$day} 10:00:00", 'Asia/Kolkata'));
            $this->walletService->applyDelta(
                $this->owner,
                10.00,
                'payment_credit',
                null,
                "Day {$i} credit"
            );
        }

        Carbon::setTestNow(Carbon::parse('2026-10-31 23:59:59', 'Asia/Kolkata'));
        $this->actingAs($this->owner);

        $volt = Volt::test('turf.reports-manager')
            ->set('period', 'custom')
            ->set('startDate', '2026-10-01')
            ->set('endDate', '2026-10-31')
            ->call('switchTab', 'passbook');

        // Newest entry (Day 30) must be visible first, oldest (Day 1) not on page 1 of 25
        $volt->assertSee('Day 30 credit')
            ->assertDontSee('Day 1 credit')
            ->assertSee('Showing 25 of 30 entries')
            ->assertSee('Load more');

        // Verify order in query directly: first row created_at >= second row
        $queryRows = $this->ledgerService->query($this->owner, '2026-10-01', '2026-10-31')->get();
        $this->assertCount(30, $queryRows);
        $this->assertEquals('Day 30 credit', $queryRows->first()->description);
        $this->assertEquals('Day 1 credit', $queryRows->last()->description);

        // After loadMorePassbook, all 30 show and "End of passbook" appears
        $volt->call('loadMorePassbook')
            ->assertSee('Showing 30 of 30 entries')
            ->assertSee('Day 1 credit')
            ->assertSee('End of passbook · 30 entries');
    }

    /**
     * 2. Manager sees the owner's data.
     * - A manager assigned to the owner opens the passbook: the current balance equals the owner's,
     *   and the rows are the owner's.
     * - On Business & Earnings, the same manager sees the owner's statement rows.
     */
    public function test_2_manager_sees_owners_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'Asia/Kolkata'));

        // Owner has wallet balance and transactions
        $this->walletService->applyDelta(
            $this->owner,
            2500.00,
            'payment_credit',
            null,
            'Owner earnings transaction'
        );

        $this->owner->refresh();
        $this->manager->refresh();
        $this->assertEquals(2500.00, (float) $this->owner->commission_wallet_balance);
        $this->assertEquals(0.00, (float) $this->manager->commission_wallet_balance);

        // Log in as manager
        $this->actingAs($this->manager);

        // 1. Passbook tab in reports-manager
        $voltReports = Volt::test('turf.reports-manager')
            ->set('period', 'custom')
            ->set('startDate', '2026-10-01')
            ->set('endDate', '2026-10-31')
            ->call('switchTab', 'passbook');

        $voltReports->assertSee('Owner earnings transaction')
            ->assertSee('₹2,500.00');

        // 2. Business & Earnings statement in business-manager
        $voltBusiness = Volt::test('turf.business-manager');
        $voltBusiness->assertSee('Owner earnings transaction')
            ->assertSee('₹2,500.00');
    }

    /**
     * 3. Opening and closing balances.
     * - Entries before the period (+500, −100), in the period (+300, −50) and after it (+70).
     * - For that period: opening 400, credits 300, debits 50, closing 650.
     * - For a period ending today: closing = the owner's commission_wallet_balance.
     */
    public function test_3_opening_and_closing_balances(): void
    {
        // 1. Before period (September 2026)
        Carbon::setTestNow(Carbon::parse('2026-09-10 10:00:00', 'Asia/Kolkata'));
        $this->walletService->applyDelta($this->owner, 500.00, 'payment_credit', null, 'Sept credit');
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00', 'Asia/Kolkata'));
        $this->walletService->applyDelta($this->owner, -100.00, 'commission_debit', null, 'Sept debit');

        // 2. In period (October 2026)
        Carbon::setTestNow(Carbon::parse('2026-10-05 10:00:00', 'Asia/Kolkata'));
        $this->walletService->applyDelta($this->owner, 300.00, 'payment_credit', null, 'Oct credit');
        Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00', 'Asia/Kolkata'));
        $this->walletService->applyDelta($this->owner, -50.00, 'platform_fee_debit', null, 'Oct debit');

        // 3. After period (November 2026)
        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00:00', 'Asia/Kolkata'));
        $this->walletService->applyDelta($this->owner, 70.00, 'payment_credit', null, 'Nov credit');

        // Test October period
        $summary = $this->ledgerService->summary($this->owner, '2026-10-01', '2026-10-31');

        $this->assertEquals(400.00, $summary['opening_balance']);
        $this->assertEquals(300.00, $summary['period_credits']);
        $this->assertEquals(50.00, $summary['period_debits']);
        $this->assertEquals(650.00, $summary['closing_balance']);

        // Period ending today (November 2026)
        Carbon::setTestNow(Carbon::parse('2026-11-02 15:00:00', 'Asia/Kolkata'));
        $this->owner->refresh();
        $summaryToday = $this->ledgerService->summary($this->owner, '2026-11-01', '2026-11-02');
        $this->assertEquals(650.00, $summaryToday['opening_balance']);
        $this->assertEquals(70.00, $summaryToday['period_credits']);
        $this->assertEquals(0.00, $summaryToday['period_debits']);
        $this->assertEquals(720.00, $summaryToday['closing_balance']);
        $this->assertEquals($this->owner->commission_wallet_balance, $summaryToday['closing_balance']);
    }

    /**
     * 4. Group totals for a period with each type: correct signed sums. Zero groups are hidden.
     */
    public function test_4_group_totals_for_period(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 10:00:00', 'Asia/Kolkata'));

        // earnings (+1000)
        $this->walletService->applyDelta($this->owner, 1000.00, 'payment_credit', null, 'Booking earnings');
        // deductions (-100 and -5 = -105)
        $this->walletService->applyDelta($this->owner, -100.00, 'commission_debit', null, 'Commission fee');
        $this->walletService->applyDelta($this->owner, -5.00, 'platform_fee_debit', null, 'Platform fee');
        // returns (+50)
        $this->walletService->applyDelta($this->owner, 50.00, 'commission_reversal', null, 'Commission returned');
        // refunds (-200)
        $this->walletService->applyDelta($this->owner, -200.00, 'refund_adjustment', null, 'Player refund');
        // payouts (-400)
        $this->walletService->applyDelta($this->owner, -400.00, 'payout_debit', null, 'Bank payout');
        // other is left at 0

        $summary = $this->ledgerService->summary($this->owner, '2026-10-01', '2026-10-31');

        $this->assertEquals(1000.00, $summary['by_group']['earnings']);
        $this->assertEquals(-105.00, $summary['by_group']['deductions']);
        $this->assertEquals(50.00, $summary['by_group']['returns']);
        $this->assertEquals(-200.00, $summary['by_group']['refunds']);
        $this->assertEquals(-400.00, $summary['by_group']['payouts']);
        $this->assertEquals(0.00, $summary['by_group']['other']);

        $this->actingAs($this->owner);
        $volt = Volt::test('turf.reports-manager')
            ->set('period', 'custom')
            ->set('startDate', '2026-10-01')
            ->set('endDate', '2026-10-31')
            ->call('switchTab', 'passbook');

        // Badges for active groups are visible
        $volt->assertSee('Booking earnings:')
            ->assertSee('+₹1,000.00')
            ->assertSee('Platform fees & commission:')
            ->assertSee('₹-105.00')
            ->assertSee('Returned on cancellation:')
            ->assertSee('+₹50.00')
            ->assertSee('Refunds to players:')
            ->assertSee('₹-200.00')
            ->assertSee('Bank payouts:')
            ->assertSee('₹-400.00');

        // Zero groups are hidden: "Other:" badge should not appear
        $volt->assertDontSee('Other:');
    }

    /**
     * 5. Booking and turf columns.
     * - A payment_credit on a Payment shows its booking reference, linked to turf.bookings?search=…
     * - A commission_reversal on a BookingCancellation shows its booking reference.
     * - A payout_debit shows "Payout #id" and "All turfs".
     * - With an owner who has two turfs, each row shows the right turf name.
     */
    public function test_5_booking_and_turf_columns(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 10:00:00', 'Asia/Kolkata'));

        // Booking 1 on Turf Alpha
        [$booking1, $bDate1] = $this->createBooking($this->turfAlpha, '2026-10-15', 1000.00);
        $payment1 = Payment::create([
            'booking_id' => $booking1->id,
            'booking_date_id' => $bDate1->id,
            'payment_method' => 'App',
            'amount' => 1000.00,
            'status' => 'Success',
            'paid_at' => now(),
        ]);
        $this->walletService->applyDelta($this->owner, 871.40, 'payment_credit', $payment1, 'Turf Alpha Online Payment');

        // Booking 2 on Turf Beta
        [$booking2, $bDate2] = $this->createBooking($this->turfBeta, '2026-10-16', 1500.00);
        $cancellation = BookingCancellation::create([
            'booking_id' => $booking2->id,
            'booking_date_id' => $bDate2->id,
            'cancelled_by_user_id' => $this->customer->id,
            'canceller_role' => 'customer',
            'refund_status' => 'Refunded',
            'refund_amount' => 1200.00,
            'turf_cancellation_fee' => 150.00,
        ]);
        $this->walletService->applyDelta($this->owner, 150.00, 'commission_reversal', $cancellation, 'Turf Beta Cancellation Reversal');

        // Payout
        $payout = TurfPayout::create([
            'user_id' => $this->owner->id,
            'requested_amount' => 500.00,
            'charge_applied' => 0.00,
            'net_amount' => 500.00,
            'status' => 'completed',
        ]);
        $this->walletService->applyDelta($this->owner, -500.00, 'payout_debit', $payout, 'Bank payout to HDFC');

        $this->actingAs($this->owner);
        $volt = Volt::test('turf.reports-manager')
            ->set('period', 'custom')
            ->set('startDate', '2026-10-01')
            ->set('endDate', '2026-10-31')
            ->call('switchTab', 'passbook');

        // 1. Payment on Turf Alpha: booking reference link + Turf Alpha name
        $volt->assertSee($booking1->booking_reference)
            ->assertSee('Turf Alpha')
            ->assertSeeHtml(route('turf.bookings', ['search' => $booking1->booking_reference]));

        // 2. Cancellation on Turf Beta: booking reference + Turf Beta name
        $volt->assertSee($booking2->booking_reference)
            ->assertSee('Turf Beta');

        // 3. Payout: "Payout #id" + "All turfs"
        $volt->assertSee("Payout #{$payout->id}")
            ->assertSee('All turfs');
    }

    /**
     * 6. Filters.
     * - Type = "payouts" shows only payout rows.
     * - ₹0 offline_booking_record rows are hidden by default and shown when the checkbox is ticked.
     * - Changing a filter resets "load more" to 25.
     */
    public function test_6_filters_group_and_zero_notes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 10:00:00', 'Asia/Kolkata'));

        // Standard payment credit
        $this->walletService->applyDelta($this->owner, 1000.00, 'payment_credit', null, 'Earnings note');

        // Payout debit
        $payout = TurfPayout::create([
            'user_id' => $this->owner->id,
            'requested_amount' => 200.00,
            'charge_applied' => 0.00,
            'net_amount' => 200.00,
            'status' => 'completed',
        ]);
        $this->walletService->applyDelta($this->owner, -200.00, 'payout_debit', $payout, 'Payout transaction');

        // ₹0 offline note
        $this->walletService->applyDelta($this->owner, 0.00, 'offline_booking_record', null, 'Pay at Venue ₹0 note');

        $this->actingAs($this->owner);
        $volt = Volt::test('turf.reports-manager')
            ->set('period', 'custom')
            ->set('startDate', '2026-10-01')
            ->set('endDate', '2026-10-31')
            ->call('switchTab', 'passbook');

        // 1. ₹0 row is hidden by default
        $volt->assertSee('Earnings note')
            ->assertSee('Payout transaction')
            ->assertDontSee('Pay at Venue ₹0 note');

        // 2. Ticking ₹0 checkbox reveals it
        $volt->set('passbookIncludeZero', true)
            ->assertSee('Pay at Venue ₹0 note');

        // 3. Filter by "payouts" shows only payouts
        $volt->set('passbookGroupFilter', 'payouts')
            ->assertSee('Payout transaction')
            ->assertDontSee('Earnings note');

        // 4. Changing a filter resets passbookPerPage to 25
        $volt->set('passbookPerPage', 50)
            ->set('passbookGroupFilter', 'earnings');
        $this->assertEquals(25, $volt->get('passbookPerPage'));

        $volt->set('passbookPerPage', 50)
            ->set('passbookIncludeZero', false);
        $this->assertEquals(25, $volt->get('passbookPerPage'));
    }

    /**
     * 7. Notes.
     * - The pending-clearance note appears when there's an uncredited online booking for a future date.
     * - The negative-balance note appears only when the balance is < 0.
     */
    public function test_7_notes_pending_clearance_and_negative_balance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 10:00:00', 'Asia/Kolkata'));

        // Initially balance = 0, no pending clearance
        $this->actingAs($this->owner);
        $volt = Volt::test('turf.reports-manager')
            ->set('period', 'custom')
            ->set('startDate', '2026-10-01')
            ->set('endDate', '2026-10-31')
            ->call('switchTab', 'passbook');

        $volt->assertDontSee('is still pending clearance')
            ->assertDontSee('A negative balance is platform fee');

        // 1. Create an uncredited online payment for a future date (tomorrow: 2026-10-16)
        [$futureBooking, $futureBDate] = $this->createBooking($this->turfAlpha, '2026-10-16', 1200.00);
        Payment::create([
            'booking_id' => $futureBooking->id,
            'booking_date_id' => $futureBDate->id,
            'payment_method' => 'App',
            'amount' => 1200.00,
            'status' => 'Success',
            'wallet_cleared_at' => null,
            'paid_at' => now(),
        ]);

        $volt2 = Volt::test('turf.reports-manager')
            ->set('period', 'custom')
            ->set('startDate', '2026-10-01')
            ->set('endDate', '2026-10-31')
            ->call('switchTab', 'passbook');

        $volt2->assertSee('is still pending clearance')
            ->assertSee('See Business');

        // 2. Make wallet balance negative (e.g. cash deductions without prior balance)
        $this->walletService->applyDelta($this->owner, -150.00, 'commission_debit', null, 'Cash commission debit');
        $this->owner->refresh();
        $this->assertLessThan(0, $this->owner->commission_wallet_balance);

        $volt3 = Volt::test('turf.reports-manager')
            ->set('period', 'custom')
            ->set('startDate', '2026-10-01')
            ->set('endDate', '2026-10-31')
            ->call('switchTab', 'passbook');

        $volt3->assertSee('A negative balance is platform fee and commission owed on cash');
    }

    /**
     * 8. No query per row. With 50 rows on screen, the total query count for rendering
     * the tab is <= 40 (constant, tuned bound). Check that 100 rows doesn't need more queries than 50.
     */
    public function test_8_no_query_per_row_rendering_passbook_tab(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 10:00:00', 'Asia/Kolkata'));

        // Create 100 transactions with real payments and bookings
        [$booking, $bDate] = $this->createBooking($this->turfAlpha, '2026-10-15', 500.00);
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $bDate->id,
            'payment_method' => 'App',
            'amount' => 500.00,
            'status' => 'Success',
            'paid_at' => now(),
        ]);

        for ($i = 1; $i <= 100; $i++) {
            $this->walletService->applyDelta($this->owner, 10.00, 'payment_credit', $payment, "Bulk tx {$i}");
        }

        $this->actingAs($this->owner);

        DB::enableQueryLog();

        $component = Volt::test('turf.reports-manager')
            ->set('period', 'custom')
            ->set('startDate', '2026-10-01')
            ->set('endDate', '2026-10-31')
            ->call('switchTab', 'passbook');

        // 50 rows
        DB::flushQueryLog();
        $component->set('passbookPerPage', 50);
        $queries50 = DB::getQueryLog();
        $queryCount50 = count($queries50);

        // 100 rows
        DB::flushQueryLog();
        $component->set('passbookPerPage', 100);
        $queries100 = DB::getQueryLog();
        $queryCount100 = count($queries100);

        // Must be constant and not grow from 50 to 100 rows
        $this->assertLessThanOrEqual(40, $queryCount50, "Expected <= 40 queries for 50 rows, got {$queryCount50}");
        $this->assertEquals(
            $queryCount50,
            $queryCount100,
            "Query count should be constant across page sizes (got {$queryCount50} for 50 vs {$queryCount100} for 100)"
        );
    }

    /**
     * 9. CSV export.
     * - The header lines (opening, closing), then the columns:
     *   Date,Time,Booking,Turf,Type,Description,Amount (INR),Balance After (INR)
     * - Rows oldest first, and the totals at the end.
     * - The group filter applies.
     * - A manager's export is the owner's.
     */
    public function test_9_csv_export_format_and_filters(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 10:00:00', 'Asia/Kolkata'));
        $this->walletService->applyDelta($this->owner, 1000.00, 'payment_credit', null, 'Early credit');

        Carbon::setTestNow(Carbon::parse('2026-10-20 14:30:00', 'Asia/Kolkata'));
        $payout = TurfPayout::create([
            'user_id' => $this->owner->id,
            'requested_amount' => 300.00,
            'charge_applied' => 0.00,
            'net_amount' => 300.00,
            'status' => 'completed',
        ]);
        $this->walletService->applyDelta($this->owner, -300.00, 'payout_debit', $payout, 'Mid-month payout');

        session(['active_turf_id' => $this->turfAlpha->id]);

        // 1. Owner export
        $response = $this->actingAs($this->owner)->get(route('reports.export-passbook', [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'group' => 'all',
            'zero' => 0,
        ]));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        // Header lines
        $this->assertStringContainsString('# Wallet Passbook — ' . $this->owner->name . ' — 2026-10-01 to 2026-10-31', $content);
        $this->assertStringContainsString('"Opening balance (INR)",0.00', $content);
        $this->assertStringContainsString('"Closing balance (INR)",700.00', $content);

        // Column headers
        $this->assertStringContainsString('Date,Time,Booking,Turf,Type,Description,"Amount (INR)","Balance After (INR)"', $content);

        // Rows are OLDEST FIRST: Early credit must appear before Mid-month payout
        $earlyPos = strpos($content, 'Early credit');
        $payoutPos = strpos($content, 'Mid-month payout');
        $this->assertNotFalse($earlyPos);
        $this->assertNotFalse($payoutPos);
        $this->assertLessThan($payoutPos, $earlyPos, 'CSV rows must be ordered OLDEST FIRST');

        // Footer totals
        $this->assertStringContainsString('"Total credits (INR)",1000.00', $content);
        $this->assertStringContainsString('"Total debits (INR)",300.00', $content);

        // 2. Group filter in CSV export (only payouts)
        $payoutsResponse = $this->actingAs($this->owner)->get(route('reports.export-passbook', [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'group' => 'payouts',
            'zero' => 0,
        ]));
        $payoutsContent = $payoutsResponse->streamedContent();
        $this->assertStringContainsString('Mid-month payout', $payoutsContent);
        $this->assertStringNotContainsString('Early credit', $payoutsContent);

        // 3. Manager export returns owner's passbook data
        $managerResponse = $this->actingAs($this->manager)->get(route('reports.export-passbook', [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'group' => 'all',
            'zero' => 0,
        ]));
        $managerContent = $managerResponse->streamedContent();
        $this->assertStringContainsString('# Wallet Passbook — ' . $this->owner->name, $managerContent);
        $this->assertStringContainsString('Early credit', $managerContent);
    }

    /**
     * 10. Phone layout. The rendered tab contains the card list markup (passbook-card)
     * and the table markup (passbook-table) with the responsive classes.
     */
    public function test_10_phone_layout_markup(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 10:00:00', 'Asia/Kolkata'));
        $this->walletService->applyDelta($this->owner, 500.00, 'payment_credit', null, 'Responsive card test');

        $this->actingAs($this->owner);
        $volt = Volt::test('turf.reports-manager')
            ->set('period', 'custom')
            ->set('startDate', '2026-10-01')
            ->set('endDate', '2026-10-31')
            ->call('switchTab', 'passbook');

        // Check for passbook-table (desktop view) and passbook-card (mobile view)
        $volt->assertSeeHtml('passbook-table')
            ->assertSeeHtml('passbook-card')
            ->assertSeeHtml('hidden sm:block')
            ->assertSeeHtml('block sm:hidden');
    }

    /**
     * 11. Business & Earnings statement query count does not grow with row count (Item 3).
     * Rendering Business & Earnings with 50 statement rows uses no more queries than with 10.
     */
    public function test_11_business_earnings_statement_queries_do_not_grow_with_row_count(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 10:00:00', 'Asia/Kolkata'));

        [$booking, $bDate] = $this->createBooking($this->turfAlpha, '2026-10-15', 500.00);
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $bDate->id,
            'payment_method' => 'App',
            'amount' => 500.00,
            'status' => 'Success',
            'paid_at' => now(),
        ]);

        for ($i = 1; $i <= 50; $i++) {
            $this->walletService->applyDelta($this->owner, 10.00, 'payment_credit', $payment, "Stmt row {$i}");
        }

        $this->actingAs($this->owner);

        DB::enableQueryLog();

        // 10 rows
        DB::flushQueryLog();
        Volt::test('turf.business-manager')
            ->set('txPerPage', 10);
        $queryCount10 = count(DB::getQueryLog());

        // 50 rows
        DB::flushQueryLog();
        Volt::test('turf.business-manager')
            ->set('txPerPage', 50);
        $queryCount50 = count(DB::getQueryLog());

        $this->assertEquals(
            $queryCount10,
            $queryCount50,
            "Business & Earnings queries should be constant across page sizes (got {$queryCount10} for 10 vs {$queryCount50} for 50)"
        );
    }
}
