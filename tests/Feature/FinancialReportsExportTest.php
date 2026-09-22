<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\BookingDate;
use App\Models\CommissionWalletTransaction;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Role;
use App\Models\SaasSetting;
use App\Models\StaffMember;
use App\Models\Turf;
use App\Models\TurfPayout;
use App\Models\TurfSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class FinancialReportsExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $manager;
    protected Turf $turf;
    protected TurfSetting $turfSetting;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles
        Role::firstOrCreate(['name' => 'turf-admin'], ['display_name' => 'Turf Admin']);
        Role::firstOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);

        // Create owner
        $this->owner = User::factory()->create([
            'name' => 'Turf Owner',
            'email' => 'owner@example.com',
            'commission_wallet_balance' => 5000.00,
        ]);
        $this->owner->assignRole('turf-admin');

        // Create manager
        $this->manager = User::factory()->create([
            'name' => 'Turf Manager',
            'email' => 'manager@example.com',
        ]);
        $this->manager->assignRole('manager');

        // Location & Turf
        $location = Location::create([
            'user_id' => $this->owner->id,
            'name' => 'Main Location',
            'address' => '123 Sport Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
        ]);

        $this->turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Premier Arena',
            'type' => 'Football',
        ]);

        // Turf GST Setting
        $this->turfSetting = TurfSetting::create([
            'turf_id' => $this->turf->id,
            'company_name' => 'Premier Arena Sports Pvt Ltd',
            'gst_number' => '27ABCDE1234F1Z5',
            'is_gst_billing_active' => true,
            'gst_pricing_type' => 'exclusive',
            'gst_percentage' => 18.00,
            'state' => 'Maharashtra',
            'state_code' => '27',
        ]);

        // SaaS platform setting (different GSTIN to ensure separation)
        SaasSetting::create([
            'company_name' => 'Turf SaaS Portal',
            'gst_number' => '33XYZAB9876C1Z9',
            'platform_fee' => 10.00,
            'platform_fee_gst_percentage' => 18.00,
        ]);

        // Link manager to owner
        StaffMember::create([
            'turf_admin_id' => $this->owner->id,
            'user_id' => $this->manager->id,
            'role' => 'manager',
        ]);
        $this->manager->assignedTurfs()->attach($this->turf->id, ['turf_admin_id' => $this->owner->id]);
    }

    public function test_export_wallet_passbook_preserves_running_balance_and_resolves_owner_for_manager()
    {
        // Create transactions in sequence
        CommissionWalletTransaction::create([
            'user_id' => $this->owner->id,
            'type' => 'payment_credit',
            'description' => 'Online payment booking',
            'amount' => 1000.00,
            'balance_after' => 1000.00,
            'created_at' => Carbon::now()->subDays(3),
        ]);

        CommissionWalletTransaction::create([
            'user_id' => $this->owner->id,
            'type' => 'platform_fee_debit',
            'description' => 'Platform fee deduction',
            'amount' => -11.80,
            'balance_after' => 988.20,
            'created_at' => Carbon::now()->subDays(2),
        ]);

        CommissionWalletTransaction::create([
            'user_id' => $this->owner->id,
            'type' => 'payout_debit',
            'description' => 'Bank payout processed',
            'amount' => -500.00,
            'balance_after' => 488.20,
            'created_at' => Carbon::now()->subDay(),
        ]);

        // Manager logs in (Bug #2 regression check: must see owner's data, not 0 rows)
        $response = $this->actingAs($this->manager)->get(route('reports.export-passbook', [
            'start_date' => Carbon::now()->subDays(5)->toDateString(),
            'end_date' => Carbon::now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('# Wallet Passbook Statement', $content);
        $this->assertStringContainsString('+ Booking Credit', $content);
        $this->assertStringContainsString('- Platform Fee', $content);
        $this->assertStringContainsString('- Bank Payout', $content);
        $this->assertStringContainsString('1000.00', $content);
        $this->assertStringContainsString('988.20', $content);
        $this->assertStringContainsString('488.20', $content);
    }

    public function test_export_turf_earnings_breakdown_scopes_by_turf_and_deductions()
    {
        $booking = Booking::create([
            'turf_id' => $this->turf->id,
            'user_id' => $this->owner->id,
            'booking_number' => 'BK-101',
            'date_of_booking' => Carbon::now()->toDateString(),
            'platform_fee' => 10.00,
            'platform_fee_gst' => 1.80,
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
        ]);

        $bookingDate = BookingDate::create([
            'booking_id' => $booking->id,
            'booking_date' => Carbon::now()->toDateString(),
            'amount' => 1000.00,
            'status' => 'Confirmed',
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $bookingDate->id,
            'payment_method' => 'UPI',
            'amount' => 1000.00,
            'commission_amount' => 20.00,
            'gateway_charge_amount' => 15.00,
            'turf_payout_amount' => 953.20,
            'status' => 'Success',
            'paid_at' => Carbon::now(),
            'wallet_cleared_at' => Carbon::now(),
            'deductions_settled_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->owner)->get(route('reports.export-turf-earnings', [
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->endOfMonth()->toDateString(),
        ]));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('BK-101', $content);
        $this->assertStringContainsString('Premier Arena', $content);
        $this->assertStringContainsString('1000.00', $content); // Gross
        $this->assertStringContainsString('11.80', $content);   // Platform Fee
        $this->assertStringContainsString('20.00', $content);   // Commission
        $this->assertStringContainsString('15.00', $content);   // PG
        $this->assertStringContainsString('953.20', $content);  // Net payout
        $this->assertStringContainsString('Cleared', $content);
    }

    public function test_export_turf_gst_report_contains_turf_gstin_and_no_saas_gstin()
    {
        $booking = Booking::create([
            'turf_id' => $this->turf->id,
            'user_id' => $this->owner->id,
            'booking_number' => 'BK-TAX-99',
            'date_of_booking' => Carbon::now()->toDateString(),
            'status' => 'Confirmed',
        ]);

        BookingDate::create([
            'booking_id' => $booking->id,
            'booking_date' => Carbon::now()->toDateString(),
            'taxable_amount' => 1000.00,
            'turf_cgst_amount' => 90.00,
            'turf_sgst_amount' => 90.00,
            'turf_gst_amount' => 180.00,
            'amount' => 1180.00,
            'status' => 'Confirmed',
        ]);

        // Test with session active_turf_id
        session(['active_turf_id' => $this->turf->id]);

        $response = $this->actingAs($this->owner)->get(route('reports.export-gst', [
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->endOfMonth()->toDateString(),
        ]));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        // Regression Bug #4 check: Turf GSTIN must be present, SaaS GSTIN must NEVER appear
        $this->assertStringContainsString('27ABCDE1234F1Z5', $content);
        $this->assertStringNotContainsString('33XYZAB9876C1Z9', $content);
        $this->assertStringContainsString('BK-TAX-99', $content);
        $this->assertStringContainsString('1000.00', $content);
        $this->assertStringContainsString('180.00', $content);
        $this->assertStringContainsString('1180.00', $content);
    }

    public function test_export_cancellations_report_outputs_retained_fees_and_refunds()
    {
        $booking = Booking::create([
            'turf_id' => $this->turf->id,
            'user_id' => $this->owner->id,
            'booking_number' => 'BK-CAN-01',
            'date_of_booking' => Carbon::now()->toDateString(),
            'status' => 'Cancelled',
        ]);

        BookingCancellation::create([
            'booking_id' => $booking->id,
            'cancelled_by_user_id' => $this->owner->id,
            'canceller_role' => 'customer',
            'gross_cancelled_amount' => 1000.00,
            'turf_cancellation_fee' => 100.00,
            'saas_cancellation_fee' => 25.00,
            'refund_amount' => 875.00,
            'refund_status' => 'Processed',
            'resolution_mode' => 'Online',
            'reason' => 'Player injured',
        ]);

        $response = $this->actingAs($this->owner)->get(route('reports.export-cancellations', [
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->endOfMonth()->toDateString(),
        ]));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('BK-CAN-01', $content);
        $this->assertStringContainsString('1000.00', $content);
        $this->assertStringContainsString('100.00', $content);
        $this->assertStringContainsString('25.00', $content);
        $this->assertStringContainsString('875.00', $content);
        $this->assertStringContainsString('Player injured', $content);
    }

    public function test_export_commission_fee_summary_groups_by_month_in_sqlite()
    {
        // Month 1
        CommissionWalletTransaction::create([
            'user_id' => $this->owner->id,
            'type' => 'payment_credit',
            'amount' => 2000.00,
            'balance_after' => 2000.00,
            'created_at' => Carbon::now()->subMonths(1)->startOfMonth(),
        ]);
        CommissionWalletTransaction::create([
            'user_id' => $this->owner->id,
            'type' => 'commission_debit',
            'amount' => -100.00,
            'balance_after' => 1900.00,
            'created_at' => Carbon::now()->subMonths(1)->startOfMonth(),
        ]);

        // Month 2
        CommissionWalletTransaction::create([
            'user_id' => $this->owner->id,
            'type' => 'payment_credit',
            'amount' => 3000.00,
            'balance_after' => 4900.00,
            'created_at' => Carbon::now()->startOfMonth(),
        ]);
        CommissionWalletTransaction::create([
            'user_id' => $this->owner->id,
            'type' => 'platform_fee_debit',
            'amount' => -20.00,
            'balance_after' => 4880.00,
            'created_at' => Carbon::now()->startOfMonth(),
        ]);

        $response = $this->actingAs($this->owner)->get(route('reports.export-commission-summary', [
            'start_date' => Carbon::now()->subMonths(2)->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->endOfMonth()->toDateString(),
        ]));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('Monthly Commission & Software Fee Summary', $content);
        $this->assertStringContainsString('2000.00', $content);
        $this->assertStringContainsString('3000.00', $content);
        $this->assertStringContainsString('100.00', $content);
        $this->assertStringContainsString('20.00', $content);
    }

    public function test_export_payout_history_exports_payout_records()
    {
        TurfPayout::create([
            'user_id' => $this->owner->id,
            'requested_amount' => 2500.00,
            'charge_applied' => 10.00,
            'net_amount' => 2490.00,
            'status' => 'Processed',
            'razorpay_payout_id' => 'pout_123456789',
            'processed_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->manager)->get(route('reports.export-payouts', [
            'start_date' => Carbon::now()->subMonths(1)->toDateString(),
            'end_date' => Carbon::now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('# Bank Payout History', $content);
        $this->assertStringContainsString('2500.00', $content);
        $this->assertStringContainsString('2490.00', $content);
        $this->assertStringContainsString('pout_123456789', $content);
    }

    public function test_reports_manager_blade_component_renders_and_switches_tabs()
    {
        $this->actingAs($this->owner);

        Volt::test('turf.reports-manager')
            ->assertSee('Bookings Ledger')
            ->call('switchTab', 'passbook')
            ->assertSee('Account-Wide Wallet Passbook')
            ->call('switchTab', 'turf-earnings')
            ->assertSee('Gross Revenue')
            ->call('switchTab', 'gst')
            ->assertSee('Output Tax Filing')
            ->call('switchTab', 'cancellations')
            ->assertSee('Cancellations & Refunds', false)
            ->call('switchTab', 'commission')
            ->assertSee('Monthly Commission Summary')
            ->call('switchTab', 'payouts')
            ->assertSee('Account-Wide Bank Payout History');
    }
}
