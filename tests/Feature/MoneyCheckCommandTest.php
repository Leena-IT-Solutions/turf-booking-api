<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CommissionWalletTransaction;
use App\Models\Location;
use App\Models\Payment;
use App\Models\SaasSetting;
use App\Models\Slot;
use App\Models\SlotCategory;
use App\Models\Turf;
use App\Models\TurfPayout;
use App\Models\User;
use App\Services\MoneyAuditService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesRazorpay;
use Tests\TestCase;

class MoneyCheckCommandTest extends TestCase
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
        $this->razorpayFeePercent = 2.0;

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

        $location = Location::create([
            'user_id' => $this->owner->id,
            'name' => 'Audit Arena',
            'address' => 'Link Road',
            'city' => 'Mumbai',
        ]);

        $this->turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Audit Turf',
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

        $category = SlotCategory::create(['name' => 'Evening']);
        $this->slot = Slot::create([
            'slot_category_id' => $category->id,
            'name' => '6-7 PM',
            'from_time' => '18:00:00',
            'to_time' => '19:00:00',
            'duration' => 60,
        ]);
        $this->slot2 = Slot::create([
            'slot_category_id' => $category->id,
            'name' => '7-8 PM',
            'from_time' => '19:00:00',
            'to_time' => '20:00:00',
            'duration' => 60,
        ]);

        foreach ([$this->slot, $this->slot2] as $s) {
            $this->turf->slots()->attach($s->id, [
                'is_active' => true,
                'mon' => 1000,
                'tue' => 1000,
                'wed' => 1000,
                'thu' => 1000,
                'fri' => 1000,
                'sat' => 1500,
                'sun' => 1500,
            ]);
        }

        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));
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
            'slot_ids' => $slotIds,
            'booking_dates' => $dates,
            'booking_type' => count($dates) > 1 ? 'long' : 'day',
            'payment_method' => $method,
            'payment_option' => $option,
        ];
    }

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

    // ---------------------------------------------------------------- tests

    public function test_happy_paths_across_all_money_scenarios(): void
    {
        $bookings = [];

        // 1. Online full payment
        $bookings[] = $this->bookOnline(['2026-10-05']);

        // 2. Part payment + cash
        $b2 = $this->bookOnline(['2026-10-06'], 'part');
        $this->collectCash($b2);
        $bookings[] = $b2;

        // 3. Pay at venue + cash
        $b3 = $this->bookPayAtVenue(['2026-10-07']);
        $this->collectCash($b3);
        $bookings[] = $b3;

        // 4. Two sessions
        $bookings[] = $this->bookOnline(['2026-10-08', '2026-10-09'], 'full');

        // 5. Online cancellation
        $b5 = $this->bookOnline(['2026-10-10']);
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$b5->id}/cancel", ['booking_date_ids' => [$b5->bookingDates()->first()->id]])
            ->assertStatus(200);
        $bookings[] = $b5;

        // 6. Cash cancellation
        $b6 = $this->bookPayAtVenue(['2026-10-11']);
        $this->collectCash($b6);
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$b6->id}/cancel", ['booking_date_ids' => [$b6->bookingDates()->first()->id]])
            ->assertStatus(200);
        $bookings[] = $b6;

        // 7. Reschedule down with online refund
        $b7 = $this->bookOnline(['2026-10-10']); // Saturday ₹1500
        $bDate7 = $b7->bookingDates()->first();
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/booking-dates/{$bDate7->id}/reschedule", ['date' => '2026-10-12', 'slot_ids' => [$this->slot->id]]) // Monday ₹1000
            ->assertStatus(200);
        $bookings[] = $b7;

        // Run nightly job after all sessions have passed
        $this->runNightly('2026-10-15 01:00:00');

        // 8. Payout
        $balance = $this->wallet();
        $this->assertGreaterThan(0, $balance);
        app(\App\Services\PayoutService::class)->requestPayout($this->owner->fresh(), $balance);

        // Verify service directly
        $service = app(MoneyAuditService::class);
        foreach ($bookings as $b) {
            $rows = $service->checkBooking($b->fresh(), true);
            $failed = collect($rows)->where('result', 'FAIL')->all();
            $this->assertEmpty($failed, 'Booking #' . $b->id . ' has failing checks: ' . json_encode($failed));
        }

        $ownerRows = $service->checkOwner($this->owner->fresh(), true);
        $ownerFailed = collect($ownerRows)->where('result', 'FAIL')->all();
        $this->assertEmpty($ownerFailed, 'Owner checks failed: ' . json_encode($ownerFailed));
        // Every wallet row type these scenarios produce (incl. the cash cancellation fee) is a known type.
        $this->assertNull(collect($ownerRows)->first(fn ($r) => str_starts_with($r['check'], 'Note: Other wallet')),
            'Unexpected "other wallet types" note: ' . json_encode($ownerRows));

        // Verify command execution
        $this->artisan('money:check', ['--owner' => $this->owner->id])
            ->assertExitCode(0);
    }

    public function test_inter_state_turf(): void
    {
        $this->turf->turfSetting()->create([
            'state' => 'Karnataka',
            'state_code' => '29',
            'gst_number' => '29AABCA1234A1Z1',
        ]);
        $this->turf->unsetRelation('turfSetting');

        $b = $this->bookOnline(['2026-10-05']);
        $rows = app(MoneyAuditService::class)->checkBooking($b->fresh(), true);

        $row5 = collect($rows)->firstWhere('check', '5. Platform fee GST type (uses the turf\'s current state)');
        $this->assertNotNull($row5);
        $this->assertEquals('PASS', $row5['result']);
        $this->assertGreaterThan(0, (float) $b->fresh()->platform_fee_igst);

        $row7 = collect($rows)->firstWhere('check', '7. Commission GST type (uses the turf\'s current state)');
        $this->assertNotNull($row7);
        $this->assertEquals('PASS', $row7['result']);
        $this->assertGreaterThan(0, (float) $b->fresh()->commission_igst_amount);
    }

    public function test_tampering_is_caught_check_13(): void
    {
        $booking = $this->bookOnline(['2026-10-05']);
        $pfDebit = CommissionWalletTransaction::where('type', 'platform_fee_debit')->first();
        $amt = (float) $pfDebit->amount; // stored negative
        $pfDebit->delete();

        // Adjust wallet balance so ledger sum matches wallet
        $this->owner->update([
            'commission_wallet_balance' => (float) $this->owner->commission_wallet_balance - $amt,
        ]);

        $rows = app(MoneyAuditService::class)->checkBooking($booking->fresh(), true);
        $row13 = collect($rows)->firstWhere('check', '13. Platform fee deducted once');
        $this->assertEquals('FAIL', $row13['result']);

        $this->artisan('money:check', ['--booking' => [$booking->id]])->assertExitCode(1);
    }

    public function test_tampering_is_caught_check_30(): void
    {
        $this->bookOnline(['2026-10-05']);
        $this->owner->increment('commission_wallet_balance', 1.00);

        $ownerRows = app(MoneyAuditService::class)->checkOwner($this->owner->fresh(), true);
        $row30 = collect($ownerRows)->firstWhere('check', '30. Wallet balance = sum(ledger transactions)');
        $this->assertEquals('FAIL', $row30['result']);

        $this->artisan('money:check', ['--owner' => $this->owner->id])->assertExitCode(1);
    }

    public function test_tampering_is_caught_check_11_stored_fee(): void
    {
        $booking = $this->bookOnline(['2026-10-05']);
        $payment = Payment::where('status', 'Success')->where('payment_method', 'App')->first();
        $payment->update([
            'gateway_charge_amount' => (float) $payment->gateway_charge_amount * 2 + 5.00,
        ]);

        $rows = app(MoneyAuditService::class)->checkBooking($booking->fresh(), true);
        $row11 = collect($rows)->firstWhere('check', '11. Razorpay fee and payment match gateway');
        $this->assertEquals('FAIL', $row11['result']);

        $this->artisan('money:check', ['--booking' => [$booking->id]])->assertExitCode(1);
    }

    public function test_tampering_is_caught_check_4_and_5_platform_fee_igst(): void
    {
        $booking = $this->bookOnline(['2026-10-05']);
        $booking->update(['platform_fee_igst' => 5.00]);

        $rows = app(MoneyAuditService::class)->checkBooking($booking->fresh(), true);
        $row4 = collect($rows)->firstWhere('check', '4. platform_fee_cgst + sgst + igst = platform_fee_gst');
        $row5 = collect($rows)->firstWhere('check', '5. Platform fee GST type (uses the turf\'s current state)');
        $this->assertTrue($row4['result'] === 'FAIL' || $row5['result'] === 'FAIL');

        $this->artisan('money:check', ['--booking' => [$booking->id]])->assertExitCode(1);
    }

    public function test_tampering_is_caught_check_18_offline_credit(): void
    {
        $booking = $this->bookPayAtVenue(['2026-10-05']);
        $this->collectCash($booking);
        $cashPayment = $booking->payments()->where('payment_method', 'Cash')->first();

        CommissionWalletTransaction::create([
            'user_id' => $this->owner->id,
            'type' => 'payment_credit',
            'amount' => 500.00,
            'balance_after' => 500.00,
            'reference_type' => Payment::class,
            'reference_id' => $cashPayment->id,
        ]);

        $rows = app(MoneyAuditService::class)->checkBooking($booking->fresh(), true);
        $row18 = collect($rows)->firstWhere('check', '18. Offline payments do not credit wallet');
        $this->assertEquals('FAIL', $row18['result']);

        $this->artisan('money:check', ['--booking' => [$booking->id]])->assertExitCode(1);
    }

    public function test_tampering_is_caught_check_11_razorpay_faked_amount(): void
    {
        $booking = $this->bookOnline(['2026-10-05']);
        $gwId = $booking->payments->first()->paymentGateway->gateway_payment_id;

        $this->withRazorpayOverride(function (Request $request) use ($gwId) {
            if (str_contains($request->url(), "/v1/payments/{$gwId}")) {
                return Http::response([
                    'id' => $gwId,
                    'order_id' => "order_{$gwId}",
                    'status' => 'captured',
                    'amount' => 999999, // Mismatched amount
                    'currency' => 'INR',
                    'fee' => 200,
                    'tax' => 36,
                ], 200);
            }
            return null;
        });

        $rows = app(MoneyAuditService::class)->checkBooking($booking->fresh(), true);
        $row11 = collect($rows)->firstWhere('check', '11. Razorpay fee and payment match gateway');
        $this->assertEquals('FAIL', $row11['result']);

        $this->artisan('money:check', ['--booking' => [$booking->id]])->assertExitCode(1);
    }

    public function test_gateway_errors_are_skip_not_fail(): void
    {
        $booking = $this->bookOnline(['2026-10-05']);

        $this->withRazorpayOverride(fn () => Http::response([], 500));

        $rows = app(MoneyAuditService::class)->checkBooking($booking->fresh(), true);
        $row11 = collect($rows)->firstWhere('check', '11. Razorpay fee and payment match gateway');
        $this->assertEquals('SKIP', $row11['result']);
        $this->assertStringContainsString('500', $row11['actual']);

        $this->artisan('money:check', ['--booking' => [$booking->id]])->assertExitCode(0);
    }

    public function test_no_gateway_flag_skips_lookups(): void
    {
        $booking = $this->bookOnline(['2026-10-05']);

        app()->instance(\Illuminate\Http\Client\Factory::class, new \Illuminate\Http\Client\Factory());
        Http::fake(); // No stubs

        $this->artisan('money:check', ['--booking' => [$booking->id], '--no-gateway' => true])
            ->assertExitCode(0);

        Http::assertNothingSent();

        $rows = app(MoneyAuditService::class)->checkBooking($booking->fresh(), false);
        $row11 = collect($rows)->firstWhere('check', '11. Razorpay fee and payment match gateway');
        $this->assertEquals('SKIP', $row11['result']);
        $this->assertEquals('Skipped by --no-gateway', $row11['actual']);
    }

    public function test_read_only_nature(): void
    {
        $booking = $this->bookOnline(['2026-10-05']);
        $this->runNightly('2026-10-06 01:00:00');

        $bCountBefore = Booking::count();
        $pCountBefore = Payment::count();
        $txCountBefore = CommissionWalletTransaction::count();
        $payoutCountBefore = TurfPayout::count();
        $balanceBefore = (float) $this->owner->fresh()->commission_wallet_balance;

        $this->artisan('money:check', ['--owner' => $this->owner->id])->assertExitCode(0);

        $this->assertEquals($bCountBefore, Booking::count());
        $this->assertEquals($pCountBefore, Payment::count());
        $this->assertEquals($txCountBefore, CommissionWalletTransaction::count());
        $this->assertEquals($payoutCountBefore, TurfPayout::count());
        $this->assertEquals($balanceBefore, (float) $this->owner->fresh()->commission_wallet_balance);

        Http::assertNotSent(fn (Request $r) => $r->method() !== 'GET');
    }

    public function test_no_arguments_exits_one(): void
    {
        $this->artisan('money:check')->assertExitCode(1);
    }
}
