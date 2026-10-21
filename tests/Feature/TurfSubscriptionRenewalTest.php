<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\SaasSetting;
use App\Models\SubscriptionPackage;
use App\Models\SubscriptionPayment;
use App\Models\Turf;
use App\Models\TurfSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TurfSubscriptionRenewalTest extends TestCase
{
    use RefreshDatabase;

    protected User $turfAdmin;
    protected Location $location;
    protected Turf $turfActive;
    protected Turf $turfLapsed;
    protected Turf $turfNew;
    protected SubscriptionPackage $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);

        // Never call the real Razorpay API from tests.
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response(['id' => 'order_test_abc'], 200),
        ]);

        SaasSetting::create([
            'commission_percentage' => 7.00,
            'razorpay_key' => 'rzp_test_key',
            'razorpay_secret' => 'rzp_test_secret',
        ]);


        $this->turfAdmin = User::factory()->create(['email' => 'admin@turf.com']);
        $this->turfAdmin->assignRole('turf-admin');

        $this->location = Location::create([
            'user_id' => $this->turfAdmin->id,
            'name' => 'City Sports Center',
            'address' => '100 Stadium Road',
        ]);

        $this->turfActive = Turf::create(['location_id' => $this->location->id, 'name' => 'Turf Active', 'type' => 'Football']);
        $this->turfLapsed = Turf::create(['location_id' => $this->location->id, 'name' => 'Turf Lapsed', 'type' => 'Cricket']);
        $this->turfNew    = Turf::create(['location_id' => $this->location->id, 'name' => 'Turf New', 'type' => 'Badminton']);

        $this->package = SubscriptionPackage::create([
            'name' => 'Exclusive 3%',
            'pricing_type' => 'commission',
            'monthly_amount' => 1500.00,
            'yearly_amount' => 15000.00,
            'is_active' => true,
        ]);

        // 1. Setup Turf Active: expires in 5 days
        $payment1 = SubscriptionPayment::create([
            'user_id' => $this->turfAdmin->id,
            'subscription_package_id' => $this->package->id,
            'billing_cycle' => 'monthly',
            'amount' => 1500.00,
            'turf_ids' => [$this->turfActive->id],
            'turf_count' => 1,
            'status' => 'completed',
        ]);

        TurfSubscription::create([
            'turf_id' => $this->turfActive->id,
            'subscription_package_id' => $this->package->id,
            'subscription_payment_id' => $payment1->id,
            'billing_cycle' => 'monthly',
            'price' => 1500.00,
            'commission_percentage' => 3.00,
            'starts_at' => now()->subDays(25),
            'expires_at' => now()->addDays(5),
            'status' => 'active',
        ]);

        // 2. Setup Turf Lapsed: expired 2 days ago
        $payment2 = SubscriptionPayment::create([
            'user_id' => $this->turfAdmin->id,
            'subscription_package_id' => $this->package->id,
            'billing_cycle' => 'monthly',
            'amount' => 1500.00,
            'turf_ids' => [$this->turfLapsed->id],
            'turf_count' => 1,
            'status' => 'completed',
        ]);

        TurfSubscription::create([
            'turf_id' => $this->turfLapsed->id,
            'subscription_package_id' => $this->package->id,
            'subscription_payment_id' => $payment2->id,
            'billing_cycle' => 'monthly',
            'price' => 1500.00,
            'commission_percentage' => 3.00,
            'starts_at' => now()->subDays(32),
            'expires_at' => now()->subDays(2),
            'status' => 'expired',
        ]);
    }

    public function test_multi_turf_checkout_calculates_independent_expiry_dates()
    {
        $this->actingAs($this->turfAdmin);

        $targetTurfIds = [$this->turfActive->id, $this->turfLapsed->id, $this->turfNew->id];

        // Initiate payment for all 3 turfs (Active, Lapsed, New)
        $component = Volt::test('turf.subscription-manager')
            ->set('selectedTurfIds', $targetTurfIds)
            ->call('initiatePayment', $this->package->id, 'monthly', $targetTurfIds);




        $paymentRecord = SubscriptionPayment::where('user_id', $this->turfAdmin->id)
            ->where('status', 'pending')
            ->latest()
            ->first();
        $this->assertNotNull($paymentRecord);
        $this->assertEquals(4500.00, (float)$paymentRecord->amount);
        $this->assertEquals(3, $paymentRecord->turf_count);


        $orderId = $paymentRecord->razorpay_order_id ?: 'order_fake_123';
        $paymentId = 'pay_fake_456';
        $signature = hash_hmac('sha256', $orderId . '|' . $paymentId, 'rzp_test_secret');

        // Update payment record to have fake order ID for signature testing
        $paymentRecord->update(['razorpay_order_id' => $orderId]);

        // Verify payment
        Volt::test('turf.subscription-manager')
            ->call('verifyPayment', $paymentRecord->id, $paymentId, $signature)
            ->assertHasNoErrors();

        // Assert Turf Active expiry extended by 30 days from existing expiry (~35 days from now)
        $subActive = $this->turfActive->activeSubscription;
        $this->assertNotNull($subActive);
        $this->assertEquals(0.00, (float)$subActive->commission_percentage);
        $this->assertTrue($subActive->expires_at->diffInDays(now()->addDays(35)) <= 1);

        // Assert Turf Lapsed expiry starts from today + 30 days (~30 days from now)
        $subLapsed = $this->turfLapsed->activeSubscription;
        $this->assertNotNull($subLapsed);
        $this->assertTrue($subLapsed->expires_at->diffInDays(now()->addDays(30)) <= 1);

        // Assert Turf New subscription created starting today + 30 days (~30 days from now)
        $subNew = $this->turfNew->activeSubscription;
        $this->assertNotNull($subNew);
        $this->assertTrue($subNew->expires_at->diffInDays(now()->addDays(30)) <= 1);
    }

    public function test_subscription_page_displays_default_commission_and_payment_gateway_charges(): void
    {
        $this->seed(\Database\Seeders\PaymentGatewayChargeSeeder::class);

        $this->actingAs($this->turfAdmin);

        Volt::test('turf.subscription-manager')
            ->assertSee('Platform Commission')
            ->assertSee('7.00%')
            ->assertSee('Default Baseline Rate')
            ->assertSee('Payment Gateway Charges')
            ->assertSee('Direct Gateway MDR (Razorpay)')
            ->assertSee('UPI')
            ->assertSee('Debit Cards (RuPay)')
            ->assertSee('Net Banking')
            ->assertSee('Credit Cards (Domestic)');
    }

    public function test_subscribe_button_dispatches_checkout_and_opens_razorpay(): void
    {
        $this->actingAs($this->turfAdmin);

        // The page script opens the Razorpay popup (rzp.open() was missing, so nothing happened)
        $this->get(route('turf.subscription'))
            ->assertOk()
            ->assertSee('checkout.razorpay.com/v1/checkout.js', false)
            ->assertSee('rzp.open()', false);

        Volt::test('turf.subscription-manager')
            ->set('selectedTurfIds', [$this->turfNew->id])
            ->call('initiatePayment', $this->package->id, 'monthly')
            ->assertDispatched('open-razorpay-checkout', function ($event, $params) {
                $p = $params['payload'] ?? [];

                return ($p['key'] ?? null) === 'rzp_test_key'
                    && ($p['amount'] ?? null) === 150000
                    && ($p['order_id'] ?? null) === 'order_test_abc'
                    && !empty($p['payment_record_id']);
            });

        $this->assertDatabaseHas('subscription_payments', [
            'user_id' => $this->turfAdmin->id,
            'razorpay_order_id' => 'order_test_abc',
            'status' => 'pending',
        ]);
    }

    /** Replace the setUp() Razorpay fake with a different response. */
    protected function fakeRazorpayOrders($response): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['api.razorpay.com/v1/orders' => $response]);
    }

    protected function pendingPayment(?User $owner = null, ?string $orderId = 'order_test_abc'): SubscriptionPayment
    {
        return SubscriptionPayment::create([
            'user_id' => ($owner ?? $this->turfAdmin)->id,
            'subscription_package_id' => $this->package->id,
            'billing_cycle' => 'monthly',
            'amount' => 1500.00,
            'turf_ids' => [$this->turfNew->id],
            'turf_count' => 1,
            'razorpay_order_id' => $orderId,
            'status' => 'pending',
        ]);
    }

    public function test_checkout_does_not_open_when_razorpay_order_fails(): void
    {
        $this->fakeRazorpayOrders(Http::response(['error' => ['description' => 'Authentication failed']], 401));
        $this->actingAs($this->turfAdmin);

        Volt::test('turf.subscription-manager')
            ->set('selectedTurfIds', [$this->turfNew->id])
            ->call('initiatePayment', $this->package->id, 'monthly')
            ->assertNotDispatched('open-razorpay-checkout')
            ->assertSee('Could not start the payment with Razorpay');

        $this->assertSame(0, SubscriptionPayment::where('status', 'pending')->count());
    }

    public function test_checkout_does_not_open_on_network_error(): void
    {
        $this->fakeRazorpayOrders(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));
        $this->actingAs($this->turfAdmin);

        Volt::test('turf.subscription-manager')
            ->set('selectedTurfIds', [$this->turfNew->id])
            ->call('initiatePayment', $this->package->id, 'monthly')
            ->assertNotDispatched('open-razorpay-checkout');

        $this->assertSame(0, SubscriptionPayment::where('status', 'pending')->count());
    }

    public function test_checkout_does_not_open_without_razorpay_keys(): void
    {
        SaasSetting::query()->update(['razorpay_key' => null, 'razorpay_secret' => null]);
        config(['services.razorpay.key' => null, 'services.razorpay.secret' => null]);
        $this->actingAs($this->turfAdmin);

        Volt::test('turf.subscription-manager')
            ->set('selectedTurfIds', [$this->turfNew->id])
            ->call('initiatePayment', $this->package->id, 'monthly')
            ->assertNotDispatched('open-razorpay-checkout')
            ->assertSee('Online payments are not configured');

        Http::assertNothingSent();
        $this->assertSame(0, SubscriptionPayment::where('status', 'pending')->count());
    }

    public function test_only_own_turfs_are_charged(): void
    {
        $other = User::factory()->create(['email' => 'other@turf.com']);
        $other->assignRole('turf-admin');
        $otherLoc = Location::create(['user_id' => $other->id, 'name' => 'Other', 'address' => 'X']);
        $otherTurf = Turf::create(['location_id' => $otherLoc->id, 'name' => 'Not Mine', 'type' => 'Football']);

        $this->actingAs($this->turfAdmin);
        Volt::test('turf.subscription-manager')
            ->call('initiatePayment', $this->package->id, 'monthly', [$this->turfNew->id, $otherTurf->id]);

        $payment = SubscriptionPayment::where('status', 'pending')->latest('id')->first();
        $this->assertSame([$this->turfNew->id], $payment->turf_ids);
        $this->assertEquals(1500.00, (float) $payment->amount);
    }

    public function test_verify_rejects_missing_or_invalid_signature(): void
    {
        $this->actingAs($this->turfAdmin);

        // No signature at all
        $p1 = $this->pendingPayment();
        Volt::test('turf.subscription-manager')->call('verifyPayment', $p1->id, 'pay_x', '');
        $this->assertSame('pending', $p1->fresh()->status);

        // Record without a Razorpay order can never be activated (old "simulated" path)
        $p2 = $this->pendingPayment(null, null);
        Volt::test('turf.subscription-manager')->call('verifyPayment', $p2->id, null, null);
        $this->assertSame('pending', $p2->fresh()->status);

        // Wrong signature
        $p3 = $this->pendingPayment();
        Volt::test('turf.subscription-manager')->call('verifyPayment', $p3->id, 'pay_x', 'not-a-real-signature');
        $this->assertSame('pending', $p3->fresh()->status);

        $this->assertNull($this->turfNew->fresh()->activeSubscription);
    }

    public function test_verify_rejects_other_users_payment_and_replay(): void
    {
        $other = User::factory()->create(['email' => 'other2@turf.com']);
        $other->assignRole('turf-admin');

        $payment = $this->pendingPayment();
        $sig = hash_hmac('sha256', 'order_test_abc|pay_ok_1', 'rzp_test_secret');

        // Another user can't activate it, even with a valid signature
        $this->actingAs($other);
        Volt::test('turf.subscription-manager')->call('verifyPayment', $payment->id, 'pay_ok_1', $sig);
        $this->assertSame('pending', $payment->fresh()->status);

        // Owner activates it once
        $this->actingAs($this->turfAdmin);
        Volt::test('turf.subscription-manager')->call('verifyPayment', $payment->id, 'pay_ok_1', $sig);
        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame(1, TurfSubscription::where('subscription_payment_id', $payment->id)->count());

        // Replaying the same verification doesn't extend it again
        Volt::test('turf.subscription-manager')
            ->call('verifyPayment', $payment->id, 'pay_ok_1', $sig)
            ->assertSee('already been processed');
        $this->assertSame(1, TurfSubscription::where('subscription_payment_id', $payment->id)->count());
    }
}
