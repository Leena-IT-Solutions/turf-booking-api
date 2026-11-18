<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\Booking;
use App\Models\CommissionWalletTransaction;
use App\Models\DeviceToken;
use App\Models\Location;
use App\Models\NotificationDelivery;
use App\Models\SaasSetting;
use App\Models\StaffMember;
use App\Models\SubscriptionPackage;
use App\Models\SubscriptionPayment;
use App\Models\Turf;
use App\Models\TurfPayout;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Livewire\Volt\Volt;
use Mockery;
use Tests\TestCase;

class NotificationFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        SaasSetting::create(['app_name' => 'TurfBooking']);
    }

    private function makeTurfFor(User $owner, string $name = 'Arena'): Turf
    {
        $location = Location::create(['user_id' => $owner->id, 'name' => "$name Location", 'address' => 'Mumbai']);

        return Turf::create(['location_id' => $location->id, 'name' => $name, 'type' => 'Synthetic']);
    }

    public function test_venue_recipients_are_only_the_turf_owner_and_their_staff(): void
    {
        $ownerA = User::factory()->create();
        $ownerA->assignRole('turf-admin');
        $staffA = User::factory()->create();
        $staffA->assignRole('manager');
        StaffMember::create(['turf_admin_id' => $ownerA->id, 'user_id' => $staffA->id, 'role' => 'manager']);

        $ownerB = User::factory()->create();
        $ownerB->assignRole('turf-admin');
        $staffB = User::factory()->create();
        $staffB->assignRole('manager');
        StaffMember::create(['turf_admin_id' => $ownerB->id, 'user_id' => $staffB->id, 'role' => 'manager']);

        $saasAdmin = User::factory()->create();
        $saasAdmin->assignRole('saas-admin');

        $turfA = $this->makeTurfFor($ownerA, 'Arena A');
        $this->makeTurfFor($ownerB, 'Arena B');

        $ids = NotificationService::venueRecipientIds($turfA);
        sort($ids);
        $expected = [$ownerA->id, $staffA->id];
        sort($expected);

        $this->assertSame($expected, $ids);
        $this->assertNotContains($ownerB->id, $ids);
        $this->assertNotContains($staffB->id, $ids);
        $this->assertNotContains($saasAdmin->id, $ids, 'SaaS admins must not receive venue booking notifications.');
    }

    public function test_booking_created_notifies_customer_and_only_that_venue(): void
    {
        Bus::fake([SendPushNotification::class]);

        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $this->makeTurfFor($otherOwner, 'Other Arena');
        $customer = User::factory()->create();
        $turf = $this->makeTurfFor($owner);

        $booking = Booking::forceCreate([
            'user_id' => $customer->id,
            'turf_id' => $turf->id,
            'status' => 'Confirmed',
            'date_of_booking' => now(),

        ]);

        NotificationService::notifyBookingCreated($booking);

        Bus::assertDispatched(SendPushNotification::class, fn ($job) => $job->event === 'booking_created' && $job->userIds === [$customer->id]);
        Bus::assertDispatched(SendPushNotification::class, fn ($job) => $job->event === 'manager_booking_created' && $job->userIds === [$owner->id]);
        Bus::assertNotDispatched(SendPushNotification::class, fn ($job) => in_array($otherOwner->id, $job->userIds, true));
    }

    public function test_push_is_not_sent_when_transaction_rolls_back(): void
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldNotReceive('sendMulticast');
        $this->app->instance(Messaging::class, $messaging);

        $customer = User::factory()->create();
        DeviceToken::create(['user_id' => $customer->id, 'device_token' => 'tok-rollback', 'device_type' => 'android']);

        try {
            DB::transaction(function () use ($customer) {
                NotificationService::sendToUser($customer->id, 'Hello', 'Should never arrive', ['type' => 'test_event']);
                throw new \RuntimeException('force rollback');
            });
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertDatabaseMissing('notification_deliveries', ['event' => 'test_event', 'status' => 'sent']);
    }

    public function test_push_is_sent_after_commit_and_delivery_is_logged(): void
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('sendMulticast')->once()->andReturn(MulticastSendReport::withItems([]));
        $this->app->instance(Messaging::class, $messaging);

        $withDevice = User::factory()->create();
        $withoutDevice = User::factory()->create();
        DeviceToken::create(['user_id' => $withDevice->id, 'device_token' => 'tok-ok', 'device_type' => 'android']);

        DB::transaction(function () use ($withDevice, $withoutDevice) {
            NotificationService::dispatchPush([$withDevice->id, $withoutDevice->id], 'test_event', 'Hi', 'Body', ['type' => 'test_event']);
        });

        $this->assertDatabaseHas('notification_deliveries', ['user_id' => $withDevice->id, 'event' => 'test_event', 'status' => 'sent']);
        $this->assertDatabaseHas('notification_deliveries', ['user_id' => $withoutDevice->id, 'event' => 'test_event', 'status' => 'no_device']);
    }

    public function test_disabled_toggle_records_skipped_delivery(): void
    {
        Bus::fake([SendPushNotification::class]);
        SaasSetting::first()->update(['notify_payment_received' => false]);

        $customer = User::factory()->create();
        $booking = new Booking(['user_id' => $customer->id, 'booking_reference' => 'TB-PAY']);
        $booking->id = 5;

        NotificationService::notifyPaymentRecorded($booking, 500);

        Bus::assertNotDispatched(SendPushNotification::class);
        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $customer->id,
            'event' => 'payment_received',
            'status' => NotificationDelivery::STATUS_SKIPPED_DISABLED,
        ]);
    }

    public function test_settings_cache_is_refreshed_when_settings_are_saved(): void
    {
        $this->assertTrue((bool) (SaasSetting::cached()->notify_booking_cancelled ?? true));

        SaasSetting::first()->update(['notify_booking_cancelled' => false]);

        $this->assertFalse((bool) SaasSetting::cached()->notify_booking_cancelled);
    }

    public function test_partial_cancellation_message_mentions_dates_and_refund(): void
    {
        Bus::fake([SendPushNotification::class]);

        $owner = User::factory()->create();
        $customer = User::factory()->create();
        $turf = $this->makeTurfFor($owner);

        $booking = Booking::forceCreate([
            'user_id' => $customer->id,
            'turf_id' => $turf->id,
            'status' => 'Confirmed',
            'date_of_booking' => now(),
            'booking_number' => 'TB-PART',
        ]);
        foreach (['2026-12-01', '2026-12-02', '2026-12-03'] as $d) {
            $booking->bookingDates()->forceCreate(['booking_date' => $d, 'amount' => 1000]);
        }

        NotificationService::notifyBookingCancelled($booking, 1, 450.0);

        Bus::assertDispatched(SendPushNotification::class, function ($job) {
            return $job->title === 'Booking Date Cancelled ⚠️'
                && str_contains($job->body, '1 date of your booking (TB-PART)')
                && str_contains($job->body, 'Refund of ₹450.00 initiated');
        });

        NotificationService::notifyBookingCancelled($booking, 3, 0.0);

        Bus::assertDispatched(SendPushNotification::class, fn ($job) => $job->title === 'Booking Cancelled ⚠️' && ! str_contains($job->body, 'Refund'));
    }

    public function test_device_token_moves_to_the_user_currently_logged_in(): void
    {
        $previous = User::factory()->create();
        $current = User::factory()->create();
        DeviceToken::create(['user_id' => $previous->id, 'device_token' => 'shared-phone', 'device_type' => 'android']);

        $this->actingAs($current, 'sanctum')
            ->postJson('/api/user/device-token', ['device_token' => 'shared-phone', 'device_type' => 'android'])
            ->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['user_id' => $previous->id, 'device_token' => 'shared-phone']);
        $this->assertDatabaseHas('device_tokens', ['user_id' => $current->id, 'device_token' => 'shared-phone']);
    }

    public function test_turf_bookings_search_by_booking_number_succeeds(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('turf-admin');
        $customer = User::factory()->create();
        $turf = $this->makeTurfFor($owner, 'Search Arena');

        Booking::forceCreate([
            'user_id' => $customer->id,
            'turf_id' => $turf->id,
            'status' => 'Confirmed',
            'date_of_booking' => now(),
            'booking_number' => 'TB-202611-00004',
        ]);

        $this->actingAs($owner)
            ->withSession(['active_turf_id' => $turf->id])
            ->get('/turf/bookings?search=TB-202611-00004')
            ->assertOk()
            ->assertSee('TB-202611-00004');
    }

    public function test_delete_turf_bookings_only_keeps_turf_and_other_turf_bookings_intact(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $owner = User::factory()->create();
        $owner->assignRole('turf-admin');
        $customer = User::factory()->create();

        $turfA = $this->makeTurfFor($owner, 'Turf A');
        $turfB = $this->makeTurfFor($owner, 'Turf B');

        $bookingA = Booking::forceCreate([
            'user_id' => $customer->id,
            'turf_id' => $turfA->id,
            'status' => 'Confirmed',
            'date_of_booking' => now(),
            'booking_number' => 'TB-DEL-A',
        ]);
        $bookingB = Booking::forceCreate([
            'user_id' => $customer->id,
            'turf_id' => $turfB->id,
            'status' => 'Confirmed',
            'date_of_booking' => now(),
            'booking_number' => 'TB-DEL-B',
        ]);

        // 1. Test SaaS Turfs Directory "Delete Bookings Only (Keep Turf)" action
        $this->actingAs($admin);
        Volt::test('saas.turf-directory')
            ->call('confirmDeleteTurf', $turfA->id)
            ->call('performDeleteTurfBookingsOnly');

        $this->assertDatabaseHas('turfs', ['id' => $turfA->id]);
        $this->assertDatabaseMissing('bookings', ['id' => $bookingA->id]);
        $this->assertDatabaseHas('bookings', ['id' => $bookingB->id]);

        // 2. Test CLI `php artisan bookings:reset --turf={id} --with-wallet --force`
        Artisan::call('bookings:reset', [
            '--turf' => $turfB->id,
            '--with-wallet' => true,
            '--force' => true,
        ]);

        $this->assertDatabaseHas('turfs', ['id' => $turfB->id]);
        $this->assertDatabaseMissing('bookings', ['id' => $bookingB->id]);
    }

    public function test_subscription_earnings_sums_completed_revenue_and_clears_abandoned_pending_attempts(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $owner = User::factory()->create();
        $owner->assignRole('turf-admin');

        $pkg = SubscriptionPackage::create([
            'name' => 'Standard Turf Partner',
            'monthly_price' => 999,
            'yearly_price' => 9999,
            'is_active' => true,
        ]);

        // 1 completed payment (status = 'completed')
        $paid = SubscriptionPayment::create([
            'user_id' => $owner->id,
            'subscription_package_id' => $pkg->id,
            'billing_cycle' => 'monthly',
            'amount' => 999.00,
            'turf_ids' => [1],
            'turf_count' => 1,
            'razorpay_order_id' => 'order_paid_1',
            'razorpay_payment_id' => 'pay_Tv5YLisccdNozFn',
            'status' => 'completed',
        ]);

        // 2 abandoned pending attempts (no razorpay_payment_id)
        $abandoned1 = SubscriptionPayment::create([
            'user_id' => $owner->id,
            'subscription_package_id' => $pkg->id,
            'billing_cycle' => 'monthly',
            'amount' => 999.00,
            'turf_ids' => [1],
            'turf_count' => 1,
            'razorpay_order_id' => 'order_pend_1',
            'status' => 'pending',
        ]);
        $abandoned2 = SubscriptionPayment::create([
            'user_id' => $owner->id,
            'subscription_package_id' => $pkg->id,
            'billing_cycle' => 'monthly',
            'amount' => 999.00,
            'turf_ids' => [1],
            'turf_count' => 1,
            'razorpay_order_id' => 'order_pend_2',
            'status' => 'pending',
        ]);

        $this->actingAs($admin);

        Volt::test('saas.subscription-earnings-manager')
            ->assertViewHas('totalRevenue', 999.0)
            ->assertViewHas('pendingAbandonedCount', 2)
            ->call('clearAbandonedPendingPayments')
            ->assertViewHas('pendingAbandonedCount', 0);

        $this->assertDatabaseHas('subscription_payments', ['id' => $paid->id]);
        $this->assertDatabaseMissing('subscription_payments', ['id' => $abandoned1->id]);
        $this->assertDatabaseMissing('subscription_payments', ['id' => $abandoned2->id]);
    }

    public function test_reset_user_wallet_and_payouts_via_ui_and_cli(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $owner1 = User::factory()->create(['commission_wallet_balance' => 1500.00]);
        $owner1->assignRole('turf-admin');

        $owner2 = User::factory()->create(['commission_wallet_balance' => 800.00]);
        $owner2->assignRole('turf-admin');

        CommissionWalletTransaction::create([
            'user_id' => $owner1->id,
            'type' => 'booking_credit',
            'amount' => 1500.00,
            'balance_after' => 1500.00,
            'description' => 'Owner 1 credit',
        ]);
        CommissionWalletTransaction::create([
            'user_id' => $owner2->id,
            'type' => 'booking_credit',
            'amount' => 800.00,
            'balance_after' => 800.00,
            'description' => 'Owner 2 credit',
        ]);

        $p1 = TurfPayout::forceCreate([
            'user_id' => $owner1->id,
            'requested_amount' => 500,
            'charge_applied' => 10,
            'net_amount' => 490,
            'mode' => 'manual',
            'status' => TurfPayout::STATUS_COMPLETED,
        ]);
        $p2 = TurfPayout::forceCreate([
            'user_id' => $owner2->id,
            'requested_amount' => 300,
            'charge_applied' => 10,
            'net_amount' => 290,
            'mode' => 'manual',
            'status' => TurfPayout::STATUS_COMPLETED,
        ]);

        // 1. Reset Owner 1's wallet & payouts via SaaS Commission Manager UI
        $this->actingAs($admin);
        Volt::test('saas.commission-manager')
            ->call('resetUserWalletAndPayouts', $owner1->id);

        $this->assertSame(0.0, (float) $owner1->fresh()->commission_wallet_balance);
        $this->assertDatabaseMissing('commission_wallet_transactions', ['user_id' => $owner1->id]);
        $this->assertDatabaseMissing('turf_payouts', ['id' => $p1->id]);

        // Owner 2 is completely untouched
        $this->assertSame(800.0, (float) $owner2->fresh()->commission_wallet_balance);
        $this->assertDatabaseHas('turf_payouts', ['id' => $p2->id]);

        // 2. Reset Owner 2 via CLI `php artisan wallet:reset --user={id} --force`
        Artisan::call('wallet:reset', [
            '--user' => $owner2->id,
            '--force' => true,
        ]);

        $this->assertSame(0.0, (float) $owner2->fresh()->commission_wallet_balance);
        $this->assertDatabaseMissing('commission_wallet_transactions', ['user_id' => $owner2->id]);
        $this->assertDatabaseMissing('turf_payouts', ['id' => $p2->id]);
    }
}
