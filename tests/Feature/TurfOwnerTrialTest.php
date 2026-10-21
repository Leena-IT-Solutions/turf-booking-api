<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
use App\Models\SaasSetting;
use App\Models\SubscriptionPackage;
use App\Models\SubscriptionPayment;
use App\Models\Turf;
use App\Models\TurfSubscription;
use App\Models\User;
use App\Services\CommissionCalculator;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TurfOwnerTrialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Carbon::setTestNow('2026-10-21 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_default_commission_in_saas_settings_and_turf_is_eight_percent(): void
    {
        $setting = SaasSetting::create([
            'app_name' => 'TurfBooking',
        ]);
        $this->assertEquals(8.00, (float) $setting->commission_percentage);

        // Turf with no active subscription
        $user = User::factory()->create();
        $loc = Location::create(['user_id' => $user->id, 'name' => 'L1', 'address' => 'A1']);
        $turf = Turf::create(['location_id' => $loc->id, 'name' => 'T1', 'type' => 'Cricket']);

        $this->assertEquals(8.00, $turf->commission_percentage);
    }

    public function test_claiming_turf_admin_starts_the_trial(): void
    {
        SaasSetting::create([
            'free_trial_days' => 30,
            'commission_percentage' => 8.00,
        ]);

        $user = User::factory()->create();
        $user->assignRole('customer');

        $this->actingAs($user);

        Volt::test('dashboard.turf-prompter')
            ->call('claimTurfAdmin')
            ->assertRedirect(route('turf.dashboard'));

        $user->refresh();
        $this->assertTrue($user->hasRole('turf-admin'));
        $this->assertTrue($user->onTrial());
        $this->assertEquals('2026-10-21 10:00:00', $user->trial_started_at->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-11-20 10:00:00', $user->trial_ends_at->format('Y-m-d H:i:s'));
        $this->assertEquals(30, $user->trialDaysLeft());

        $this->assertTrue(session()->has('success'));
        $this->assertStringContainsString('30-day trial with 0% commission', session('success'));
    }

    public function test_trial_is_never_restarted(): void
    {
        SaasSetting::create(['free_trial_days' => 30]);

        $user = User::factory()->create();
        $user->startTurfOwnerTrial();
        $initialEnd = $user->trial_ends_at->copy();

        Carbon::setTestNow('2026-10-25 12:00:00');

        $user->startTurfOwnerTrial();
        $user->refresh();

        $this->assertEquals($initialEnd->format('Y-m-d H:i:s'), $user->trial_ends_at->format('Y-m-d H:i:s'));
    }

    public function test_setting_zero_gives_no_trial(): void
    {
        SaasSetting::create([
            'free_trial_days' => 0,
            'commission_percentage' => 8.00,
        ]);

        $user = User::factory()->create();
        $user->startTurfOwnerTrial();

        $this->assertNull($user->trial_started_at);
        $this->assertNull($user->trial_ends_at);
        $this->assertFalse($user->onTrial());

        $loc = Location::create(['user_id' => $user->id, 'name' => 'L1', 'address' => 'A1']);
        $turf = Turf::create(['location_id' => $loc->id, 'name' => 'T1', 'type' => 'Cricket']);

        $this->assertEquals(8.00, $turf->commission_percentage);
    }

    public function test_zero_percent_commission_during_trial_and_reverts_to_default_after(): void
    {
        SaasSetting::create([
            'free_trial_days' => 30,
            'commission_percentage' => 8.00,
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('turf-admin');
        $owner->startTurfOwnerTrial();

        $loc = Location::create(['user_id' => $owner->id, 'name' => 'Venue 1', 'address' => 'Addr 1']);
        $turf = Turf::create(['location_id' => $loc->id, 'name' => 'Court 1', 'type' => 'Tennis']);

        $calculator = new CommissionCalculator();

        // During trial: 0% commission
        $this->assertEquals(0.00, $turf->commission_percentage);
        $duringTrialCalc = $calculator->calculate($turf, 'App', 1000.00);
        $this->assertEquals(0.00, $duringTrialCalc['commission_percentage']);
        $this->assertEquals(0.00, $duringTrialCalc['commission_amount']);

        // After trial expires: reverts to 8.00%
        Carbon::setTestNow('2026-11-21 10:00:00');
        $owner->refresh();
        $this->assertFalse($owner->onTrial());

        $this->assertEquals(8.00, $turf->commission_percentage);
        $afterTrialCalc = $calculator->calculate($turf, 'App', 1000.00);
        $this->assertEquals(8.00, $afterTrialCalc['commission_percentage']);
        $this->assertEquals(80.00, $afterTrialCalc['commission_amount']);
    }

    public function test_paid_plan_wins_over_trial(): void
    {
        SaasSetting::create([
            'free_trial_days' => 30,
            'commission_percentage' => 8.00,
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('turf-admin');
        $owner->startTurfOwnerTrial();

        $loc = Location::create(['user_id' => $owner->id, 'name' => 'Venue 1', 'address' => 'Addr 1']);
        $turf = Turf::create(['location_id' => $loc->id, 'name' => 'Court 1', 'type' => 'Tennis']);

        $pkg = SubscriptionPackage::create([
            'name' => 'Special 2% Package',
            'pricing_type' => 'commission',
            'commission_percentage' => 2.00,
            'monthly_amount' => 1000.00,
            'yearly_amount' => 10000.00,
            'is_active' => true,
        ]);

        $pmt = SubscriptionPayment::create([
            'user_id' => $owner->id,
            'subscription_package_id' => $pkg->id,
            'billing_cycle' => 'monthly',
            'amount' => 1000.00,
            'turf_ids' => [$turf->id],
            'turf_count' => 1,
            'status' => 'completed',
        ]);

        TurfSubscription::create([
            'turf_id' => $turf->id,
            'subscription_package_id' => $pkg->id,
            'subscription_payment_id' => $pmt->id,
            'billing_cycle' => 'monthly',
            'price' => 1000.00,
            'commission_percentage' => 2.00,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(29),
            'status' => 'active',
        ]);

        $this->assertEquals(2.00, $turf->commission_percentage);
    }

    public function test_turf_added_during_trial_also_gets_zero_commission(): void
    {
        SaasSetting::create([
            'free_trial_days' => 30,
            'commission_percentage' => 8.00,
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('turf-admin');
        $owner->startTurfOwnerTrial();

        $loc1 = Location::create(['user_id' => $owner->id, 'name' => 'Location 1', 'address' => 'Addr 1']);
        $turf1 = Turf::create(['location_id' => $loc1->id, 'name' => 'Turf 1', 'type' => 'Football']);

        $loc2 = Location::create(['user_id' => $owner->id, 'name' => 'Location 2', 'address' => 'Addr 2']);
        $turf2 = Turf::create(['location_id' => $loc2->id, 'name' => 'Turf 2', 'type' => 'Badminton']);

        $this->assertEquals(0.00, $turf1->commission_percentage);
        $this->assertEquals(0.00, $turf2->commission_percentage);
    }

    public function test_buying_plan_during_trial_starts_at_trial_ends_at(): void
    {
        $setting = SaasSetting::create([
            'free_trial_days' => 30,
            'commission_percentage' => 8.00,
            'razorpay_key' => 'rzp_test_key',
            'razorpay_secret' => 'rzp_test_secret',
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('turf-admin');
        $owner->startTurfOwnerTrial();

        $loc = Location::create(['user_id' => $owner->id, 'name' => 'Main Center', 'address' => '100 Road']);
        $turf = Turf::create(['location_id' => $loc->id, 'name' => 'Turf Pro', 'type' => 'Football']);

        $pkg = SubscriptionPackage::create([
            'name' => 'Standard Package',
            'pricing_type' => 'commission',
            'commission_percentage' => 0.00,
            'monthly_amount' => 1500.00,
            'yearly_amount' => 15000.00,
            'is_active' => true,
        ]);

        $payment = SubscriptionPayment::create([
            'user_id' => $owner->id,
            'subscription_package_id' => $pkg->id,
            'billing_cycle' => 'monthly',
            'amount' => 1500.00,
            'turf_ids' => [$turf->id],
            'turf_count' => 1,
            'razorpay_order_id' => 'order_123',
            'status' => 'pending',
        ]);

        $this->actingAs($owner);

        $signature = hash_hmac('sha256', 'order_123|pay_123', 'rzp_test_secret');

        Volt::test('turf.subscription-manager')
            ->call('verifyPayment', $payment->id, 'pay_123', $signature);

        $sub = TurfSubscription::where('turf_id', $turf->id)->first();
        $this->assertNotNull($sub);

        // Starts at trial end (now + 30 days) and expires 30 days later
        $expectedStart = $owner->trial_ends_at;
        $expectedExpire = $expectedStart->copy()->addDays(30);

        $this->assertEquals($expectedStart->format('Y-m-d H:i:s'), $sub->starts_at->format('Y-m-d H:i:s'));
        $this->assertEquals($expectedExpire->format('Y-m-d H:i:s'), $sub->expires_at->format('Y-m-d H:i:s'));
    }

    public function test_saas_admin_adding_role_starts_trial_editing_existing_does_not(): void
    {
        SaasSetting::create(['free_trial_days' => 30]);

        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');
        $this->actingAs($admin);

        // 1. Create a user with turf-admin role
        Volt::test('saas.user-manager')
            ->set('name', 'New Owner')
            ->set('email', 'newowner@test.com')
            ->set('mobile', '9876543210')
            ->set('password', 'password123')
            ->set('selectedRoles', ['turf-admin'])
            ->call('saveUser');

        $newOwner = User::where('email', 'newowner@test.com')->first();
        $this->assertNotNull($newOwner);
        $this->assertTrue($newOwner->onTrial());
        $initialEnd = $newOwner->trial_ends_at->copy();

        // 2. Editing existing owner does not restart trial
        Carbon::setTestNow('2026-10-25 10:00:00');

        Volt::test('saas.user-manager')
            ->call('editUser', $newOwner->id)
            ->set('name', 'Updated Owner Name')
            ->call('saveUser');

        $newOwner->refresh();
        $this->assertEquals($initialEnd->format('Y-m-d H:i:s'), $newOwner->trial_ends_at->format('Y-m-d H:i:s'));
    }

    public function test_extend_trial_adds_days_from_later_of_now_and_current_end(): void
    {
        SaasSetting::create(['free_trial_days' => 30]);

        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');
        $this->actingAs($admin);

        // Existing owner with no trial
        $existingOwner = User::factory()->create();
        $existingOwner->assignRole('turf-admin');
        $this->assertFalse($existingOwner->onTrial());

        Volt::test('saas.user-manager')
            ->call('extendTrial', $existingOwner->id);

        $existingOwner->refresh();
        $this->assertTrue($existingOwner->onTrial());
        $this->assertEquals('2026-11-20 10:00:00', $existingOwner->trial_ends_at->format('Y-m-d H:i:s'));

        // Extending an active trial adds from current trial_ends_at
        Volt::test('saas.user-manager')
            ->call('extendTrial', $existingOwner->id, 15);

        $existingOwner->refresh();
        $this->assertEquals('2026-12-05 10:00:00', $existingOwner->trial_ends_at->format('Y-m-d H:i:s'));
    }

    public function test_screens_trial_banners_and_badges(): void
    {
        SaasSetting::create(['free_trial_days' => 30]);

        $owner = User::factory()->create();
        $owner->assignRole('turf-admin');
        $owner->startTurfOwnerTrial();

        $loc = Location::create(['user_id' => $owner->id, 'name' => 'Demo Club', 'address' => 'Demo Rd']);
        $turf = Turf::create(['location_id' => $loc->id, 'name' => 'Demo Court', 'type' => 'Tennis']);

        $this->actingAs($owner);

        // Subscription page on trial
        Volt::test('turf.subscription-manager')
            ->assertSee('Free trial: 30 days left')
            ->assertSee('Plan:')
            ->assertSee('Free Trial');

        // Dashboard reminder: not visible when 30 days left
        Volt::test('turf.dashboard-manager')
            ->assertDontSee('Your free trial ends in');

        // Advance to 5 days left: dashboard reminder is now visible
        Carbon::setTestNow('2026-11-15 10:00:00');
        Volt::test('turf.dashboard-manager')
            ->assertSee('Your free trial ends in 5 days');

        // After trial ends
        Carbon::setTestNow('2026-11-22 10:00:00');
        Volt::test('turf.subscription-manager')
            ->assertSee('Your free trial ended on 20 Nov 2026');
    }

    public function test_business_and_earnings_shows_turf_rate_and_free_trial(): void
    {
        SaasSetting::create([
            'free_trial_days' => 30,
            'commission_percentage' => 8.00,
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('turf-admin');
        $owner->startTurfOwnerTrial();

        $loc = Location::create(['user_id' => $owner->id, 'name' => 'Biz Club', 'address' => 'Biz Rd']);
        $turf = Turf::create(['location_id' => $loc->id, 'name' => 'Biz Turf', 'type' => 'Cricket']);

        $this->actingAs($owner);

        // While on trial
        Volt::test('turf.business-manager')
            ->assertSee('0% · Free Trial');

        // After trial ends: shows default rate
        Carbon::setTestNow('2026-11-25 10:00:00');
        Volt::test('turf.business-manager')
            ->assertSee('8.00% Commission');
    }

    public function test_reminders_sent_three_days_before_and_last_day(): void
    {
        SaasSetting::create(['free_trial_days' => 30]);

        $owner = User::factory()->create();
        $owner->assignRole('turf-admin');
        $owner->startTurfOwnerTrial();

        // 1. Move to 3 days before expiry (day 27)
        Carbon::setTestNow('2026-11-17 10:00:00'); // 3 days left
        $this->assertEquals(3, $owner->trialDaysLeft());

        $this->artisan('trials:send-reminders')
            ->expectsOutputToContain('1 3-day reminders, 0 last-day reminders')
            ->assertExitCode(0);

        $owner->refresh();
        $this->assertNotNull($owner->trial_reminder_3d_sent_at);

        // Running again doesn't resend
        $this->artisan('trials:send-reminders')
            ->expectsOutputToContain('0 3-day reminders, 0 last-day reminders')
            ->assertExitCode(0);

        // 2. Move to last day (<= 1 day left)
        Carbon::setTestNow('2026-11-20 09:00:00');
        $this->assertEquals(1, $owner->trialDaysLeft());

        $this->artisan('trials:send-reminders')
            ->expectsOutputToContain('0 3-day reminders, 1 last-day reminders')
            ->assertExitCode(0);

        $owner->refresh();
        $this->assertNotNull($owner->trial_reminder_0d_sent_at);

        // Running again doesn't resend
        $this->artisan('trials:send-reminders')
            ->expectsOutputToContain('0 3-day reminders, 0 last-day reminders')
            ->assertExitCode(0);
    }

    public function test_api_returns_trial_ends_at_and_on_trial(): void
    {
        SaasSetting::create(['free_trial_days' => 30]);

        $owner = User::factory()->create([
            'email' => 'apiowner@example.com',
            'mobile' => '9876543211',
            'password' => bcrypt('secret123'),
        ]);
        $owner->assignRole('turf-admin');
        $owner->startTurfOwnerTrial();

        // Test login
        $loginRes = $this->postJson('/api/login', [
            'login' => 'apiowner@example.com',
            'password' => 'secret123',
        ]);
        $loginRes->assertOk();
        $loginRes->assertJsonPath('user.on_trial', true);
        $this->assertNotNull($loginRes->json('user.trial_ends_at'));

        // Test user/me
        $token = $loginRes->json('access_token');
        $meRes = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/user');
        $meRes->assertOk();
        $meRes->assertJsonPath('on_trial', true);
        $this->assertNotNull($meRes->json('trial_ends_at'));
    }

    public function test_existing_owners_created_via_assign_role_have_no_trial_and_default_commission(): void
    {
        SaasSetting::create([
            'free_trial_days' => 30,
            'commission_percentage' => 8.00,
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('turf-admin');

        $this->assertFalse($owner->onTrial());
        $this->assertNull($owner->trial_started_at);
        $this->assertNull($owner->trial_ends_at);

        $loc = Location::create(['user_id' => $owner->id, 'name' => 'Loc', 'address' => 'Addr']);
        $turf = Turf::create(['location_id' => $loc->id, 'name' => 'Turf', 'type' => 'Football']);

        $this->assertEquals(8.00, $turf->commission_percentage);
    }
}
