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
            'commission_percentage' => 3.00,
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
        $this->assertEquals(3.00, (float)$subActive->commission_percentage);
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
}
