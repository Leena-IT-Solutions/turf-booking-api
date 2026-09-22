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

        $slotCategory = SlotCategory::create([
            'turf_id' => $turf->id,
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
}
