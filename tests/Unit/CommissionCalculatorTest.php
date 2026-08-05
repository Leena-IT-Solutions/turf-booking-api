<?php

namespace Tests\Unit;

use App\Models\Location;
use App\Models\SaasSetting;
use App\Models\SubscriptionPackage;
use App\Models\SubscriptionPayment;
use App\Models\Turf;
use App\Models\TurfSubscription;
use App\Models\User;
use App\Services\CommissionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Location $location;
    protected Turf $turf;

    protected function setUp(): void
    {
        parent::setUp();
        SaasSetting::create([
            'commission_percentage' => 7.00,
            'payment_gateway_percentage' => 2.00,
        ]);

        $this->user = User::factory()->create();
        $this->location = Location::create([
            'user_id' => $this->user->id,
            'name' => 'Main Location',
            'address' => '123 Main St',
        ]);
        $this->turf = Turf::create([
            'location_id' => $this->location->id,
            'name' => 'Main Turf',
            'type' => 'Football',
        ]);
    }

    public function test_default_rate_online_vs_offline_calculations()
    {
        $calculator = new CommissionCalculator();

        // Standard turf (no active subscription) -> Default SaaS rate 7%
        $this->assertEquals(7.00, $this->turf->commission_percentage);

        // Online (App): 7% commission
        $onlineCalc = $calculator->calculate($this->turf, 'App', 1000.00);
        $this->assertEquals(7.00, $onlineCalc['commission_percentage']);
        $this->assertEquals(70.00, $onlineCalc['commission_amount']);
        $this->assertEquals(1000.00, $onlineCalc['cash_held_amount']);
        $this->assertEquals(930.00, $onlineCalc['turf_payout_amount']);

        // Offline (Cash/UPI/Other): Effective rate (7% - 2% gateway discount = 5%)
        $offlineCalc = $calculator->calculate($this->turf, 'Cash', 1000.00);

        $this->assertEquals(5.00, $offlineCalc['commission_percentage']);
        $this->assertEquals(50.00, $offlineCalc['commission_amount']);
        $this->assertEquals(0.00, $offlineCalc['cash_held_amount']);
        $this->assertEquals(-50.00, $offlineCalc['turf_payout_amount']);
    }

    public function test_active_subscription_overrides_default_commission_rate()
    {
        $package = SubscriptionPackage::create([
            'name' => '3% Pro Plan',
            'pricing_type' => 'commission',
            'commission_percentage' => 3.00,
            'duration_days' => 30,
            'is_active' => true,
        ]);

        $payment = SubscriptionPayment::create([
            'user_id' => $this->user->id,
            'subscription_package_id' => $package->id,
            'billing_cycle' => 'monthly',
            'amount' => 999.00,
            'turf_ids' => [$this->turf->id],
            'turf_count' => 1,
            'status' => 'completed',
        ]);

        TurfSubscription::create([
            'turf_id' => $this->turf->id,
            'subscription_package_id' => $package->id,
            'subscription_payment_id' => $payment->id,
            'billing_cycle' => 'monthly',
            'price' => 999.00,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(29),
            'status' => 'active',
            'commission_percentage' => 3.00,
        ]);

        $this->assertEquals(3.00, $this->turf->commission_percentage);

        $calculator = new CommissionCalculator();

        // Online (App): 3%
        $onlineCalc = $calculator->calculate($this->turf, 'App', 1000.00);
        $this->assertEquals(3.00, $onlineCalc['commission_percentage']);
        $this->assertEquals(30.00, $onlineCalc['commission_amount']);
        $this->assertEquals(970.00, $onlineCalc['turf_payout_amount']);

        // Offline (Cash): 3% - 2% gateway discount = 1%
        $offlineCalc = $calculator->calculate($this->turf, 'Cash', 1000.00);
        $this->assertEquals(1.00, $offlineCalc['commission_percentage']);
        $this->assertEquals(10.00, $offlineCalc['commission_amount']);
        $this->assertEquals(-10.00, $offlineCalc['turf_payout_amount']);
    }

    public function test_zero_floored_offline_commission_rate()
    {
        $package = SubscriptionPackage::create([
            'name' => '2% VIP Plan',
            'pricing_type' => 'commission',
            'commission_percentage' => 2.00,
            'duration_days' => 30,
            'is_active' => true,
        ]);

        $payment = SubscriptionPayment::create([
            'user_id' => $this->user->id,
            'subscription_package_id' => $package->id,
            'billing_cycle' => 'monthly',
            'amount' => 999.00,
            'turf_ids' => [$this->turf->id],
            'turf_count' => 1,
            'status' => 'completed',
        ]);

        TurfSubscription::create([
            'turf_id' => $this->turf->id,
            'subscription_package_id' => $package->id,
            'subscription_payment_id' => $payment->id,
            'billing_cycle' => 'monthly',
            'price' => 999.00,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(29),
            'status' => 'active',
            'commission_percentage' => 2.00,
        ]);

        $calculator = new CommissionCalculator();

        // Offline (Cash): 2% - 2% gateway discount = 0%
        $offlineCalc = $calculator->calculate($this->turf, 'Cash', 1000.00);

        $this->assertEquals(0.00, $offlineCalc['commission_percentage']);
        $this->assertEquals(0.00, $offlineCalc['commission_amount']);
        $this->assertEquals(0.00, $offlineCalc['turf_payout_amount']);
    }
}
