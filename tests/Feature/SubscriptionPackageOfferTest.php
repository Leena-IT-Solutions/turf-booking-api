<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\SaasSetting;
use App\Models\SubscriptionPackage;
use App\Models\SubscriptionPayment;
use App\Models\Turf;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SubscriptionPackageOfferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        SaasSetting::create([
            'commission_percentage' => 7.00,
            'razorpay_key' => 'rzp_test_key',
            'razorpay_secret' => 'rzp_test_secret',
        ]);
    }

    public function test_effective_amounts_use_offer_price_when_offer_is_valid(): void
    {
        $pkg = SubscriptionPackage::create([
            'name' => 'Standard Turf Partner',
            'monthly_amount' => 3000.00,
            'yearly_amount' => 30000.00,
            'commission_percentage' => 3.00,
            'is_active' => true,
            'is_offer_active' => true,
            'offer_badge' => '🔥 First 100 Turfs Founder Offer',
            'offer_monthly_amount' => 999.00,
            'offer_yearly_amount' => 9999.00,
            'offer_max_claims' => 100,
            'offer_claimed_count' => 14,
        ]);

        $this->assertTrue($pkg->isOfferValid());
        $this->assertEquals(999.00, $pkg->getEffectiveMonthlyAmount());
        $this->assertEquals(9999.00, $pkg->getEffectiveYearlyAmount());
        $this->assertEquals(86, $pkg->getRemainingOfferClaims());
    }

    public function test_effective_amounts_fallback_when_offer_is_inactive(): void
    {
        $pkg = SubscriptionPackage::create([
            'name' => 'Standard Turf Partner',
            'monthly_amount' => 3000.00,
            'yearly_amount' => 30000.00,
            'commission_percentage' => 3.00,
            'is_active' => true,
            'is_offer_active' => false,
            'offer_monthly_amount' => 999.00,
            'offer_yearly_amount' => 9999.00,
        ]);

        $this->assertFalse($pkg->isOfferValid());
        $this->assertEquals(3000.00, $pkg->getEffectiveMonthlyAmount());
        $this->assertEquals(30000.00, $pkg->getEffectiveYearlyAmount());
    }

    public function test_effective_amounts_fallback_when_quota_exhausted(): void
    {
        $pkg = SubscriptionPackage::create([
            'name' => 'Standard Turf Partner',
            'monthly_amount' => 3000.00,
            'yearly_amount' => 30000.00,
            'commission_percentage' => 3.00,
            'is_active' => true,
            'is_offer_active' => true,
            'offer_monthly_amount' => 999.00,
            'offer_yearly_amount' => 9999.00,
            'offer_max_claims' => 100,
            'offer_claimed_count' => 100,
        ]);

        $this->assertFalse($pkg->isOfferValid());
        $this->assertEquals(3000.00, $pkg->getEffectiveMonthlyAmount());
        $this->assertEquals(30000.00, $pkg->getEffectiveYearlyAmount());
        $this->assertEquals(0, $pkg->getRemainingOfferClaims());
    }

    public function test_effective_amounts_fallback_when_offer_expired(): void
    {
        $pkg = SubscriptionPackage::create([
            'name' => 'Standard Turf Partner',
            'monthly_amount' => 3000.00,
            'yearly_amount' => 30000.00,
            'commission_percentage' => 3.00,
            'is_active' => true,
            'is_offer_active' => true,
            'offer_monthly_amount' => 999.00,
            'offer_yearly_amount' => 9999.00,
            'offer_max_claims' => 100,
            'offer_claimed_count' => 10,
            'offer_expires_at' => Carbon::yesterday(),
        ]);

        $this->assertFalse($pkg->isOfferValid());
        $this->assertEquals(3000.00, $pkg->getEffectiveMonthlyAmount());
    }

    public function test_saas_admin_can_manage_package_launch_offer(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        Volt::test('saas.subscription-package-manager')
            ->call('openCreateModal')
            ->set('name', 'Founder Special Plan')
            ->set('monthly_amount', '3000.00')
            ->set('yearly_amount', '30000.00')
            ->set('is_offer_active', true)
            ->set('offer_badge', '🔥 First 100 Turfs Founder Offer')
            ->set('offer_monthly_amount', '999.00')
            ->set('offer_yearly_amount', '9999.00')
            ->set('offer_max_claims', 100)
            ->call('savePackage')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('subscription_packages', [
            'name' => 'Founder Special Plan',
            'monthly_amount' => 3000.00,
            'yearly_amount' => 30000.00,
            'commission_percentage' => 0.00,
            'is_offer_active' => true,
            'offer_monthly_amount' => 999.00,
            'offer_yearly_amount' => 9999.00,
            'offer_max_claims' => 100,
        ]);
    }

    public function test_turf_subscription_checkout_initiates_with_offer_price(): void
    {
        $turfAdmin = User::factory()->create(['email' => 'owner@turf.com']);
        $turfAdmin->assignRole('turf-admin');

        $location = Location::create([
            'user_id' => $turfAdmin->id,
            'name' => 'Arena Center',
            'address' => 'Sports Hub',
        ]);

        $turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Turf Pitch 1',
            'type' => 'Cricket',
        ]);

        $pkg = SubscriptionPackage::create([
            'name' => 'Standard Partner',
            'monthly_amount' => 3000.00,
            'yearly_amount' => 30000.00,
            'commission_percentage' => 3.00,
            'is_active' => true,
            'is_offer_active' => true,
            'offer_badge' => '🔥 First 100 Turfs Founder Offer',
            'offer_monthly_amount' => 999.00,
            'offer_yearly_amount' => 9999.00,
            'offer_max_claims' => 100,
            'offer_claimed_count' => 0,
        ]);

        $this->actingAs($turfAdmin);

        Volt::test('turf.subscription-manager')
            ->call('initiatePayment', $pkg->id, 'monthly', [$turf->id])
            ->assertHasNoErrors();

        // Assert payment record was created for ₹999 (effective offer amount) instead of ₹3,000
        $payment = SubscriptionPayment::where('user_id', $turfAdmin->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(999.00, (float) $payment->amount);
    }

    public function test_increment_offer_claim_increments_counter(): void
    {
        $pkg = SubscriptionPackage::create([
            'name' => 'Standard Partner',
            'monthly_amount' => 3000.00,
            'yearly_amount' => 30000.00,
            'commission_percentage' => 3.00,
            'is_active' => true,
            'is_offer_active' => true,
            'offer_monthly_amount' => 999.00,
            'offer_max_claims' => 100,
            'offer_claimed_count' => 5,
        ]);

        $pkg->incrementOfferClaim();

        $this->assertEquals(6, $pkg->fresh()->offer_claimed_count);
    }
}
