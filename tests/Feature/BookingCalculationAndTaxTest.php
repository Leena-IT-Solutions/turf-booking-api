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
use App\Services\BookingPricingCalculator;
use App\Services\CommissionCalculator;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCalculationAndTaxTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customer;
    protected Location $location;
    protected Turf $turf;
    protected Slot $slot1;
    protected Slot $slot2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['commission_wallet_balance' => 0.00]);
        $this->admin->assignRole('turf-admin');

        $this->customer = User::factory()->create();
        $this->customer->assignRole('customer');

        $this->location = Location::create([
            'user_id' => $this->admin->id,
            'name' => 'Mumbai Arena',
            'address' => 'Ghatkopar East',
        ]);

        $this->turf = Turf::create([
            'location_id' => $this->location->id,
            'name' => 'Apex Pro Arena',
            'type' => 'Synthetic',
            'is_booking_open' => true,
            'booking_open_days' => 30,
            'is_online_payment_active' => true,
            'is_pay_at_location_active' => true,
            'is_part_payment_active' => true,
            'part_payment_type' => 'percentage',
            'part_payment_value' => 50, // 50%
            'is_cancellation_active' => true,
            'cancellation_hours' => 12,
            'cancellation_fee' => 100.00,
        ]);

        $category = SlotCategory::create([
            'name' => 'Evening',
            'is_active' => true,
        ]);

        $this->slot1 = Slot::create([
            'slot_category_id' => $category->id,
            'from_time' => '18:00:00',
            'to_time' => '19:00:00',
            'duration' => 60,
        ]);

        $this->slot2 = Slot::create([
            'slot_category_id' => $category->id,
            'from_time' => '19:00:00',
            'to_time' => '20:00:00',
            'duration' => 60,
        ]);

        $this->turf->slots()->attach($this->slot1->id, [
            'is_active' => true,
            'mon' => 500.00,
            'tue' => 500.00,
            'wed' => 500.00,
            'thu' => 500.00,
            'fri' => 500.00,
            'sat' => 500.00,
            'sun' => 500.00,
        ]);

        $this->turf->slots()->attach($this->slot2->id, [
            'is_active' => true,
            'mon' => 500.00,
            'tue' => 500.00,
            'wed' => 500.00,
            'thu' => 500.00,
            'fri' => 500.00,
            'sat' => 500.00,
            'sun' => 500.00,
        ]);

        // Default Saas Settings
        SaasSetting::updateOrCreate(['id' => 1], [
            'app_name' => 'TurfBooking',
            'min_slots_booking' => 2,
            'platform_fee' => 10.00,
            'is_gst_billing_active' => true,
            'booking_gst_percentage' => 18.00,
            'commission_gst_percentage' => 18.00,
            'commission_percentage' => 7.00,
            'cancellation_fee_percentage' => 5.00,
            'state_code' => '27', // Maharashtra
        ]);

        // Default Turf Settings
        TurfSetting::updateOrCreate(['turf_id' => $this->turf->id], [
            'company_name' => 'Apex Pro Sports LLP',
            'state_code' => '27', // Maharashtra (Intra-state with SaaS)
            'is_gst_billing_active' => true,
            'gst_pricing_type' => 'included',
            'gst_percentage' => 18.00,
        ]);
    }

    public function test_sequential_booking_number_generation(): void
    {
        $num1 = Booking::generateBookingNumber();
        $this->assertStringStartsWith('TB-' . date('Ym') . '-', $num1);

        $booking1 = Booking::create([
            'user_id' => $this->customer->id,
            'turf_id' => $this->turf->id,
            'date_of_booking' => now(),
            'booking_type' => 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
        ]);

        $this->assertEquals($num1, $booking1->booking_number);

        $booking2 = Booking::create([
            'user_id' => $this->customer->id,
            'turf_id' => $this->turf->id,
            'date_of_booking' => now(),
            'booking_type' => 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
        ]);

        $this->assertNotEquals($booking1->booking_number, $booking2->booking_number);
        $expectedSeq2 = 'TB-' . date('Ym') . '-00002';
        $this->assertEquals($expectedSeq2, $booking2->booking_number);
    }

    public function test_pre_booking_guardrails_maintenance_mode(): void
    {
        $saas = SaasSetting::first();
        $saas->update(['is_maintenance_mode' => true]);

        $calculator = new BookingPricingCalculator();

        // Customer attempt should fail with 503
        $result = $calculator->validateGuardrails(
            $this->turf,
            $this->customer,
            [now()->addDays(2)->format('Y-m-d')],
            [$this->slot1->id, $this->slot2->id],
            'App'
        );
        $this->assertFalse($result['valid']);
        $this->assertEquals(503, $result['status_code']);

        // SaaS Admin attempt should succeed
        $saasAdmin = User::factory()->create();
        $saasAdmin->assignRole('saas-admin');
        $adminResult = $calculator->validateGuardrails(
            $this->turf,
            $saasAdmin,
            [now()->addDays(2)->format('Y-m-d')],
            [$this->slot1->id, $this->slot2->id],
            'App'
        );
        $this->assertTrue($adminResult['valid']);
    }

    public function test_turf_gst_included_calculation(): void
    {
        $this->turf->setting->update([
            'is_gst_billing_active' => true,
            'gst_pricing_type' => 'included',
            'gst_percentage' => 18.00,
        ]);

        // Net slot base ₹1180.00
        $calculator = new BookingPricingCalculator();
        $dateItems = [
            [
                'date' => '2026-09-20',
                'subtotal' => 1180.00,
                'coupon_discount' => 0.00,
                'additional_discount' => 0.00,
            ]
        ];

        $res = $calculator->calculatePricing($this->turf, $dateItems, 0.00, 'App', 'full');

        // Taxable amount should be 1180 / 1.18 = 1000.00
        $this->assertEquals(1000.00, $res['taxable_amount']);
        $this->assertEquals(180.00, $res['turf_gst_amount']);
        $this->assertEquals(90.00, $res['turf_cgst_amount']);
        $this->assertEquals(90.00, $res['turf_sgst_amount']);
        $this->assertEquals(1180.00, $res['turf_total']);
    }

    public function test_turf_gst_excluded_calculation(): void
    {
        $this->turf->setting->update([
            'is_gst_billing_active' => true,
            'gst_pricing_type' => 'excluded',
            'gst_percentage' => 18.00,
        ]);

        // Net slot base ₹1000.00
        $calculator = new BookingPricingCalculator();
        $dateItems = [
            [
                'date' => '2026-09-20',
                'subtotal' => 1000.00,
                'coupon_discount' => 0.00,
                'additional_discount' => 0.00,
            ]
        ];

        $res = $calculator->calculatePricing($this->turf, $dateItems, 0.00, 'App', 'full');

        // Taxable amount should be 1000.00, GST 180.00, Total 1180.00
        $this->assertEquals(1000.00, $res['taxable_amount']);
        $this->assertEquals(180.00, $res['turf_gst_amount']);
        $this->assertEquals(90.00, $res['turf_cgst_amount']);
        $this->assertEquals(90.00, $res['turf_sgst_amount']);
        $this->assertEquals(1180.00, $res['turf_total']);
    }

    public function test_platform_fee_saas_gst_inter_vs_intra_state(): void
    {
        $calculator = new BookingPricingCalculator();
        $dateItems = [
            [
                'date' => '2026-09-20',
                'subtotal' => 1000.00,
                'coupon_discount' => 0.00,
                'additional_discount' => 0.00,
            ]
        ];

        // 1. Same state (Intra-state: SaaS '27', Turf '27')
        $resIntra = $calculator->calculatePricing($this->turf, $dateItems, 0.00, 'App', 'full');
        $this->assertEquals(8.47, $resIntra['platform_fee']);
        $this->assertEquals(1.53, $resIntra['platform_fee_gst']);
        $this->assertEquals(0.77, $resIntra['platform_fee_cgst']);
        $this->assertEquals(0.76, $resIntra['platform_fee_sgst']);
        $this->assertEquals(0.00, $resIntra['platform_fee_igst']);
        $this->assertEquals(10.00, round($resIntra['platform_fee'] + $resIntra['platform_fee_gst'], 2));

        // 2. Different state (Inter-state: SaaS '27', Turf '29' Karnataka)
        $this->turf->setting->update(['state_code' => '29']);
        $resInter = $calculator->calculatePricing($this->turf, $dateItems, 0.00, 'App', 'full');
        $this->assertEquals(8.47, $resInter['platform_fee']);
        $this->assertEquals(1.53, $resInter['platform_fee_gst']);
        $this->assertEquals(1.53, $resInter['platform_fee_igst']);
        $this->assertEquals(0.00, $resInter['platform_fee_cgst']);
        $this->assertEquals(0.00, $resInter['platform_fee_sgst']);
        $this->assertEquals(10.00, round($resInter['platform_fee'] + $resInter['platform_fee_gst'], 2));
    }

    public function test_one_paisa_rounding_guardrail(): void
    {
        $calc = new CommissionCalculator();
        SaasSetting::first()->update([
            'is_gst_billing_active' => true,
            'commission_gst_percentage' => 18.00,
            'state_code' => '27',
        ]);
        $this->turf->setting->update(['state_code' => '27']);

        $amount = 1429.35;
        $result = $calc->calculate($this->turf, 'App', $amount);

        // General 1-paisa rule check
        $this->assertEquals(
            $result['commission_gst_amount'],
            round($result['commission_cgst_amount'] + $result['commission_sgst_amount'], 2)
        );
    }

    public function test_part_payment_split_and_pro_rated_distribution(): void
    {
        $this->turf->update([
            'is_part_payment_active' => true,
            'part_payment_type' => 'percentage',
            'part_payment_value' => 50,
        ]);

        $calculator = new BookingPricingCalculator();
        $dateItems = [
            ['date' => '2026-09-20', 'subtotal' => 600.00, 'coupon_discount' => 0.00, 'additional_discount' => 0.00],
            ['date' => '2026-09-21', 'subtotal' => 400.00, 'coupon_discount' => 0.00, 'additional_discount' => 0.00],
        ];

        $res = $calculator->calculatePricing($this->turf, $dateItems, 0.00, 'App', 'part');

        $this->assertTrue($res['is_part_payment']);
        // Grand total = 1000 + 10 (platform fee, inclusive of GST) = 1010.00
        $this->assertEquals(1010.00, $res['total_amount']);
        $this->assertEquals(505.00, $res['payable_now']);
        $this->assertEquals(505.00, $res['balance_amount']);

        // Verify dates pro-rated sum equals total payable_now
        $paidSum = array_sum(array_column($res['dates'], 'paid_amount'));
        $balanceSum = array_sum(array_column($res['dates'], 'balance_amount'));
        $this->assertEquals(505.00, round($paidSum, 2));
        $this->assertEquals(505.00, round($balanceSum, 2));
    }

    public function test_flat_part_payment_per_slot_plus_platform_fee(): void
    {
        $this->turf->update([
            'is_part_payment_active' => true,
            'part_payment_type' => 'flat',
            'part_payment_value' => 5.00, // 5 Rs per slot
        ]);

        $calculator = new BookingPricingCalculator();
        $dateItems = [
            [
                'date' => '2026-09-21',
                'subtotal' => 2000.00,
                'coupon_discount' => 0.00,
                'additional_discount' => 0.00,
                'slots' => [101, 102, 103, 104], // 4 slots
            ],
        ];

        $res = $calculator->calculatePricing($this->turf, $dateItems, 0.00, 'App', 'part');

        $this->assertTrue($res['is_part_payment']);
        // 4 slots * 5 Rs = 20 Rs deposit + 10 Rs platform fee (GST inclusive) = 30.00 Rs
        $platformFeeTotal = $res['platform_fee'] + $res['platform_fee_gst'];
        $expectedPayableNow = round(20.00 + $platformFeeTotal, 2);

        $this->assertEquals($expectedPayableNow, $res['payable_now']);
        $this->assertEquals(round($res['total_amount'] - $expectedPayableNow, 2), $res['balance_amount']);
    }

    public function test_platform_fee_total_unchanged_by_saas_gst_toggle(): void
    {
        SaasSetting::first()->update(['platform_fee' => 2.00]);

        $calculator = new BookingPricingCalculator();
        $dateItems = [
            ['date' => '2026-09-20', 'subtotal' => 10.00, 'coupon_discount' => 0.00, 'additional_discount' => 0.00],
        ];

        SaasSetting::first()->update(['is_gst_billing_active' => false]);
        $resGstOff = $calculator->calculatePricing($this->turf, $dateItems, 0.00, 'App', 'full');

        SaasSetting::first()->update(['is_gst_billing_active' => true, 'booking_gst_percentage' => 18.00]);
        $resGstOn = $calculator->calculatePricing($this->turf, $dateItems, 0.00, 'App', 'full');

        // Total platform fee charged must be identical regardless of the GST toggle.
        $this->assertEquals(2.00, round($resGstOff['platform_fee'] + $resGstOff['platform_fee_gst'], 2));
        $this->assertEquals(2.00, round($resGstOn['platform_fee'] + $resGstOn['platform_fee_gst'], 2));

        $this->assertEquals(2.00, $resGstOff['platform_fee']);
        $this->assertEquals(0.00, $resGstOff['platform_fee_gst']);
        $this->assertLessThan(2.00, $resGstOn['platform_fee']);
        $this->assertGreaterThan(0.00, $resGstOn['platform_fee_gst']);
    }

    public function test_commission_total_unchanged_by_saas_gst_toggle(): void
    {
        SaasSetting::first()->update(['commission_percentage' => 8.00]);
        $calculator = new CommissionCalculator();
        $taxableBase = 10.00; // e.g. 2 slots @ Rs 5

        SaasSetting::first()->update(['is_gst_billing_active' => false]);
        $resOff = $calculator->calculate($this->turf, 'App', $taxableBase);

        SaasSetting::first()->update(['is_gst_billing_active' => true, 'commission_gst_percentage' => 18.00]);
        $resOn = $calculator->calculate($this->turf, 'App', $taxableBase);

        // Expected total: 8% of Rs 10 = Rs 0.80, pegged regardless of GST toggle.
        $this->assertEquals(0.80, round($resOff['commission_amount'] + $resOff['commission_gst_amount'], 2));
        $this->assertEquals(0.80, round($resOn['commission_amount'] + $resOn['commission_gst_amount'], 2));
        $this->assertEquals($resOff['total_commission_deduction'], $resOn['total_commission_deduction']);

        $this->assertEquals(0.80, $resOff['commission_amount']);
        $this->assertEquals(0.00, $resOff['commission_gst_amount']);
        $this->assertLessThan(0.80, $resOn['commission_amount']);
        $this->assertGreaterThan(0.00, $resOn['commission_gst_amount']);
    }

    public function test_booking_creation_and_database_persistence(): void
    {
        $dateStr = now()->addDays(2)->format('Y-m-d');

        $payload = [
            'slot_ids' => [$this->slot1->id, $this->slot2->id],
            'booking_dates' => [$dateStr],
            'booking_type' => 'day',
            'payment_method' => 'Cash',
            'customer_gstin' => '27AAECP1234F1Z5',
            'customer_company_name' => 'Corporate Sports Club',
        ];

        $response = $this->actingAs($this->customer)->postJson("/api/turfs/{$this->turf->id}/bookings", $payload);
        $response->assertOk();

        $this->assertDatabaseHas('bookings', [
            'turf_id' => $this->turf->id,
            'user_id' => $this->customer->id,
            'customer_gstin' => '27AAECP1234F1Z5',
            'customer_company_name' => 'Corporate Sports Club',
            'status' => 'Confirmed',
        ]);

        $booking = Booking::where('user_id', $this->customer->id)->latest()->first();
        $this->assertNotNull($booking->booking_number);
        $this->assertGreaterThan(0, $booking->total_amount);
        $this->assertDatabaseHas('booking_slots', [
            'status' => 'active',
        ]);
    }

    public function test_cancellation_audit_logging_and_slot_status(): void
    {
        $dateStr = now()->addDays(3)->format('Y-m-d');

        // Create booking with payment
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'turf_id' => $this->turf->id,
            'date_of_booking' => now(),
            'booking_type' => 'day',
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
            'total_amount' => 1000.00,
            'payable_now' => 1000.00,
            'balance_amount' => 0.00,
        ]);

        $bDate = $booking->bookingDates()->create([
            'booking_date' => $dateStr,
            'status' => 'Confirmed',
            'amount' => 1000.00,
            'payment_status' => 'Paid',
        ]);

        $slotRel = $bDate->bookingSlots()->create([
            'slot_id' => $this->slot1->id,
            'status' => 'active',
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $bDate->id,
            'payment_method' => 'Cash',
            'amount' => 1000.00,
            'commission_percentage' => 7.00,
            'commission_amount' => 70.00,
            'cash_held_amount' => 0.00,
            'turf_payout_amount' => -70.00,
            'status' => 'Success',
            'paid_at' => now(),
        ]);

        // Cancel the booking date
        $response = $this->actingAs($this->customer)->postJson("/api/bookings/{$booking->id}/cancel", [
            'booking_date_id' => $bDate->id,
            'reason' => 'Change of schedule',
        ]);

        $response->assertOk();

        // Check slot status updated to cancelled
        $slotRel->refresh();
        $this->assertEquals('cancelled', $slotRel->status);

        // Check booking date updated
        $bDate->refresh();
        $this->assertEquals('Cancelled', $bDate->status);
        $this->assertEquals('Cash / Offline Refund', $bDate->refund_status);

        // Check audit record created in booking_cancellations
        $this->assertDatabaseHas('booking_cancellations', [
            'booking_id' => $booking->id,
            'booking_date_id' => $bDate->id,
            'cancelled_by_user_id' => $this->customer->id,
            'reason' => 'Change of schedule',
            'refund_status' => 'Cash / Offline Refund',
        ]);
    }

    public function test_platform_fee_online_booking_records_exact_paid_amount(): void
    {
        // Set platform fee = 10.00
        $saas = \App\Models\SaasSetting::first();
        if ($saas) {
            $saas->update(['platform_fee' => 10.00, 'is_gst_billing_active' => false]);
        }

        $dateStr = now()->addDays(5)->format('Y-m-d');

        $response = $this->actingAs($this->customer)->postJson("/api/turfs/{$this->turf->id}/bookings", [
            'slot_ids' => [$this->slot1->id, $this->slot2->id],
            'booking_dates' => [$dateStr],
            'booking_type' => 'day',
            'payment_method' => 'App',
            'payment_option' => 'full',
            'razorpay_payment_id' => 'pay_test_1010',
        ]);

        $response->assertOk();

        $booking = \App\Models\Booking::where('user_id', $this->customer->id)->latest()->first();
        $this->assertNotNull($booking);

        // Total amount must be base slots (500*2 = 1000) + platform fee (10) = 1010.00
        $this->assertEquals(1010.00, (float)$booking->total_amount);
        $this->assertEquals('Paid', $booking->payment_status);
        $this->assertEquals(0.00, (float)$booking->balance_amount);

        // BookingDate checks
        $bDate = $booking->bookingDates->first();
        $this->assertNotNull($bDate);
        $this->assertEquals(1010.00, (float)$bDate->amount);
        $this->assertEquals(1010.00, (float)$bDate->paid_amount);
        $this->assertEquals(0.00, (float)$bDate->balance_amount);
        $this->assertEquals('Paid', $bDate->payment_status);

        // Payment record checks
        $payment = \App\Models\Payment::where('booking_id', $booking->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(1010.00, (float)$payment->amount);
        $this->assertEquals('Success', $payment->status);

        // API endpoint /api/bookings checks
        $apiResponse = $this->actingAs($this->customer)->getJson('/api/bookings');
        $apiResponse->assertOk();
        $bookingData = collect($apiResponse->json('data'))->firstWhere('id', $bDate->id);
        $this->assertNotNull($bookingData);
        $this->assertEquals(1010.00, $bookingData['amount']);
        $this->assertEquals(1010.00, $bookingData['date_paid_amount']);
        $this->assertEquals(0.00, $bookingData['date_balance_amount']);
        $this->assertEquals(1010.00, $bookingData['total_paid_amount']);
        $this->assertEquals('Paid', $bookingData['payment_status']);
    }
}
