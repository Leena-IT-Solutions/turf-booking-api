<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingCancellation;
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
use App\Services\BookingCancellationService;
use App\Services\CancellationFeeCalculator;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancellationFeeGstTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $turfAdmin;
    protected Turf $turf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        SaasSetting::create([
            'commission_percentage' => 7.00,
            'cancellation_fee_percentage' => 5.00,
            'booking_gst_percentage' => 18.00,
            'state_code' => '27',
        ]);

        $this->customer = User::factory()->create();
        $this->customer->assignRole('customer');

        $this->turfAdmin = User::factory()->create();
        $this->turfAdmin->assignRole('turf-admin');

        $location = Location::create([
            'user_id' => $this->turfAdmin->id,
            'name' => 'City Arena',
            'address' => 'Marine Lines, Mumbai',
        ]);

        $this->turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Turf 1',
            'type' => 'Football',
            'is_cancellation_active' => true,
            'cancellation_hours' => 12,
            'cancellation_fee' => 100.00,
        ]);

        TurfSetting::create([
            'turf_id' => $this->turf->id,
            'is_gst_billing_active' => false,
            'gst_percentage' => 18.00,
            'state_code' => '27',
        ]);
    }

    protected function createBookingFixture(array $dateOverrides = []): array
    {
        $booking = Booking::create([
            'booking_number' => 'TB-' . uniqid(),
            'user_id' => $this->customer->id,
            'turf_id' => $this->turf->id,
            'date_of_booking' => now(),
            'booking_type' => 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
            'total_amount' => 500.00,
            'platform_fee' => 0.00,
            'platform_fee_gst' => 0.00,
        ]);

        $bDate = BookingDate::create(array_merge([
            'booking_id' => $booking->id,
            'booking_date' => now()->addDays(2)->format('Y-m-d'),
            'status' => 'Confirmed',
            'amount' => 500.00,
            'paid_amount' => 500.00,
            'balance_amount' => 0.00,
            'taxable_amount' => 423.50,
            'turf_gst_amount' => 76.50,
            'payment_status' => 'Paid',
        ], $dateOverrides));

        Payment::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $bDate->id,
            'payment_method' => 'App',
            'amount' => 500.00,
            'status' => 'Success',
        ]);

        return [$booking, $bDate];
    }

    public function test_turf_cancellation_fee_total_unchanged_by_turf_gst_toggle(): void
    {
        [, $bDate] = $this->createBookingFixture();
        $calculator = new CancellationFeeCalculator();

        $this->turf->turfSetting()->update(['is_gst_billing_active' => false]);
        $resOff = $calculator->calculate($this->turf->fresh(), $bDate, 500.00, 0.00);

        $this->turf->turfSetting()->update(['is_gst_billing_active' => true, 'gst_percentage' => 18.00]);
        $resOn = $calculator->calculate($this->turf->fresh(), $bDate, 500.00, 0.00);

        $this->assertEquals($resOff['turf_fee'], $resOn['turf_fee']); // total retained unchanged (100.00)
        $this->assertEquals(0.00, $resOff['turf_fee_gst']);
        $this->assertGreaterThan(0.00, $resOn['turf_fee_gst']);
        $this->assertEquals($resOn['turf_fee'], round($resOn['turf_fee_base'] + $resOn['turf_fee_gst'], 2));
        $this->assertEquals($resOff['total_deductions'], $resOn['total_deductions']);
        $this->assertEquals($resOff['refund_amount'], $resOn['refund_amount']);
    }

    public function test_saas_cancellation_fee_total_unchanged_by_saas_gst_toggle(): void
    {
        [, $bDate] = $this->createBookingFixture();
        $calculator = new CancellationFeeCalculator();

        SaasSetting::first()->update(['is_gst_billing_active' => false, 'cancellation_fee_percentage' => 5.00]);
        $resOff = $calculator->calculate($this->turf, $bDate, 500.00, 0.00);

        SaasSetting::first()->update(['is_gst_billing_active' => true, 'booking_gst_percentage' => 18.00]);
        $resOn = $calculator->calculate($this->turf, $bDate, 500.00, 0.00);

        $this->assertEquals($resOff['saas_fee'], $resOn['saas_fee']); // total retained unchanged (25.00)
        $this->assertEquals(0.00, $resOff['saas_fee_gst']);
        $this->assertGreaterThan(0.00, $resOn['saas_fee_gst']);
        $this->assertEquals($resOn['saas_fee'], round($resOn['saas_fee_base'] + $resOn['saas_fee_gst'], 2));
        $this->assertEquals($resOff['total_deductions'], $resOn['total_deductions']);
        $this->assertEquals($resOff['refund_amount'], $resOn['refund_amount']);
    }

    public function test_refund_amount_gst_breakup_matches_original_booking_gst_ratio(): void
    {
        [, $bDate] = $this->createBookingFixture([
            'taxable_amount' => 423.50,
            'turf_gst_amount' => 76.50,
        ]);

        $calculator = new CancellationFeeCalculator();
        $breakup = $calculator->refundGstBreakup($bDate, 200.00);

        // The refund carries the SAME GST ratio as the original sale (76.50 / 500.00 = 15.3%).
        $expectedGst = round(200.00 * (76.50 / 500.00), 2);
        $this->assertEquals($expectedGst, $breakup['gst']);
        $this->assertEquals(round(200.00 - $expectedGst, 2), $breakup['taxable']);
        $this->assertEquals($breakup['gst'], round($breakup['cgst'] + $breakup['sgst'], 2));

        // Full refund (refunding the entire turf total) reproduces the original GST split exactly.
        $fullBreakup = $calculator->refundGstBreakup($bDate, 500.00);
        $this->assertEquals(76.50, $fullBreakup['gst']);
        $this->assertEquals(423.50, $fullBreakup['taxable']);

        // Zero refund -> zero GST breakdown, not an error.
        $zeroBreakup = $calculator->refundGstBreakup($bDate, 0.00);
        $this->assertEquals(0.00, $zeroBreakup['gst']);
        $this->assertEquals(0.00, $zeroBreakup['taxable']);
    }

    public function test_cancellation_service_persists_all_11_gst_breakdown_columns_on_cancellation(): void
    {
        SaasSetting::first()->update(['is_gst_billing_active' => true, 'booking_gst_percentage' => 18.00]);
        $this->turf->turfSetting()->update(['is_gst_billing_active' => true, 'gst_percentage' => 18.00]);

        [$booking, $bookingDate] = $this->createBookingFixture([
            'taxable_amount' => 423.50,
            'turf_gst_amount' => 76.50,
        ]);

        $service = new BookingCancellationService();
        $result = $service->cancelBookingDates($booking, [$bookingDate->id], $this->customer, 'Customer changed plans');

        $this->assertTrue($result['success']);
        $cancellation = BookingCancellation::where('booking_date_id', $bookingDate->id)->first();
        $this->assertNotNull($cancellation);

        // Retained fee totals unchanged
        $this->assertEquals(100.00, (float)$cancellation->turf_cancellation_fee);
        $this->assertEquals(25.00, (float)$cancellation->saas_cancellation_fee);
        $this->assertEquals(125.00, (float)$cancellation->total_cancellation_fee);
        $this->assertEquals(375.00, (float)$cancellation->refund_amount);

        // Fee GST breakdown
        $this->assertGreaterThan(0.00, (float)$cancellation->turf_fee_gst_amount);
        $this->assertGreaterThan(0.00, (float)$cancellation->saas_fee_gst_amount);
        $this->assertEquals((float)$cancellation->turf_fee_gst_amount, round((float)$cancellation->turf_fee_cgst_amount + (float)$cancellation->turf_fee_sgst_amount, 2));
        $this->assertEquals((float)$cancellation->saas_fee_gst_amount, round((float)$cancellation->saas_fee_cgst_amount + (float)$cancellation->saas_fee_sgst_amount, 2));

        // Refund GST breakdown (375.00 * (76.50 / 500.00) = 57.38)
        $expectedRefundGst = round(375.00 * (76.50 / 500.00), 2);
        $expectedRefundTaxable = round(375.00 - $expectedRefundGst, 2);
        $this->assertEquals($expectedRefundGst, (float)$cancellation->refund_gst_amount);
        $this->assertEquals($expectedRefundTaxable, (float)$cancellation->refund_taxable_amount);
        $this->assertEquals((float)$cancellation->refund_gst_amount, round((float)$cancellation->refund_cgst_amount + (float)$cancellation->refund_sgst_amount, 2));
    }

    public function test_resolve_refund_recomputes_refund_gst_breakup_for_resolution_modes(): void
    {
        SaasSetting::first()->update(['is_gst_billing_active' => true, 'booking_gst_percentage' => 18.00]);
        $this->turf->turfSetting()->update(['is_gst_billing_active' => true, 'gst_percentage' => 18.00]);

        [$booking, $bookingDate] = $this->createBookingFixture([
            'taxable_amount' => 423.50,
            'turf_gst_amount' => 76.50,
        ]);

        $service = new BookingCancellationService();
        $service->cancelBookingDates($booking, [$bookingDate->id], $this->customer, 'Cancelled');
        $cancellation = BookingCancellation::where('booking_date_id', $bookingDate->id)->first();

        // 1. Full Compensation: should refund 500.00 with full 76.50 GST
        $res = $service->resolveRefund(
            $cancellation,
            'full_compensation',
            'offline',
            $this->turfAdmin
        );

        $this->assertTrue($res['success']);
        $cancellation->refresh();
        $this->assertEquals(500.00, (float)$cancellation->refund_amount);
        $this->assertEquals(76.50, (float)$cancellation->refund_gst_amount);
        $this->assertEquals(423.50, (float)$cancellation->refund_taxable_amount);
        $this->assertEquals(0.00, (float)$cancellation->total_cancellation_fee);

        // 2. Custom amount on a new cancellation
        [$booking2, $bookingDate2] = $this->createBookingFixture([
            'taxable_amount' => 423.50,
            'turf_gst_amount' => 76.50,
        ]);
        $service->cancelBookingDates($booking2, [$bookingDate2->id], $this->customer, 'Cancelled');
        $cancellation2 = BookingCancellation::where('booking_date_id', $bookingDate2->id)->first();

        $res2 = $service->resolveRefund(
            $cancellation2,
            'custom',
            'offline',
            $this->turfAdmin,
            customAmount: 200.00
        );
        $this->assertTrue($res2['success']);
        $cancellation2->refresh();
        $this->assertEquals(200.00, (float)$cancellation2->refund_amount);
        $expectedGst2 = round(200.00 * (76.50 / 500.00), 2); // 30.60
        $this->assertEquals($expectedGst2, (float)$cancellation2->refund_gst_amount);
        $this->assertEquals(round(200.00 - $expectedGst2, 2), (float)$cancellation2->refund_taxable_amount);

        // 3. No refund on a new cancellation
        [$booking3, $bookingDate3] = $this->createBookingFixture([
            'taxable_amount' => 423.50,
            'turf_gst_amount' => 76.50,
        ]);
        $service->cancelBookingDates($booking3, [$bookingDate3->id], $this->customer, 'Cancelled');
        $cancellation3 = BookingCancellation::where('booking_date_id', $bookingDate3->id)->first();

        $res3 = $service->resolveRefund(
            $cancellation3,
            'no_refund',
            'offline',
            $this->turfAdmin
        );
        $this->assertTrue($res3['success']);
        $cancellation3->refresh();
        $this->assertEquals(0.00, (float)$cancellation3->refund_amount);
        $this->assertEquals(0.00, (float)$cancellation3->refund_gst_amount);
        $this->assertEquals(0.00, (float)$cancellation3->refund_taxable_amount);
    }
}
