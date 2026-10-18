<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\CommissionInvoice;
use App\Models\CommissionWalletTransaction;
use App\Models\Location;
use App\Models\Payment;
use App\Models\SaasSetting;
use App\Models\Turf;
use App\Models\TurfSetting;
use App\Models\User;
use App\Services\CommissionInvoiceService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Tests\TestCase;

class CommissionInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $saasAdmin;
    protected User $turfOwner;
    protected Turf $turfA;
    protected Turf $turfB;
    protected CommissionInvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        SaasSetting::create([
            'app_name' => 'TurfBooking',
            'company_name' => 'LEENA IT SOLUTIONS',
            'company_address' => 'Plot 65, Ambernath, MH',
            'state' => 'Maharashtra',
            'state_code' => '27',
            'gst_number' => '27ABCDE1234F1Z5',
            'is_gst_billing_active' => true,
            'commission_gst_sac' => '998599',
            'commission_gst_percentage' => 18.00,
            'commission_percentage' => 10.00,
        ]);

        $this->saasAdmin = User::factory()->create(['email' => 'saas@turf.com']);
        $this->saasAdmin->assignRole('saas-admin');

        $this->turfOwner = User::factory()->create(['name' => 'Sandeep Rathod', 'email' => 'owner@turf.com']);
        $this->turfOwner->assignRole('turf-admin');

        $location = Location::create([
            'user_id' => $this->turfOwner->id,
            'name' => 'Mumbai Arena',
            'address' => 'Andheri Sports Complex',
            'city' => 'Mumbai',
        ]);

        $this->turfA = Turf::create([
            'location_id' => $location->id,
            'name' => 'Turf Alpha',
            'type' => 'Football',
            'is_active' => true,
        ]);

        TurfSetting::create([
            'turf_id' => $this->turfA->id,
            'company_name' => 'Alpha Sports Pvt Ltd',
            'address' => 'Andheri West',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'state_code' => '27',
            'gst_number' => '27AABCA1234A1Z1',
            'is_gst_billing_active' => true,
        ]);

        $this->turfB = Turf::create([
            'location_id' => $location->id,
            'name' => 'Turf Beta',
            'type' => 'Cricket',
            'is_active' => true,
        ]);

        TurfSetting::create([
            'turf_id' => $this->turfB->id,
            'company_name' => 'Beta Sports Club',
            'address' => 'Andheri East',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'state_code' => '27',
            'gst_number' => '27AABCB5678B1Z2',
            'is_gst_billing_active' => true,
        ]);

        $this->service = app(CommissionInvoiceService::class);
    }

    /**
     * Helper to create a booking with a payment and matching commission_debit ledger row.
     */
    protected function createBookingWithCommission(
        Turf $turf,
        Carbon $chargedAt,
        float $bookingAmount = 1000.00,
        float $taxable = 100.00,
        float $gst = 18.00,
        bool $isInterState = false
    ): array {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $cgst = $isInterState ? 0.00 : round($gst / 2, 2);
        $sgst = $isInterState ? 0.00 : round($gst - $cgst, 2);
        $igst = $isInterState ? $gst : 0.00;

        $booking = Booking::create([
            'booking_number' => 'BK-' . uniqid(),
            'turf_id' => $turf->id,
            'user_id' => $customer->id,
            'date_of_booking' => $chargedAt->format('Y-m-d'),
            'booking_type' => 'Online',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
            'total_amount' => $bookingAmount,
            'commission_rate' => 10.00,
            'commission_amount' => $taxable,
            'commission_gst_amount' => $gst,
            'commission_cgst_amount' => $cgst,
            'commission_sgst_amount' => $sgst,
            'commission_igst_amount' => $igst,
            'created_at' => $chargedAt,
        ]);

        $bookingDate = BookingDate::create([
            'booking_id' => $booking->id,
            'booking_date' => $chargedAt->format('Y-m-d'),
            'amount' => $bookingAmount,
            'status' => 'Confirmed',
        ]);

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $bookingDate->id,
            'user_id' => $customer->id,
            'payment_method' => 'App',
            'amount' => $bookingAmount,
            'status' => 'Success',
            'commission_amount' => $taxable,
            'commission_gst_amount' => $gst,
            'created_at' => $chargedAt,
        ]);

        $ledgerAmount = round($taxable + $gst, 2);

        $tx = CommissionWalletTransaction::create([
            'user_id' => $turf->location?->user_id ?? $this->turfOwner->id,
            'type' => 'commission_debit',
            'description' => "Booking #{$booking->id} Platform Commission",
            'amount' => -$ledgerAmount,
            'balance_after' => 0.00,
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
            'created_at' => $chargedAt,
        ]);

        return [$booking, $payment, $tx];
    }

    public function test_happy_path_monthly_invoice_generation(): void
    {
        $september = Carbon::parse('2026-09-15 14:00:00');

        $this->createBookingWithCommission($this->turfA, $september->copy()->setDay(5), 1000.00, 100.00, 18.00);
        $this->createBookingWithCommission($this->turfA, $september->copy()->setDay(12), 2000.00, 200.00, 36.00);
        $this->createBookingWithCommission($this->turfA, $september->copy()->setDay(25), 1500.00, 150.00, 27.00);

        $created = $this->service->generateForMonth(Carbon::parse('2026-09-01'));

        $this->assertCount(1, $created);
        $invoice = $created[0];

        $this->assertEquals(3, $invoice->line_count);
        $this->assertEquals(450.00, (float)$invoice->taxable_amount);
        $this->assertEquals(40.50, (float)$invoice->cgst_amount);
        $this->assertEquals(40.50, (float)$invoice->sgst_amount);
        $this->assertEquals(0.00, (float)$invoice->igst_amount);
        $this->assertEquals(531.00, (float)$invoice->total_amount);

        $this->assertEquals(
            (float)$invoice->total_amount,
            round((float)$invoice->taxable_amount + (float)$invoice->cgst_amount + (float)$invoice->sgst_amount, 2)
        );
    }

    public function test_month_boundary_ledger_created_at_is_source_of_truth(): void
    {
        $augustBooking = Carbon::parse('2026-08-31 23:50:00');
        $septemberCharge = Carbon::parse('2026-09-01 00:05:00');

        [$booking, $payment, $tx] = $this->createBookingWithCommission($this->turfA, $septemberCharge, 1000.00, 100.00, 18.00);
        // Explicitly set booking created_at to August 31
        $booking->update(['created_at' => $augustBooking]);

        // Generate for August -> should be empty
        $augustInvoices = $this->service->generateForMonth(Carbon::parse('2026-08-01'));
        $this->assertEmpty($augustInvoices);

        // Generate for September -> should contain the invoice
        $septemberInvoices = $this->service->generateForMonth(Carbon::parse('2026-09-01'));
        $this->assertCount(1, $septemberInvoices);
        $this->assertEquals(1, $septemberInvoices[0]->line_count);
    }

    public function test_idempotency_prevents_duplicate_generation(): void
    {
        $date = Carbon::parse('2026-09-10 10:00:00');
        $this->createBookingWithCommission($this->turfA, $date);

        $firstRun = $this->service->generateForMonth(Carbon::parse('2026-09-01'));
        $this->assertCount(1, $firstRun);
        $originalInvoiceNumber = $firstRun[0]->invoice_number;

        $secondRun = $this->service->generateForMonth(Carbon::parse('2026-09-01'));
        $this->assertCount(0, $secondRun);

        $this->assertEquals(1, CommissionInvoice::where('turf_id', $this->turfA->id)->count());
        $this->assertEquals($originalInvoiceNumber, CommissionInvoice::first()->invoice_number);
    }

    public function test_per_turf_split_for_same_owner(): void
    {
        $date = Carbon::parse('2026-09-10 10:00:00');
        $this->createBookingWithCommission($this->turfA, $date, 1000.00, 100.00, 18.00);
        $this->createBookingWithCommission($this->turfB, $date, 2000.00, 200.00, 36.00);

        $invoices = $this->service->generateForMonth(Carbon::parse('2026-09-01'));
        $this->assertCount(2, $invoices);

        $invoiceA = CommissionInvoice::where('turf_id', $this->turfA->id)->first();
        $invoiceB = CommissionInvoice::where('turf_id', $this->turfB->id)->first();

        $this->assertNotNull($invoiceA);
        $this->assertNotNull($invoiceB);
        $this->assertNotEquals($invoiceA->invoice_number, $invoiceB->invoice_number);
        $this->assertEquals('27AABCA1234A1Z1', $invoiceA->recipient_snapshot['gst_number']);
        $this->assertEquals('27AABCB5678B1Z2', $invoiceB->recipient_snapshot['gst_number']);
    }

    public function test_zero_commission_turf_creates_no_invoice(): void
    {
        // No bookings created
        $invoice = $this->service->generateForTurf($this->turfA, Carbon::parse('2026-09-01'));
        $this->assertNull($invoice);
        $this->assertEquals(0, CommissionInvoice::count());
    }

    public function test_current_month_rejected_by_service_and_endpoint(): void
    {
        $caught = false;
        try {
            $this->service->generateForMonth(now());
        } catch (InvalidArgumentException $e) {
            $caught = true;
        }
        $this->assertTrue($caught, 'Service should throw InvalidArgumentException for current month');

        // Test web endpoint rejects current month
        $response = $this->actingAs($this->saasAdmin)
            ->post('/saas/commission-invoices/generate', [
                'month' => now()->format('Y-m'),
            ]);

        $response->assertSessionHasErrors('month');
    }

    public function test_inter_state_commission_uses_igst(): void
    {
        // Update Turf A to Karnataka (state code 29)
        TurfSetting::where('turf_id', $this->turfA->id)->first()->update([
            'state' => 'Karnataka',
            'state_code' => '29',
        ]);

        $date = Carbon::parse('2026-09-10 10:00:00');
        $this->createBookingWithCommission($this->turfA, $date, 1000.00, 100.00, 18.00, isInterState: true);

        $invoices = $this->service->generateForMonth(Carbon::parse('2026-09-01'));
        $this->assertCount(1, $invoices);
        $invoice = $invoices[0];

        $this->assertTrue($invoice->is_inter_state);
        $this->assertEquals(18.00, (float)$invoice->igst_amount);
        $this->assertEquals(0.00, (float)$invoice->cgst_amount);
        $this->assertEquals(0.00, (float)$invoice->sgst_amount);
    }

    public function test_non_gst_saas_generates_commission_statement(): void
    {
        SaasSetting::first()->update(['is_gst_billing_active' => false]);

        $date = Carbon::parse('2026-09-10 10:00:00');
        $this->createBookingWithCommission($this->turfA, $date, 1000.00, 100.00, 18.00);

        $invoices = $this->service->generateForMonth(Carbon::parse('2026-09-01'));
        $invoice = $invoices[0];

        $this->assertFalse($invoice->is_gst_invoice);

        $view = view('invoices.commission-invoice', ['invoice' => $invoice])->render();
        $this->assertStringContainsString('COMMISSION STATEMENT', $view);
        $this->assertStringNotContainsString('TAX INVOICE', $view);
    }

    public function test_snapshot_immutability(): void
    {
        $date = Carbon::parse('2026-09-10 10:00:00');
        $this->createBookingWithCommission($this->turfA, $date);

        $invoices = $this->service->generateForMonth(Carbon::parse('2026-09-01'));
        $invoice = $invoices[0];

        // Mutate original settings after generation
        TurfSetting::where('turf_id', $this->turfA->id)->first()->update([
            'gst_number' => '27CHANGED9999Z9',
            'company_name' => 'Completely Different Name',
        ]);
        SaasSetting::first()->update([
            'company_name' => 'New SaaS Platform Brand',
        ]);

        $freshInvoice = $invoice->fresh();
        $this->assertEquals('Alpha Sports Pvt Ltd', $freshInvoice->recipient_snapshot['company_name']);
        $this->assertEquals('27AABCA1234A1Z1', $freshInvoice->recipient_snapshot['gst_number']);
        $this->assertEquals('LEENA IT SOLUTIONS', $freshInvoice->supplier_snapshot['company_name']);

        $view = view('invoices.commission-invoice', ['invoice' => $freshInvoice])->render();
        $this->assertStringContainsString('Alpha Sports Pvt Ltd', $view);
        $this->assertStringContainsString('27AABCA1234A1Z1', $view);
        $this->assertStringContainsString('LEENA IT SOLUTIONS', $view);
        $this->assertStringNotContainsString('Completely Different Name', $view);
    }

    public function test_financial_year_numbering_for_march(): void
    {
        $marchDate = Carbon::parse('2026-03-15 10:00:00');
        $this->createBookingWithCommission($this->turfA, $marchDate);

        // Generated now (e.g. October 2026), but period_end is March 2026 -> FY is 2526
        $invoice = $this->service->generateForTurf($this->turfA, Carbon::parse('2026-03-01'));

        $this->assertNotNull($invoice);
        $this->assertStringStartsWith('CINV-2526-', $invoice->invoice_number);
    }

    public function test_authorization_matrix_for_download(): void
    {
        $date = Carbon::parse('2026-09-10 10:00:00');
        $this->createBookingWithCommission($this->turfA, $date);
        $invoices = $this->service->generateForMonth(Carbon::parse('2026-09-01'));
        $invoice = $invoices[0];

        // 1. Owner of Turf A can download
        $this->actingAs($this->turfOwner)
            ->get(route('commission-invoices.download', $invoice))
            ->assertStatus(200)
            ->assertHeader('content-type', 'application/pdf');

        // 2. Another turf owner gets 403
        $otherOwner = User::factory()->create();
        $otherOwner->assignRole('turf-admin');
        $this->actingAs($otherOwner)
            ->get(route('commission-invoices.download', $invoice))
            ->assertStatus(403);

        // 3. Manager assigned to Turf A can download
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->assignedTurfs()->attach($this->turfA->id, ['turf_admin_id' => $this->turfOwner->id]);
        $this->actingAs($manager)
            ->get(route('commission-invoices.download', $invoice))
            ->assertStatus(200);

        // 4. Regular customer gets 403
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        $this->actingAs($customer)
            ->get(route('commission-invoices.download', $invoice))
            ->assertStatus(403);

        // 5. SaaS Admin can download any invoice
        $this->actingAs($this->saasAdmin)
            ->get(route('commission-invoices.download', $invoice))
            ->assertStatus(200);
    }

    public function test_pdf_template_renders_rupee_symbol_and_safe_styles(): void
    {
        $date = Carbon::parse('2026-09-10 10:00:00');
        $this->createBookingWithCommission($this->turfA, $date);
        $invoice = $this->service->generateForTurf($this->turfA, Carbon::parse('2026-09-01'));

        $view = view('invoices.commission-invoice', ['invoice' => $invoice])->render();

        $this->assertStringContainsString("font-family: 'DejaVu Sans', sans-serif;", $view);
        $this->assertStringNotContainsString('font-weight: 800', $view);
        $this->assertStringNotContainsString('font-weight: 900', $view);
        $this->assertStringContainsString('₹', $view);
    }

    public function test_artisan_command_generates_monthly_invoices(): void
    {
        $date = Carbon::parse('2026-09-10 10:00:00');
        $this->createBookingWithCommission($this->turfA, $date);

        $exitCode = Artisan::call('commission:generate-invoices', [
            '--month' => '2026-09',
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertEquals(1, CommissionInvoice::where('turf_id', $this->turfA->id)->count());
    }

    public function test_turf_deleted_invoice_kept_safely(): void
    {
        $date = Carbon::parse('2026-09-10 10:00:00');
        $this->createBookingWithCommission($this->turfA, $date);
        $invoice = $this->service->generateForTurf($this->turfA, Carbon::parse('2026-09-01'));

        // Delete turf
        $this->turfA->delete();

        // Invoice still exists with turf_id = null
        $freshInvoice = $invoice->fresh();
        $this->assertNotNull($freshInvoice);
        $this->assertNull($freshInvoice->turf_id);
        $this->assertEquals('Alpha Sports Pvt Ltd', $freshInvoice->recipient_snapshot['company_name']);

        // SaaS Admin can still download
        $this->actingAs($this->saasAdmin)
            ->get(route('commission-invoices.download', $freshInvoice))
            ->assertStatus(200);

        // Former owner now gets 403
        $this->actingAs($this->turfOwner)
            ->get(route('commission-invoices.download', $freshInvoice))
            ->assertStatus(403);
    }

    public function test_owner_user_deleted_invoice_kept_safely(): void
    {
        $date = Carbon::parse('2026-09-10 10:00:00');
        $this->createBookingWithCommission($this->turfA, $date);
        $invoice = $this->service->generateForTurf($this->turfA, Carbon::parse('2026-09-01'));

        // Delete owner
        $this->turfOwner->delete();

        $freshInvoice = $invoice->fresh();
        $this->assertNotNull($freshInvoice);
        $this->assertNull($freshInvoice->owner_user_id);
    }

    public function test_month_input_is_not_shifted_when_generated_on_the_31st(): void
    {
        // On the 31st, createFromFormat('Y-m', '2026-09') borrows day 31 and overflows into October.
        Carbon::setTestNow(Carbon::parse('2026-10-31 10:00:00'));
        $this->createBookingWithCommission($this->turfA, Carbon::parse('2026-09-10 10:00:00'));

        $this->actingAs($this->saasAdmin)
            ->post('/saas/commission-invoices/generate', ['month' => '2026-09'])
            ->assertSessionHasNoErrors();

        $invoice = CommissionInvoice::where('turf_id', $this->turfA->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('2026-09-01', $invoice->period_start->toDateString());

        Carbon::setTestNow();
    }

    public function test_command_default_month_on_31st_of_march_is_february(): void
    {
        // Plain subMonth() on 31 March lands on 3 March (the current month) instead of February.
        Carbon::setTestNow(Carbon::parse('2027-03-31 10:00:00'));
        $this->createBookingWithCommission($this->turfA, Carbon::parse('2027-02-15 10:00:00'));

        $this->assertEquals(0, Artisan::call('commission:generate-invoices'));

        $invoice = CommissionInvoice::where('turf_id', $this->turfA->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('2027-02-01', $invoice->period_start->toDateString());

        Carbon::setTestNow();
    }

    public function test_saas_invoice_tab_filters_by_period_and_turf(): void
    {
        $this->createBookingWithCommission($this->turfA, Carbon::parse('2026-08-12 10:00:00'));
        $this->createBookingWithCommission($this->turfA, Carbon::parse('2026-09-12 10:00:00'));
        $this->createBookingWithCommission($this->turfB, Carbon::parse('2026-09-15 10:00:00'));
        $augA = $this->service->generateForTurf($this->turfA, Carbon::parse('2026-08-01'));
        $sepA = $this->service->generateForTurf($this->turfA, Carbon::parse('2026-09-01'));
        $sepB = $this->service->generateForTurf($this->turfB, Carbon::parse('2026-09-01'));

        $this->actingAs($this->saasAdmin);
        $component = \Livewire\Volt\Volt::test('saas.commission-manager')->set('activeTab', 'invoices');

        $component->set('invoiceMonth', '2026-09')
            ->assertSee($sepA->invoice_number)->assertSee($sepB->invoice_number)->assertDontSee($augA->invoice_number);

        $component->set('invoiceTurfId', (string) $this->turfB->id)
            ->assertSee($sepB->invoice_number)->assertDontSee($sepA->invoice_number)->assertDontSee($augA->invoice_number);

        $component->call('clearInvoiceFilters')
            ->assertSee($augA->invoice_number)->assertSee($sepA->invoice_number)->assertSee($sepB->invoice_number);
    }
}
