<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\CommissionWalletTransaction;
use App\Models\Location;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\SaasSetting;
use App\Models\Slot;
use App\Models\SlotCategory;
use App\Models\Turf;
use App\Models\TurfPayout;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesRazorpay;
use Tests\TestCase;

/**
 * End-to-end money audit with production-like settings (SaaS GST on, ₹2 platform fee, 8% commission,
 * 5% platform cancellation fee, Razorpay 2% + 18% GST). Every scenario goes through the real API,
 * the nightly payout job and the admin services, then checks one balance rule:
 *
 *   owner wallet = online money received - gateway refunds - what the platform keeps - bank payouts
 *
 * Cash never passes through the platform, so it cancels out of the rule. "What the platform keeps"
 * is built from the business rules (platform fee, commission, Razorpay's actual fee, the platform's
 * cancellation fee), not from the wallet ledger, so a routing mistake anywhere shows up here.
 */
class MoneyFlowAuditTest extends TestCase
{
    use RefreshDatabase, FakesRazorpay;

    protected User $owner;
    protected User $customer;
    protected Turf $turf;
    protected Slot $slot;
    protected Slot $slot2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->razorpayFeePercent = 2.0; // Razorpay UPI pricing: 2% + 18% GST

        SaasSetting::create([
            'is_gst_billing_active' => true,
            'state_code' => '27',
            'platform_fee' => 2.00,
            'booking_gst_percentage' => 18.00,
            'commission_percentage' => 8.00,
            'commission_gst_percentage' => 18.00,
            'cancellation_fee_percentage' => 5.00,
            'payout_charges' => 40.00,
            'payout_hours' => 24,
            'min_slots_booking' => 1,
        ]);

        $this->owner = User::factory()->create([
            'commission_wallet_balance' => 0.00,
            'payout_method' => 'upi',
            'upi_id' => 'owner@upi',
        ]);
        $this->owner->assignRole('turf-admin');
        $this->customer = User::factory()->create();
        $this->customer->assignRole('customer');

        $location = Location::create(['user_id' => $this->owner->id, 'name' => 'Audit Arena', 'address' => 'Link Road', 'city' => 'Mumbai']);
        $this->turf = Turf::create([
            'location_id' => $location->id, 'name' => 'Audit Turf', 'type' => 'Football', 'is_active' => true,
            'is_online_payment_active' => true, 'is_pay_at_location_active' => true,
            'is_part_payment_active' => true, 'part_payment_type' => 'percentage', 'part_payment_value' => 50,
            'is_cancellation_active' => true, 'cancellation_hours' => 1, 'cancellation_fee' => 100.00,
            'is_reschedule_active' => true, 'max_reschedules' => 3,
        ]);

        $category = SlotCategory::create(['name' => 'Evening']);
        $this->slot = Slot::create(['slot_category_id' => $category->id, 'name' => '6-7 PM', 'from_time' => '18:00:00', 'to_time' => '19:00:00', 'duration' => 60]);
        $this->slot2 = Slot::create(['slot_category_id' => $category->id, 'name' => '7-8 PM', 'from_time' => '19:00:00', 'to_time' => '20:00:00', 'duration' => 60]);
        foreach ([$this->slot, $this->slot2] as $s) {
            $this->turf->slots()->attach($s->id, [
                'is_active' => true,
                'mon' => 1000, 'tue' => 1000, 'wed' => 1000, 'thu' => 1000, 'fri' => 1000,
                'sat' => 1500, 'sun' => 1500,
            ]);
        }

        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00')); // Thursday
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
            'slot_ids' => $slotIds, 'booking_dates' => $dates,
            'booking_type' => count($dates) > 1 ? 'long' : 'day',
            'payment_method' => $method, 'payment_option' => $option,
        ];
    }

    /** Books like the app does: preview -> pay Razorpay the payable amount -> create booking. */
    private function bookOnline(array $dates, string $option = 'full', ?array $slotIds = null): Booking
    {
        $slotIds ??= [$this->slot->id];
        $preview = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/turfs/{$this->turf->id}/bookings/preview", $this->payload($dates, $slotIds, 'App', $option))
            ->assertStatus(200)->json();
        $payable = (float) ($preview['payable_now'] ?? $preview['pricing']['payable_now'] ?? 0);
        $this->assertGreaterThan(0, $payable, 'preview must return payable_now');

        $response = $this->actingAs($this->customer, 'sanctum')->postJson(
            "/api/turfs/{$this->turf->id}/bookings",
            $this->payload($dates, $slotIds, 'App', $option) + $this->razorpayPayment($payable)
        )->assertStatus(200);

        return Booking::find($response->json('booking.id'));
    }

    private function bookPayAtVenue(array $dates): Booking
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/turfs/{$this->turf->id}/bookings", $this->payload($dates, [$this->slot->id], 'offline', 'full'))
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

    private function runNightly(string $at): void
    {
        Carbon::setTestNow(Carbon::parse($at));
        $this->artisan('wallet:clear-matured-entries');
    }

    private function wallet(): float
    {
        return round((float) $this->owner->fresh()->commission_wallet_balance, 2);
    }

    /** Pending Clearance as the Business & Earnings panel computes it. */
    private function pendingClearance(): float
    {
        return round((float) (new \App\Services\WalletService())->pendingCredits([$this->turf->id])->sum('amount'), 2);
    }

    /** What the platform keeps, from the business rules (not from the wallet ledger). */
    private function platformKeeps(): float
    {
        $keep = 0.0;
        foreach (Booking::all() as $b) {
            $hasPayment = Payment::where('booking_id', $b->id)->where('status', 'Success')->exists();
            if ($hasPayment) {
                $keep += (float) $b->platform_fee + (float) $b->platform_fee_gst
                    + (float) $b->commission_amount + (float) $b->commission_gst_amount;
            }
        }
        // One Razorpay payment can be stored once per session it paid for: count each payment once.
        foreach (PaymentGateway::pluck('gateway_payment_id')->unique() as $razorpayPaymentId) {
            $keep += $this->razorpayFees[$razorpayPaymentId] ?? 0.0;
        }
        foreach (BookingCancellation::all() as $c) {
            $keep += min((float) $c->saas_cancellation_fee, max(0.0, (float) $c->total_cancellation_fee - (float) $c->platform_fee_retained));
        }

        return round($keep, 2);
    }

    private function assertMoneyBalances(string $scenario): void
    {
        $online = Payment::where('status', 'Success')->where('payment_method', 'App')->get();
        $onlineNet = (float) $online->sum(fn ($p) => (float) $p->amount - (float) $p->refunded_amount);
        $payouts = (float) TurfPayout::where('status', '!=', 'failed')->sum('requested_amount');

        $expected = round($onlineNet - $this->platformKeeps() - $payouts, 2);
        $this->assertEqualsWithDelta($expected, $this->wallet(), 0.02,
            "[{$scenario}] wallet {$this->wallet()} != online net {$onlineNet} - platform keeps {$this->platformKeeps()} - payouts {$payouts}");
    }

    // ---------------------------------------------------------------- scenarios

    public function test_01_online_full_payment(): void
    {
        $this->bookOnline(['2026-10-05']);
        $pending = $this->pendingClearance();
        $before = $this->wallet();

        $this->runNightly('2026-10-06 01:00:00');

        $this->assertEqualsWithDelta($pending, $this->wallet() - $before, 0.02, 'Pending Clearance must equal what gets released');
        $this->assertMoneyBalances('online full');
    }

    public function test_02_part_online_balance_in_cash(): void
    {
        $booking = $this->bookOnline(['2026-10-05'], 'part');
        $this->collectCash($booking);
        $pending = $this->pendingClearance();
        $before = $this->wallet();

        $this->runNightly('2026-10-06 01:00:00');

        $this->assertEqualsWithDelta($pending, $this->wallet() - $before, 0.02, 'Pending Clearance must equal what gets released');
        $this->assertMoneyBalances('part online + cash');
    }

    public function test_03_pay_at_venue_cash(): void
    {
        $booking = $this->bookPayAtVenue(['2026-10-05']);
        $this->collectCash($booking);
        $this->runNightly('2026-10-06 01:00:00');
        $this->assertMoneyBalances('pay at venue');
    }

    public function test_04_two_session_online_booking(): void
    {
        $this->bookOnline(['2026-10-05', '2026-10-06']);
        $this->runNightly('2026-10-05 23:00:00');
        $this->runNightly('2026-10-07 01:00:00');
        $this->assertMoneyBalances('two sessions online');
        $this->assertEquals(0, Payment::whereNull('wallet_cleared_at')->count());
    }

    public function test_05_online_cancelled_before_session(): void
    {
        $booking = $this->bookOnline(['2026-10-05']);
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", ['booking_date_ids' => [$booking->bookingDates()->first()->id]])
            ->assertStatus(200);
        $pending = $this->pendingClearance();
        $before = $this->wallet();

        $this->runNightly('2026-10-02 01:00:00');

        $this->assertEqualsWithDelta($pending, $this->wallet() - $before, 0.02, 'Pending Clearance must equal what gets released');
        $this->assertMoneyBalances('online cancelled');
    }

    public function test_06_cash_booking_cancelled(): void
    {
        $booking = $this->bookPayAtVenue(['2026-10-05']);
        $this->collectCash($booking);
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", ['booking_date_ids' => [$booking->bookingDates()->first()->id]])
            ->assertStatus(200);
        $this->runNightly('2026-10-06 01:00:00');
        $this->assertMoneyBalances('cash cancelled');
    }

    public function test_07_part_online_plus_cash_then_cancelled(): void
    {
        $booking = $this->bookOnline(['2026-10-05'], 'part');
        $this->collectCash($booking);
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", ['booking_date_ids' => [$booking->bookingDates()->first()->id]])
            ->assertStatus(200);
        $this->runNightly('2026-10-06 01:00:00');
        $this->assertMoneyBalances('part online + cash, cancelled');
    }

    public function test_08_second_session_cancelled_after_first_paid_out(): void
    {
        $booking = $this->bookOnline(['2026-10-05', '2026-10-06']);
        $this->runNightly('2026-10-05 12:00:00');
        $second = $booking->bookingDates()->orderBy('booking_date')->get()->last();
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", ['booking_date_ids' => [$second->id]])
            ->assertStatus(200);
        $this->runNightly('2026-10-06 01:00:00');
        $this->assertMoneyBalances('second session cancelled after first paid out');
    }

    public function test_09_reschedule_to_costlier_slot_balance_in_cash(): void
    {
        $booking = $this->bookOnline(['2026-10-05']); // Monday ₹1000
        $bDate = $booking->bookingDates()->first();
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/booking-dates/{$bDate->id}/reschedule", ['date' => '2026-10-10', 'slot_ids' => [$this->slot->id]]) // Saturday ₹1500
            ->assertStatus(200);
        $this->collectCash($booking);
        $this->runNightly('2026-10-11 01:00:00');
        $this->assertMoneyBalances('reschedule up, balance in cash');
    }

    public function test_10_reschedule_to_cheaper_slot_refunds_online(): void
    {
        $booking = $this->bookOnline(['2026-10-10']); // Saturday ₹1500
        $bDate = $booking->bookingDates()->first();
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/booking-dates/{$bDate->id}/reschedule", ['date' => '2026-10-12', 'slot_ids' => [$this->slot->id]]) // Monday ₹1000
            ->assertStatus(200);
        $this->assertGreaterThan(0, (float) Payment::sum('refunded_amount'));
        $this->runNightly('2026-10-13 01:00:00');
        $this->assertMoneyBalances('reschedule down, online refund');
    }

    public function test_11_admin_resolutions(): void
    {
        $service = app(\App\Services\BookingCancellationService::class);
        foreach (['full_compensation' => '2026-10-05', 'custom' => '2026-10-06', 'no_refund' => '2026-10-07'] as $mode => $date) {
            $booking = $this->bookOnline([$date]);
            $this->actingAs($this->customer, 'sanctum')
                ->postJson("/api/bookings/{$booking->id}/cancel", ['booking_date_ids' => [$booking->bookingDates()->first()->id]])
                ->assertStatus(200);
            $c = BookingCancellation::where('booking_id', $booking->id)->first();
            $c->update(['refund_status' => 'Pending Resolution']);
            $service->resolveRefund($c->fresh(), $mode, 'online_gateway', $this->owner, $mode === 'custom' ? 500.00 : null);
        }
        $this->runNightly('2026-10-08 01:00:00');
        $this->assertMoneyBalances('admin resolutions');
    }

    public function test_12_bank_payout(): void
    {
        $this->bookOnline(['2026-10-05']);
        $this->runNightly('2026-10-06 01:00:00');
        $balance = $this->wallet();
        app(\App\Services\PayoutService::class)->requestPayout($this->owner->fresh(), $balance);
        $this->assertEqualsWithDelta(0.0, $this->wallet(), 0.01);
        $this->assertMoneyBalances('bank payout');
    }

    public function test_13_razorpay_fee_is_charged_once(): void
    {
        $this->bookOnline(['2026-10-05']);
        $charged = abs((float) CommissionWalletTransaction::where('type', 'gateway_charge_debit')->sum('amount'));
        $this->assertEqualsWithDelta(array_sum($this->razorpayFees), $charged, 0.01,
            'owner must be charged exactly what Razorpay charged (its fee already includes GST)');
    }

    public function test_14_turf_with_own_gst_charged_extra(): void
    {
        \App\Models\TurfSetting::create([
            'turf_id' => $this->turf->id, 'company_name' => 'Audit Sports LLP', 'gst_number' => '27ABCDE1234F1Z5',
            'is_gst_billing_active' => true, 'gst_pricing_type' => 'excluded', 'gst_percentage' => 18.00,
            'state' => 'Maharashtra', 'state_code' => '27',
        ]);
        $booking = $this->bookOnline(['2026-10-05']);
        $this->assertEqualsWithDelta(1182.00, (float) $booking->total_amount, 0.01); // 1000 + 18% GST + ₹2 platform fee
        $this->runNightly('2026-10-06 01:00:00');
        $this->assertMoneyBalances('turf GST extra');
    }
}
