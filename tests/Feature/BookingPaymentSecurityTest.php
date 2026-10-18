<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Location;
use App\Models\SaasSetting;
use App\Models\Slot;
use App\Models\SlotCategory;
use App\Models\Turf;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesRazorpay;
use Tests\TestCase;

/**
 * Regression tests for the money-flow audit: a booking can only be recorded as paid online with a
 * genuine, captured, unused Razorpay payment of the exact amount; booking privileges (cash, discounts,
 * on-behalf bookings) are limited to the turf's own staff; unsigned payout webhooks are refused; and a
 * genuine payment that can't become a booking is refunded automatically.
 */
class BookingPaymentSecurityTest extends TestCase
{
    use RefreshDatabase, FakesRazorpay;

    protected User $owner;
    protected User $customer;
    protected Turf $turf;
    protected Slot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        SaasSetting::create(['platform_fee' => 10.00, 'is_gst_billing_active' => false, 'min_slots_booking' => 1]);

        $this->owner = User::factory()->create(['commission_wallet_balance' => 0.00]);
        $this->owner->assignRole('turf-admin');
        $this->customer = User::factory()->create();
        $this->customer->assignRole('customer');

        $this->turf = $this->makeTurf($this->owner, 'Secure Turf');
        $category = SlotCategory::create(['name' => 'Evening']);
        $this->slot = Slot::create(['slot_category_id' => $category->id, 'name' => '6-7 PM', 'from_time' => '18:00:00', 'to_time' => '19:00:00', 'duration' => 60]);
        $this->turf->slots()->attach($this->slot->id, [
            'is_active' => true,
            'mon' => 1000, 'tue' => 1000, 'wed' => 1000, 'thu' => 1000, 'fri' => 1000, 'sat' => 1000, 'sun' => 1000,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));
        $this->ensureRazorpayFake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeTurf(User $owner, string $name): Turf
    {
        $location = Location::create(['user_id' => $owner->id, 'name' => "{$name} Arena", 'address' => 'Link Road', 'city' => 'Mumbai']);

        return Turf::create([
            'location_id' => $location->id, 'name' => $name, 'type' => 'Football', 'is_active' => true,
            'is_online_payment_active' => true, 'is_pay_at_location_active' => true,
        ]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'slot_ids' => [$this->slot->id],
            'booking_dates' => ['2026-10-03'],
            'booking_type' => 'day',
            'payment_method' => 'App',
            'payment_option' => 'full',
        ], $extra);
    }

    private function book(User $as, array $payload)
    {
        return $this->actingAs($as, 'sanctum')->postJson("/api/turfs/{$this->turf->id}/bookings", $payload);
    }

    private function signed(string $paymentId, string $orderId): array
    {
        return [
            'razorpay_payment_id' => $paymentId,
            'razorpay_order_id' => $orderId,
            'razorpay_signature' => hash_hmac('sha256', "{$orderId}|{$paymentId}", $this->razorpayTestSecret),
        ];
    }

    private function refundCalls(): int
    {
        return Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/refund'))->count();
    }

    // ------------------------------------------------------------ genuine online payment required

    public function test_genuine_payment_of_the_exact_amount_is_accepted(): void
    {
        $this->book($this->customer, $this->payload($this->razorpayPayment(1010)))->assertOk();

        $booking = Booking::firstOrFail();
        $this->assertSame('Paid', $booking->payment_status);
        $this->assertEquals(1010.00, (float) $booking->payments()->sum('amount'));
    }

    public function test_online_booking_without_payment_details_is_rejected(): void
    {
        $this->book($this->customer, $this->payload())->assertStatus(422);
        $this->book($this->customer, $this->payload(['razorpay_payment_id' => 'pay_made_up']))->assertStatus(422);

        $this->assertSame(0, Booking::count());
    }

    public function test_forged_signature_is_rejected(): void
    {
        $payment = $this->razorpayPayment(1010);
        $payment['razorpay_signature'] = hash_hmac('sha256', 'anything', 'wrong_secret');

        $this->book($this->customer, $this->payload($payment))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Payment could not be verified.');
        $this->assertSame(0, Booking::count());
        $this->assertSame(0, $this->refundCalls(), 'a forged payment must never trigger a refund');
    }

    public function test_payment_smaller_than_the_booking_amount_is_rejected_and_refunded(): void
    {
        $this->book($this->customer, $this->payload($this->razorpayPayment(1.00)))
            ->assertStatus(422)
            ->assertJsonPath('payment_refunded', true);

        $this->assertSame(0, Booking::count());
        $this->assertSame(1, $this->refundCalls());
    }

    public function test_failed_payment_is_rejected(): void
    {
        Http::fake(['api.razorpay.com/v1/payments/pay_failed1' => Http::response([
            'id' => 'pay_failed1', 'order_id' => 'order_failed1', 'status' => 'failed', 'amount' => 101000, 'currency' => 'INR',
        ])]);

        $this->book($this->customer, $this->payload($this->signed('pay_failed1', 'order_failed1')))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Payment was not successful.');
        $this->assertSame(0, Booking::count());
        $this->assertSame(0, $this->refundCalls(), 'nothing was captured, so nothing to refund');
    }

    public function test_payment_for_a_different_order_is_rejected(): void
    {
        $payment = $this->razorpayPayment(1010);
        $other = $this->signed($payment['razorpay_payment_id'], 'order_someone_else');

        $this->book($this->customer, $this->payload($other))->assertStatus(422);
        $this->assertSame(0, Booking::count());
    }

    public function test_a_payment_cannot_be_used_for_two_bookings(): void
    {
        $payment = $this->razorpayPayment(1010);
        $this->book($this->customer, $this->payload($payment))->assertOk();

        $this->book($this->customer, $this->payload($payment + ['booking_dates' => ['2026-10-04']]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'This payment has already been used for another booking.');
        $this->assertSame(1, Booking::count());
        $this->assertSame(0, $this->refundCalls(), 'the payment belongs to the first booking and must not be refunded');
    }

    public function test_genuine_payment_is_refunded_when_the_slot_was_taken_meanwhile(): void
    {
        $this->book($this->customer, $this->payload($this->razorpayPayment(1010)))->assertOk();

        $other = User::factory()->create();
        $other->assignRole('customer');
        $response = $this->book($other, $this->payload($this->razorpayPayment(1010)));

        $this->assertNotSame(200, $response->status());
        $response->assertJsonPath('payment_refunded', true);
        $this->assertStringContainsString('refunded', $response->json('message'));
        $this->assertSame(1, Booking::count());
        $this->assertSame(1, $this->refundCalls());
    }

    // ------------------------------------------------------------ staff-only privileges

    public function test_player_cannot_use_staff_only_fields(): void
    {
        $this->book($this->customer, $this->payload(['payment_method' => 'offline', 'additional_discount' => 1000]))->assertStatus(403);
        $this->book($this->customer, $this->payload(['payment_method' => 'offline', 'amount_received' => 1010]))->assertStatus(403);
        $this->book($this->customer, $this->payload(['payment_method' => 'offline', 'customer_id' => $this->owner->id]))->assertStatus(403);
        $this->book($this->customer, $this->payload(['payment_method' => 'Cash']))->assertStatus(422);

        $this->assertSame(0, Booking::count());
    }

    public function test_another_turfs_owner_gets_no_staff_privileges_here(): void
    {
        $rival = User::factory()->create();
        $rival->assignRole('turf-admin');
        $this->makeTurf($rival, 'Rival Turf');

        $this->book($rival, $this->payload([
            'payment_method' => 'Cash', 'customer_id' => $this->customer->id, 'additional_discount' => 1000,
        ]))->assertStatus(403);

        $this->assertSame(0, Booking::count());
    }

    public function test_staff_cannot_record_an_on_behalf_booking_as_paid_online(): void
    {
        $this->book($this->owner, $this->payload([
            'customer_id' => $this->customer->id, 'payment_method' => 'App', 'amount_received' => 1010,
        ]))->assertStatus(422)->assertJsonPath('message', 'Staff bookings can only record Cash, UPI or Other payments.');

        $this->assertSame(0, Booking::count());
        $this->assertEquals(0.00, (float) $this->owner->fresh()->commission_wallet_balance);
    }

    public function test_owner_booking_their_own_turf_online_still_needs_a_real_payment(): void
    {
        $this->book($this->owner, $this->payload(['razorpay_payment_id' => 'pay_made_up']))->assertStatus(422);

        $this->assertSame(0, Booking::count());
        $this->assertEquals(0.00, (float) $this->owner->fresh()->commission_wallet_balance);
    }

    // ------------------------------------------------------------ payout webhook

    public function test_payout_webhook_is_refused_when_no_secret_is_configured(): void
    {
        $this->postJson('/api/razorpay/payout-webhook', ['event' => 'payout.failed'])->assertStatus(503);
    }

    public function test_payout_webhook_with_a_bad_signature_is_refused(): void
    {
        SaasSetting::first()->update(['razorpayx_webhook_secret' => 'whsec']);

        $this->postJson('/api/razorpay/payout-webhook', ['event' => 'payout.failed'])->assertStatus(400);
        $this->postJson('/api/razorpay/payout-webhook', ['event' => 'payout.failed'], ['X-Razorpay-Signature' => 'bad'])->assertStatus(400);
    }
}
