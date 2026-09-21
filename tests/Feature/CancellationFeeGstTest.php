<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\BookingDate;
use App\Models\Location;
use App\Models\Payment;
use App\Models\SaasSetting;
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

    public function test_turf_cancellation_fee_total_unchanged_by_turf_gst_toggle(): void
    {
        $calculator = new CancellationFeeCalculator();

        $this->turf->turfSetting()->update(['is_gst_billing_active' => false]);
        $resOff = $calculator->calculate($this->turf->fresh(), 500.00, 0.00, 1);

        $this->turf->turfSetting()->update(['is_gst_billing_active' => true, 'gst_percentage' => 18.00]);
        $resOn = $calculator->calculate($this->turf->fresh(), 500.00, 0.00, 1);

        $this->assertEquals($resOff['turf_fee'], $resOn['turf_fee']); // total retained unchanged (100.00)
        $this->assertEquals(0.00, $resOff['turf_fee_gst']);
        $this->assertGreaterThan(0.00, $resOn['turf_fee_gst']);
        $this->assertEquals($resOn['turf_fee'], round($resOn['turf_fee_base'] + $resOn['turf_fee_gst'], 2));
        $this->assertEquals($resOff['total_deductions'], $resOn['total_deductions']);
        $this->assertEquals($resOff['refund_amount'], $resOn['refund_amount']);
    }

    public function test_saas_cancellation_fee_total_unchanged_by_saas_gst_toggle(): void
    {
        $calculator = new CancellationFeeCalculator();

        SaasSetting::first()->update(['is_gst_billing_active' => false, 'cancellation_fee_percentage' => 5.00]);
        $resOff = $calculator->calculate($this->turf, 500.00, 0.00, 1);

        SaasSetting::first()->update(['is_gst_billing_active' => true, 'booking_gst_percentage' => 18.00]);
        $resOn = $calculator->calculate($this->turf, 500.00, 0.00, 1);

        $this->assertEquals($resOff['saas_fee'], $resOn['saas_fee']); // total retained unchanged (25.00)
        $this->assertEquals(0.00, $resOff['saas_fee_gst']);
        $this->assertGreaterThan(0.00, $resOn['saas_fee_gst']);
        $this->assertEquals($resOn['saas_fee'], round($resOn['saas_fee_base'] + $resOn['saas_fee_gst'], 2));
        $this->assertEquals($resOff['total_deductions'], $resOn['total_deductions']);
        $this->assertEquals($resOff['refund_amount'], $resOn['refund_amount']);
    }

    public function test_cancellation_service_persists_gst_breakdown_on_cancellation(): void
    {
        SaasSetting::first()->update(['is_gst_billing_active' => true, 'booking_gst_percentage' => 18.00]);
        $this->turf->turfSetting()->update(['is_gst_billing_active' => true, 'gst_percentage' => 18.00]);

        $booking = Booking::create([
            'booking_number' => 'TB-TEST-001',
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

        $bookingDate = BookingDate::create([
            'booking_id' => $booking->id,
            'booking_date' => now()->addDays(2)->format('Y-m-d'),
            'status' => 'Confirmed',
            'amount' => 500.00,
            'paid_amount' => 500.00,
            'balance_amount' => 0.00,
            'payment_status' => 'Paid',
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $bookingDate->id,
            'payment_method' => 'App',
            'amount' => 500.00,
            'status' => 'Success',
        ]);

        $service = new BookingCancellationService();
        $result = $service->cancelBookingDates($booking, [$bookingDate->id], $this->customer, 'Customer changed plans');

        $this->assertTrue($result['success']);
        $cancellation = BookingCancellation::where('booking_date_id', $bookingDate->id)->first();
        $this->assertNotNull($cancellation);

        // Retained fees
        $this->assertEquals(100.00, (float)$cancellation->turf_cancellation_fee);
        $this->assertEquals(25.00, (float)$cancellation->saas_cancellation_fee);
        $this->assertEquals(125.00, (float)$cancellation->total_cancellation_fee);
        $this->assertEquals(375.00, (float)$cancellation->refund_amount);

        // Extracted GSTs
        $this->assertGreaterThan(0.00, (float)$cancellation->turf_fee_gst_amount);
        $this->assertGreaterThan(0.00, (float)$cancellation->saas_fee_gst_amount);
        $this->assertEquals((float)$cancellation->turf_fee_gst_amount, round((float)$cancellation->turf_fee_cgst_amount + (float)$cancellation->turf_fee_sgst_amount, 2));
        $this->assertEquals((float)$cancellation->saas_fee_gst_amount, round((float)$cancellation->saas_fee_cgst_amount + (float)$cancellation->saas_fee_sgst_amount, 2));
    }
}
