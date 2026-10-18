<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\BookingSlot;
use App\Models\Location;
use App\Models\Payment;
use App\Models\SaasSetting;
use App\Models\Slot;
use App\Models\SlotCategory;
use App\Models\Turf;
use App\Models\TurfSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SaasSetting::create([
            'app_name' => 'TurfBooking',
            'company_name' => 'LEENA IT SOLUTIONS',
            'company_address' => 'Plot 65, Ambernath, MH',
            'state' => 'Maharashtra',
            'state_code' => '27',
            'gst_number' => '27ABCDE1234F1Z5',
            'is_gst_billing_active' => true,
            'booking_gst_sac' => '999652',
            'platform_fee' => 5.00,
        ]);
    }

    protected function createBookingFixture(array $options = []): array
    {
        $customer = $options['customer'] ?? User::factory()->create();
        $turfOwner = $options['turf_owner'] ?? User::factory()->create();

        $location = Location::create([
            'user_id' => $turfOwner->id,
            'name' => 'Test Venue',
            'state' => $options['turf_state'] ?? 'Maharashtra',
            'city' => 'Mumbai',
            'address' => 'Andheri Sports Complex',
            'status' => true,
        ]);

        $turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Champions Turf',
            'type' => 'Grass',
        ]);

        $turfSetting = TurfSetting::create([
            'turf_id' => $turf->id,
            'company_name' => 'Champions Sports LLP',
            'address' => 'Andheri West',
            'city' => 'Mumbai',
            'state' => $options['turf_state'] ?? 'Maharashtra',
            'state_code' => $options['turf_state_code'] ?? '27',
            'gst_number' => $options['turf_gst_number'] ?? '27AAACH1234A1Z1',
            'is_gst_billing_active' => $options['turf_is_gst'] ?? true,
            'gst_pricing_type' => 'excluded',
            'gst_percentage' => 18.00,
        ]);

        $slotCategory = SlotCategory::firstOrCreate([
            'name' => 'Regular',
        ]);

        $slot1 = Slot::create([
            'slot_category_id' => $slotCategory->id,
            'from_time' => '10:00:00',
            'to_time' => '11:00:00',
            'duration' => 60,
            'is_active' => true,
        ]);

        $slot2 = Slot::create([
            'slot_category_id' => $slotCategory->id,
            'from_time' => '11:00:00',
            'to_time' => '12:00:00',
            'duration' => 60,
            'is_active' => true,
        ]);

        $booking = Booking::create([
            'user_id' => $customer->id,
            'turf_id' => $turf->id,
            'date_of_booking' => now(),
            'booking_type' => $options['booking_type'] ?? 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
            'actual_amount' => 1000.00,
            'taxable_amount' => 1000.00,
            'turf_gst_rate' => 18.00,
            'turf_gst_amount' => ($options['turf_is_gst'] ?? true) ? 180.00 : 0.00,
            'turf_cgst_amount' => ($options['turf_is_gst'] ?? true) ? 90.00 : 0.00,
            'turf_sgst_amount' => ($options['turf_is_gst'] ?? true) ? 90.00 : 0.00,
            'platform_fee' => $options['platform_fee'] ?? 5.00,
            'platform_fee_gst' => isset($options['platform_fee']) && $options['platform_fee'] == 0 ? 0.00 : 0.90,
            'platform_fee_cgst' => $options['platform_fee_cgst'] ?? 0.45,
            'platform_fee_sgst' => $options['platform_fee_sgst'] ?? 0.45,
            'platform_fee_igst' => $options['platform_fee_igst'] ?? 0.00,
            'total_amount' => ($options['turf_is_gst'] ?? true) ? 1185.90 : 1005.90,
            'payable_now' => ($options['turf_is_gst'] ?? true) ? 1185.90 : 1005.90,
            'balance_amount' => 0.00,
            'customer_gstin' => $options['customer_gstin'] ?? null,
            'customer_company_name' => $options['customer_company_name'] ?? null,
        ]);

        $bDate1 = BookingDate::create([
            'booking_id' => $booking->id,
            'booking_date' => Carbon::tomorrow('Asia/Kolkata')->toDateString(),
            'amount' => 1000.00,
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
        ]);
        BookingSlot::create([
            'booking_date_id' => $bDate1->id,
            'slot_id' => $slot1->id,
        ]);

        if (!empty($options['second_date'])) {
            $bDate2 = BookingDate::create([
                'booking_id' => $booking->id,
                'booking_date' => Carbon::tomorrow('Asia/Kolkata')->addDays(2)->toDateString(),
                'amount' => 500.00,
                'status' => 'Confirmed',
                'payment_status' => 'Paid',
            ]);
            BookingSlot::create([
                'booking_date_id' => $bDate2->id,
                'slot_id' => $slot2->id,
            ]);
        }

        Payment::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $bDate1->id,
            'amount' => $options['first_payment_amount'] ?? 1185.90,
            'payment_method' => $options['first_payment_method'] ?? 'Razorpay Online',
            'status' => 'Success',
            'gateway_payment_id' => 'pay_test_001',
            'paid_at' => now(),
        ]);

        if (!empty($options['second_payment'])) {
            Payment::create([
                'booking_id' => $booking->id,
                'booking_date_id' => $bDate1->id,
                'amount' => $options['second_payment_amount'] ?? 200.00,
                'payment_method' => 'Cash',
                'status' => 'Success',
                'paid_at' => now(),
            ]);
        }

        return [$customer, $turfOwner, $booking, $turfSetting];
    }

    public function test_two_page_invoice_when_turf_and_saas_both_gst_active_same_state(): void
    {
        [$customer, , $booking] = $this->createBookingFixture([
            'turf_is_gst' => true,
            'platform_fee' => 5.00,
            'platform_fee_cgst' => 0.45,
            'platform_fee_sgst' => 0.45,
            'platform_fee_igst' => 0.00,
        ]);

        $res = $this->actingAs($customer, 'sanctum')->get("/api/bookings/{$booking->id}/invoice");
        $res->assertStatus(200);
        $res->assertHeader('content-type', 'application/pdf');

        // Check view rendering directly to assert content
        $view = view('invoices.booking-bill', [
            'booking' => $booking->fresh(['bookingDates.bookingSlots.slot', 'payments', 'turf.turfSetting', 'user']),
            'turfSetting' => $booking->turf->turfSetting,
            'saasSetting' => SaasSetting::first(),
            'turfIsGstActive' => true,
            'saasIsGstActive' => true,
            'includeSaasPage' => true,
            'bookingGstSac' => '999652',
        ])->render();

        // Page 1: Turf Tax Invoice with CGST & SGST only (never IGST)
        $this->assertStringContainsString('TAX INVOICE', $view);
        $this->assertStringContainsString('Champions Sports LLP', $view);
        $this->assertStringContainsString('CGST (9.0%)', $view);
        $this->assertStringContainsString('SGST (9.0%)', $view);
        $this->assertStringNotContainsString('Turf Charge:</td><td class="text-right">IGST', $view);

        // Page 2: SaaS Platform Fee Tax Invoice with CGST+SGST
        $this->assertStringContainsString('TAX INVOICE (PLATFORM CONVENIENCE FEE)', $view);
        $this->assertStringContainsString('LEENA IT SOLUTIONS', $view);
        $this->assertStringContainsString('Intra-State Supply (CGST + SGST)', $view);
        $this->assertStringContainsString('page-break-after: always;', $view);
    }

    public function test_page_2_shows_igst_when_cross_state(): void
    {
        [$customer, , $booking] = $this->createBookingFixture([
            'turf_is_gst' => true,
            'turf_state' => 'Karnataka',
            'turf_state_code' => '29',
            'platform_fee' => 5.00,
            'platform_fee_cgst' => 0.00,
            'platform_fee_sgst' => 0.00,
            'platform_fee_igst' => 0.90,
        ]);

        $view = view('invoices.booking-bill', [
            'booking' => $booking->fresh(['bookingDates.bookingSlots.slot', 'payments', 'turf.turfSetting', 'user']),
            'turfSetting' => $booking->turf->turfSetting,
            'saasSetting' => SaasSetting::first(),
            'turfIsGstActive' => true,
            'saasIsGstActive' => true,
            'includeSaasPage' => true,
            'bookingGstSac' => '999652',
        ])->render();

        // Page 1: Turf is still CGST+SGST (venue state = Karnataka)
        $this->assertStringContainsString('CGST (9.0%)', $view);
        $this->assertStringContainsString('SGST (9.0%)', $view);

        // Page 2: SaaS shows IGST (18%) because SaaS is in MH (27) and supply is to Karnataka (29)
        $this->assertStringContainsString('Inter-State Supply (IGST)', $view);
        $this->assertStringContainsString('IGST (18%):', $view);
    }

    public function test_turf_gst_active_and_saas_not_gst_active(): void
    {
        SaasSetting::first()->update(['is_gst_billing_active' => false]);

        [$customer, , $booking] = $this->createBookingFixture([
            'turf_is_gst' => true,
            'platform_fee' => 5.00,
        ]);

        $view = view('invoices.booking-bill', [
            'booking' => $booking->fresh(['bookingDates.bookingSlots.slot', 'payments', 'turf.turfSetting', 'user']),
            'turfSetting' => $booking->turf->turfSetting,
            'saasSetting' => SaasSetting::first(),
            'turfIsGstActive' => true,
            'saasIsGstActive' => false,
            'includeSaasPage' => true,
            'bookingGstSac' => '999652',
        ])->render();

        // Page 1 is Tax Invoice
        $this->assertStringContainsString('TAX INVOICE', $view);
        // Page 2 is Plain Fee Receipt
        $this->assertStringContainsString('PLATFORM FEE RECEIPT', $view);
    }

    public function test_turf_not_gst_and_saas_gst_active(): void
    {
        [$customer, , $booking] = $this->createBookingFixture([
            'turf_is_gst' => false,
            'platform_fee' => 5.00,
        ]);

        $view = view('invoices.booking-bill', [
            'booking' => $booking->fresh(['bookingDates.bookingSlots.slot', 'payments', 'turf.turfSetting', 'user']),
            'turfSetting' => $booking->turf->turfSetting,
            'saasSetting' => SaasSetting::first(),
            'turfIsGstActive' => false,
            'saasIsGstActive' => true,
            'includeSaasPage' => true,
            'bookingGstSac' => '999652',
        ])->render();

        // Page 1 is Booking Receipt
        $this->assertStringContainsString('BOOKING RECEIPT', $view);
        // Page 2 is Tax Invoice
        $this->assertStringContainsString('TAX INVOICE (PLATFORM CONVENIENCE FEE)', $view);
    }

    public function test_single_page_when_platform_fee_is_zero(): void
    {
        [$customer, , $booking] = $this->createBookingFixture([
            'turf_is_gst' => true,
            'platform_fee' => 0.00,
        ]);

        $this->actingAs($customer, 'sanctum')->get("/api/bookings/{$booking->id}/invoice")->assertStatus(200);

        $view = view('invoices.booking-bill', [
            'booking' => $booking->fresh(['bookingDates.bookingSlots.slot', 'payments', 'turf.turfSetting', 'user']),
            'turfSetting' => $booking->turf->turfSetting,
            'saasSetting' => SaasSetting::first(),
            'turfIsGstActive' => true,
            'saasIsGstActive' => true,
            'includeSaasPage' => false,
            'bookingGstSac' => '999652',
        ])->render();

        $this->assertStringNotContainsString('page-break-after: always;', $view);
        $this->assertStringNotContainsString('PLATFORM CONVENIENCE FEE', $view);
    }

    public function test_invoice_numbers_are_persisted_identically_on_multiple_downloads(): void
    {
        [$customer, , $booking] = $this->createBookingFixture([
            'turf_is_gst' => true,
            'platform_fee' => 5.00,
        ]);

        $this->assertNull($booking->turf_invoice_number);
        $this->assertNull($booking->saas_invoice_number);

        // First call
        $this->actingAs($customer, 'sanctum')->get("/api/bookings/{$booking->id}/invoice")->assertStatus(200);
        $booking->refresh();

        $turfInv1 = $booking->turf_invoice_number;
        $saasInv1 = $booking->saas_invoice_number;

        $this->assertNotNull($turfInv1);
        $this->assertNotNull($saasInv1);
        $this->assertStringStartsWith('INV-' . $booking->turf_id . '-', $turfInv1);
        $this->assertStringStartsWith('PINV-', $saasInv1);

        // Second call
        $this->actingAs($customer, 'sanctum')->get("/api/bookings/{$booking->id}/invoice")->assertStatus(200);
        $booking->refresh();

        $this->assertEquals($turfInv1, $booking->turf_invoice_number);
        $this->assertEquals($saasInv1, $booking->saas_invoice_number);
    }

    public function test_unauthorized_user_cannot_access_invoice(): void
    {
        [$customer, , $booking] = $this->createBookingFixture();
        $stranger = User::factory()->create();

        $res = $this->actingAs($stranger, 'sanctum')->get("/api/bookings/{$booking->id}/invoice");
        $res->assertStatus(403);
    }

    public function test_multi_date_and_multi_payment_booking_renders_all_entries(): void
    {
        [$customer, , $booking] = $this->createBookingFixture([
            'booking_type' => 'long',
            'second_date' => true,
            'second_payment' => true,
        ]);

        $view = view('invoices.booking-bill', [
            'booking' => $booking->fresh(['bookingDates.bookingSlots.slot', 'payments', 'turf.turfSetting', 'user']),
            'turfSetting' => $booking->turf->turfSetting,
            'saasSetting' => SaasSetting::first(),
            'turfIsGstActive' => true,
            'saasIsGstActive' => true,
            'includeSaasPage' => true,
            'bookingGstSac' => '999652',
        ])->render();

        // Both dates must appear
        foreach ($booking->bookingDates as $bDate) {
            $formattedDate = Carbon::parse($bDate->booking_date)->format('D, d M Y');
            $this->assertStringContainsString($formattedDate, $view);
        }

        // Both payments must appear
        $this->assertStringContainsString('Razorpay Online', $view);
        $this->assertStringContainsString('Cash', $view);
    }

    public function test_sac_code_is_dynamic_from_saas_setting(): void
    {
        SaasSetting::first()->update(['booking_gst_sac' => '999888']);

        [$customer, , $booking] = $this->createBookingFixture();

        $view = view('invoices.booking-bill', [
            'booking' => $booking->fresh(['bookingDates.bookingSlots.slot', 'payments', 'turf.turfSetting', 'user']),
            'turfSetting' => $booking->turf->turfSetting,
            'saasSetting' => SaasSetting::first(),
            'turfIsGstActive' => true,
            'saasIsGstActive' => true,
            'includeSaasPage' => true,
            'bookingGstSac' => '999888',
        ])->render();

        $this->assertStringContainsString('999888', $view);
        $this->assertStringNotContainsString('998413', $view);
    }

    public function test_signed_url_generation_and_download(): void
    {
        [$customer, , $booking] = $this->createBookingFixture();

        $res = $this->actingAs($customer, 'sanctum')->getJson("/api/bookings/{$booking->id}/invoice/signed-url");
        $res->assertStatus(200);
        $res->assertJsonStructure(['url', 'expires_in_minutes']);

        $signedUrl = $res->json('url');

        // Unauthenticated access to the signed URL should succeed
        $downloadRes = $this->get($signedUrl);
        $downloadRes->assertStatus(200);
        $downloadRes->assertHeader('content-type', 'application/pdf');
    }

    public function test_part_payment_bill_renders_exact_amounts_without_proration_discrepancy(): void
    {
        [$customer, , $booking] = $this->createBookingFixture([
            'turf_is_gst' => false,
            'platform_fee' => 10.00,
            'first_payment_amount' => 210.00,
        ]);

        $booking->update([
            'actual_amount' => 800.00,
            'taxable_amount' => 800.00,
            'turf_gst_amount' => 0.00,
            'platform_fee' => 10.00,
            'platform_fee_gst' => 0.00,
            'total_amount' => 810.00,
            'payable_now' => 210.00,
            'balance_amount' => 600.00,
        ]);

        $bDate = $booking->bookingDates()->first();
        $bDate->update([
            'amount' => 810.00,
            'actual_amount' => 800.00,
            'taxable_amount' => 800.00,
            'turf_gst_amount' => 0.00,
            'paid_amount' => 210.00,
            'balance_amount' => 600.00,
        ]);

        $payment = $booking->payments()->first();
        $payment->update([
            'amount' => 210.00,
        ]);

        $view = view('invoices.booking-bill', [
            'booking' => $booking->fresh(['bookingDates.bookingSlots.slot', 'payments', 'turf.turfSetting', 'user']),
            'turfSetting' => $booking->turf->turfSetting,
            'saasSetting' => SaasSetting::first(),
            'turfIsGstActive' => false,
            'saasIsGstActive' => false,
            'includeSaasPage' => true,
            'bookingGstSac' => '999652',
        ])->render();

        $compactView = str_replace(["\n", "\r", ' '], '', $view);

        // Page 1: Slot row amount must be 800.00 (not 810.00)
        $this->assertStringContainsString('₹800.00', $view);
        // Page 1: Total Booking Amount must be 800.00
        $this->assertStringContainsString('TotalBookingAmount:</td><tdclass="text-right">₹800.00</td>', $compactView);
        // Page 1: Total Paid to turf must be 200.00 (not 207.41)
        $this->assertStringContainsString('TotalPaid:</td><tdclass="text-rightfont-bold"style="color:#047857;padding-top:6px;">₹200.00</td>', $compactView);
        // Page 1: Balance Due must be 600.00 (not 592.59)
        $this->assertStringContainsString('BalanceDue:</td><tdclass="text-rightfont-bold"style="color:#b45309;">₹600.00</td>', $compactView);

        // Page 2: Platform Convenience Fee must be 10.00
        $this->assertStringContainsString('TotalPlatformFee:</td><tdclass="text-right">₹10.00</td>', $compactView);
        // Page 2: Paid via Online Payment must be 10.00 (not 2.59)
        $this->assertStringContainsString('PaidviaOnlinePayment:</td><tdclass="text-rightfont-bold"style="color:#047857;padding-top:6px;">₹10.00</td>', $compactView);
        // Page 2: Balance Due must be 0.00 (not 7.41)
        $this->assertStringContainsString('BalanceDue:</td><tdclass="text-rightfont-bold"style="color:#475569;">₹0.00</td>', $compactView);
    }

    public function test_invoice_follows_booking_gst_charge_when_turf_setting_toggled(): void
    {
        [$customer, $turfOwner, $booking, $turfSetting] = $this->createBookingFixture();

        // 1. Booking made with turf GST
        $booking->update([
            'turf_gst_type' => 'excluded',
            'turf_gst_rate' => 18.00,
            'turf_gst_amount' => 180.00,
        ]);

        // Owner disables GST billing afterwards
        $turfSetting->update(['is_gst_billing_active' => false]);

        $flags = \App\Http\Controllers\Api\BookingInvoiceController::invoiceFlags($booking);
        $this->assertTrue($flags['turfIsGstActive']);

        $view = view('invoices.booking-bill', [
            'booking' => $booking->fresh(['bookingDates.bookingSlots.slot', 'payments', 'turf.turfSetting', 'user']),
            'turfSetting' => $turfSetting->fresh(),
            'saasSetting' => SaasSetting::first(),
            'turfIsGstActive' => $flags['turfIsGstActive'],
            'saasIsGstActive' => $flags['saasIsGstActive'],
            'includeSaasPage' => $flags['includeSaasPage'],
            'bookingGstSac' => '999652',
        ])->render();

        $this->assertStringContainsString('TAX INVOICE', $view);
        $this->assertStringContainsString('CGST', $view);
        $this->assertStringContainsString('SGST', $view);

        // 2. Booking made without turf GST
        $booking->update([
            'turf_gst_type' => 'exempt',
            'turf_gst_rate' => 0.00,
            'turf_gst_amount' => 0.00,
            'turf_invoice_number' => null,
        ]);

        // Owner enables GST billing afterwards
        $turfSetting->update(['is_gst_billing_active' => true]);

        $flagsNoGst = \App\Http\Controllers\Api\BookingInvoiceController::invoiceFlags($booking);
        $this->assertFalse($flagsNoGst['turfIsGstActive']);

        $viewReceipt = view('invoices.booking-bill', [
            'booking' => $booking->fresh(['bookingDates.bookingSlots.slot', 'payments', 'turf.turfSetting', 'user']),
            'turfSetting' => $turfSetting->fresh(),
            'saasSetting' => SaasSetting::first(),
            'turfIsGstActive' => $flagsNoGst['turfIsGstActive'],
            'saasIsGstActive' => $flagsNoGst['saasIsGstActive'],
            'includeSaasPage' => $flagsNoGst['includeSaasPage'],
            'bookingGstSac' => '999652',
        ])->render();

        $this->assertStringContainsString('BOOKING RECEIPT', $viewReceipt);
        $this->assertNull($booking->fresh()->turf_invoice_number);
    }

    public function test_invoice_follows_saas_platform_fee_gst_charge_when_saas_setting_toggled(): void
    {
        [$customer, $turfOwner, $booking, $turfSetting] = $this->createBookingFixture();
        $saasSetting = SaasSetting::first();

        // 1. Booking charged with platform fee GST
        $booking->update([
            'platform_fee' => 10.00,
            'platform_fee_gst' => 1.80,
            'platform_fee_cgst' => 0.90,
            'platform_fee_sgst' => 0.90,
            'platform_fee_igst' => 0.00,
        ]);

        // SaaS disables GST billing afterwards
        $saasSetting->update(['is_gst_billing_active' => false]);

        $flags = \App\Http\Controllers\Api\BookingInvoiceController::invoiceFlags($booking);
        $this->assertTrue($flags['saasIsGstActive']);

        $view = view('invoices.booking-bill', [
            'booking' => $booking->fresh(['bookingDates.bookingSlots.slot', 'payments', 'turf.turfSetting', 'user']),
            'turfSetting' => $turfSetting,
            'saasSetting' => $saasSetting->fresh(),
            'turfIsGstActive' => $flags['turfIsGstActive'],
            'saasIsGstActive' => $flags['saasIsGstActive'],
            'includeSaasPage' => $flags['includeSaasPage'],
            'bookingGstSac' => '999652',
        ])->render();

        $this->assertStringContainsString('CGST (9%)', $view);

        // 2. Booking charged without platform fee GST
        $booking->update([
            'platform_fee' => 10.00,
            'platform_fee_gst' => 0.00,
            'platform_fee_cgst' => 0.00,
            'platform_fee_sgst' => 0.00,
            'platform_fee_igst' => 0.00,
        ]);

        // SaaS enables GST billing afterwards
        $saasSetting->update(['is_gst_billing_active' => true]);

        $flagsNoGst = \App\Http\Controllers\Api\BookingInvoiceController::invoiceFlags($booking);
        $this->assertFalse($flagsNoGst['saasIsGstActive']);

        $viewReceipt = view('invoices.booking-bill', [
            'booking' => $booking->fresh(['bookingDates.bookingSlots.slot', 'payments', 'turf.turfSetting', 'user']),
            'turfSetting' => $turfSetting,
            'saasSetting' => $saasSetting->fresh(),
            'turfIsGstActive' => $flagsNoGst['turfIsGstActive'],
            'saasIsGstActive' => $flagsNoGst['saasIsGstActive'],
            'includeSaasPage' => $flagsNoGst['includeSaasPage'],
            'bookingGstSac' => '999652',
        ])->render();

        $this->assertStringNotContainsString('CGST (9%)', $viewReceipt);
    }

    public function test_turf_with_no_state_does_not_print_made_up_state(): void
    {
        [$customer, $turfOwner, $booking, $turfSetting] = $this->createBookingFixture();
        $turfSetting->update([
            'state' => null,
            'state_code' => null,
            'gst_number' => null,
        ]);

        $booking->turf->unsetRelation('turfSetting');
        $booking->turf->unsetRelation('setting');

        $turfBill = view('invoices.partials.turf-bill', [
            'booking' => $booking->fresh(['bookingDates.bookingSlots.slot', 'payments', 'turf.turfSetting', 'user']),
            'turfSetting' => $turfSetting->fresh(),
            'isGst' => true,
            'bookingGstSac' => '999652',
        ])->render();

        // Must not print made-up 'Maharashtra' or '(27)' on turf bill
        $this->assertStringNotContainsString('Maharashtra', $turfBill);
        $this->assertStringNotContainsString('(27)', $turfBill);
        $this->assertStringNotContainsString('State:', $turfBill);
        $this->assertStringNotContainsString('Place of Supply:', $turfBill);

        // Entire booking invoice without SaaS bill should not contain Maharashtra or (27)
        $viewNoSaas = view('invoices.booking-bill', [
            'booking' => $booking->fresh(['bookingDates.bookingSlots.slot', 'payments', 'turf.turfSetting', 'user']),
            'turfSetting' => $turfSetting->fresh(),
            'saasSetting' => SaasSetting::first(),
            'turfIsGstActive' => true,
            'saasIsGstActive' => false,
            'includeSaasPage' => false,
            'bookingGstSac' => '999652',
        ])->render();

        $this->assertStringNotContainsString('Maharashtra', $viewNoSaas);
        $this->assertStringNotContainsString('(27)', $viewNoSaas);
    }

    public function test_place_of_supply_and_rates_on_platform_fee_invoice(): void
    {
        // 1. Cross-state booking (turf 29)
        [$customer, $turfOwner, $booking, $turfSetting] = $this->createBookingFixture([
            'turf_state' => 'Karnataka',
            'turf_state_code' => '29',
            'turf_gst_number' => '29AAACH1234A1Z1',
        ]);
        $booking->update([
            'platform_fee' => 10.00,
            'platform_fee_gst' => 1.80,
            'platform_fee_cgst' => 0.00,
            'platform_fee_sgst' => 0.00,
            'platform_fee_igst' => 1.80,
        ]);

        $viewCross = view('invoices.booking-bill', [
            'booking' => $booking->fresh(['bookingDates.bookingSlots.slot', 'payments', 'turf.turfSetting', 'user']),
            'turfSetting' => $turfSetting->fresh(),
            'saasSetting' => SaasSetting::first(),
            'turfIsGstActive' => true,
            'saasIsGstActive' => true,
            'includeSaasPage' => true,
            'bookingGstSac' => '999652',
        ])->render();

        $this->assertStringContainsString('Place of Supply: <strong>Karnataka (29)</strong>', $viewCross);
        $this->assertStringContainsString('IGST (18%)', $viewCross);

        // 2. Intra-state booking (turf 27, SaaS 27)
        [$customerIntra, $turfOwnerIntra, $bookingIntra, $turfSettingIntra] = $this->createBookingFixture([
            'turf_state' => 'Maharashtra',
            'turf_state_code' => '27',
            'turf_gst_number' => '27AAACH1234A1Z1',
        ]);
        $bookingIntra->update([
            'platform_fee' => 10.00,
            'platform_fee_gst' => 1.80,
            'platform_fee_cgst' => 0.90,
            'platform_fee_sgst' => 0.90,
            'platform_fee_igst' => 0.00,
        ]);

        $viewIntra = view('invoices.booking-bill', [
            'booking' => $bookingIntra->fresh(['bookingDates.bookingSlots.slot', 'payments', 'turf.turfSetting', 'user']),
            'turfSetting' => $turfSettingIntra->fresh(),
            'saasSetting' => SaasSetting::first(),
            'turfIsGstActive' => true,
            'saasIsGstActive' => true,
            'includeSaasPage' => true,
            'bookingGstSac' => '999652',
        ])->render();

        $this->assertStringContainsString('Place of Supply: <strong>Maharashtra (27)</strong>', $viewIntra);
        $this->assertStringContainsString('CGST (9%)', $viewIntra);

        // 3. Dynamic rate (booking_gst_percentage = 12)
        $saasSetting = SaasSetting::first();
        $saasSetting->update(['booking_gst_percentage' => 12.00]);

        $view12Pct = view('invoices.booking-bill', [
            'booking' => $booking->fresh(['bookingDates.bookingSlots.slot', 'payments', 'turf.turfSetting', 'user']),
            'turfSetting' => $turfSetting->fresh(),
            'saasSetting' => $saasSetting->fresh(),
            'turfIsGstActive' => true,
            'saasIsGstActive' => true,
            'includeSaasPage' => true,
            'bookingGstSac' => '999652',
        ])->render();

        $this->assertStringContainsString('IGST (12%)', $view12Pct);

        $view12PctIntra = view('invoices.booking-bill', [
            'booking' => $bookingIntra->fresh(['bookingDates.bookingSlots.slot', 'payments', 'turf.turfSetting', 'user']),
            'turfSetting' => $turfSettingIntra->fresh(),
            'saasSetting' => $saasSetting->fresh(),
            'turfIsGstActive' => true,
            'saasIsGstActive' => true,
            'includeSaasPage' => true,
            'bookingGstSac' => '999652',
        ])->render();

        $this->assertStringContainsString('CGST (6%)', $view12PctIntra);
    }
}
