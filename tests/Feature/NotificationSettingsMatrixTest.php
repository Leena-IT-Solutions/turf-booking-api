<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\Booking;
use App\Models\Location;
use App\Models\NotificationDelivery;
use App\Models\NotificationSetting;
use App\Models\SaasSetting;
use App\Models\Turf;
use App\Models\TurfPayout;
use App\Models\User;
use App\Notifications\TurfSubmittedForVerification;
use App\Services\NotificationService;
use App\Support\NotificationEvents;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class NotificationSettingsMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        SaasSetting::create(['app_name' => 'TurfBooking']);
    }

    private function makeTurf(User $owner, string $name = 'Arena One'): Turf
    {
        $location = Location::create([
            'user_id' => $owner->id,
            'name' => "$name Location",
            'address' => 'Mumbai',
        ]);

        return Turf::create([
            'location_id' => $location->id,
            'name' => $name,
            'type' => 'Synthetic',
        ]);
    }

    public function test_registry_defines_all_23_events_across_three_categories(): void
    {
        $all = NotificationEvents::all();
        $this->assertCount(23, $all);

        $grouped = NotificationEvents::byCategory();
        $this->assertCount(9, $grouped[NotificationEvents::CATEGORY_CUSTOMER]);
        $this->assertCount(10, $grouped[NotificationEvents::CATEGORY_VENUE]);
        $this->assertCount(4, $grouped[NotificationEvents::CATEGORY_ADMIN]);
    }

    public function test_customer_and_venue_booking_created_toggles_work_independently(): void
    {
        Bus::fake([SendPushNotification::class]);

        $owner = User::factory()->create();
        $customer = User::factory()->create();
        $turf = $this->makeTurf($owner);

        $booking = Booking::forceCreate([
            'user_id' => $customer->id,
            'turf_id' => $turf->id,
            'status' => 'Confirmed',
            'date_of_booking' => now(),
            'booking_number' => 'TB-IND-001',
        ]);

        // Disable customer.booking_created only; keep venue.booking_created ON
        NotificationSetting::updateOrCreate(
            ['event_key' => 'customer.booking_created'],
            ['push_enabled' => false]
        );
        NotificationSetting::updateOrCreate(
            ['event_key' => 'venue.booking_created'],
            ['push_enabled' => true]
        );

        NotificationService::notifyBookingCreated($booking);

        Bus::assertNotDispatched(SendPushNotification::class, fn ($job) => $job->event === 'booking_created');
        Bus::assertDispatched(SendPushNotification::class, fn ($job) => $job->event === 'manager_booking_created' && $job->userIds === [$owner->id]);

        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $customer->id,
            'event' => 'booking_created',
            'status' => NotificationDelivery::STATUS_SKIPPED_DISABLED,
        ]);
    }

    public function test_promotional_events_respect_user_promo_opt_out_while_transactional_always_send(): void
    {
        Bus::fake([SendPushNotification::class]);

        $optedOutUser = User::factory()->create(['promo_notifications_enabled' => false]);
        $optedInUser = User::factory()->create(['promo_notifications_enabled' => true]);

        // Promotional event (customer.match_reminder) skips optedOutUser, sends to optedInUser
        NotificationService::dispatchEventPush(
            'customer.match_reminder',
            [$optedOutUser->id, $optedInUser->id],
            'Match Reminder',
            'Starts in 1 hour',
            ['type' => 'match_reminder']
        );

        Bus::assertDispatched(SendPushNotification::class, function ($job) use ($optedInUser) {
            return $job->event === 'match_reminder'
                && $job->userIds === [$optedInUser->id]
                && ($job->data['android_channel_id'] ?? null) === 'reminders';
        });

        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $optedOutUser->id,
            'event' => 'match_reminder',
            'status' => NotificationDelivery::STATUS_SKIPPED_OPT_OUT,
        ]);

        // Transactional event (customer.payment_received) still sends to optedOutUser
        NotificationService::dispatchEventPush(
            'customer.payment_received',
            [$optedOutUser->id],
            'Payment Received',
            '₹500 received',
            ['type' => 'payment_received']
        );

        Bus::assertDispatched(SendPushNotification::class, fn ($job) => $job->event === 'payment_received' && $job->userIds === [$optedOutUser->id]);
    }

    public function test_wrapped_payout_and_trial_and_turf_submitted_respect_toggles(): void
    {
        Bus::fake([SendPushNotification::class]);
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $owner = User::factory()->create();
        $owner->assignRole('turf-admin');
        $turf = $this->makeTurf($owner, 'Alpha Turf');

        $payout = TurfPayout::forceCreate([
            'user_id' => $owner->id,
            'requested_amount' => 1000,
            'charge_applied' => 10,
            'net_amount' => 990,
            'mode' => 'manual',
            'status' => TurfPayout::STATUS_PENDING,
        ]);

        // 1. Disable venue.payout_paid -> no push dispatched
        NotificationSetting::updateOrCreate(['event_key' => 'venue.payout_paid'], ['push_enabled' => false]);
        NotificationService::notifyPayoutPaid($payout, 'UTR999');
        Bus::assertNotDispatched(SendPushNotification::class, fn ($job) => $job->event === 'payout_paid');

        // 2. Enable venue.payout_paid -> push dispatched
        NotificationSetting::updateOrCreate(['event_key' => 'venue.payout_paid'], ['push_enabled' => true]);
        NotificationService::notifyPayoutPaid($payout, 'UTR999');
        Bus::assertDispatched(SendPushNotification::class, fn ($job) => $job->event === 'payout_paid' && $job->userIds === [$owner->id]);

        // 3. admin.turf_submitted controls Push and Email independently
        NotificationSetting::updateOrCreate(
            ['event_key' => 'admin.turf_submitted'],
            ['push_enabled' => true, 'email_enabled' => false]
        );

        NotificationService::notifyTurfSubmittedForVerification($turf, $owner);

        Bus::assertDispatched(SendPushNotification::class, fn ($job) => $job->event === 'admin_turf_submitted' && in_array($admin->id, $job->userIds, true));
        Notification::assertNothingSent();

        // Turn email ON -> email is sent
        NotificationSetting::updateOrCreate(
            ['event_key' => 'admin.turf_submitted'],
            ['push_enabled' => true, 'email_enabled' => true]
        );
        NotificationService::notifyTurfSubmittedForVerification($turf, $owner);
        Notification::assertSentTo($admin, TurfSubmittedForVerification::class);
    }

    public function test_saas_admin_notification_settings_volt_ui_auto_saves_toggles_and_lead_time(): void
    {
        Bus::fake([SendPushNotification::class]);

        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $this->actingAs($admin);

        $component = Volt::test('saas.settings.notifications')
            ->assertSee('Push & Email Notification Control Center')
            ->assertSee('23 of 23 Active')
            ->call('toggleEvent', 'customer.new_offer', 'push');

        $this->assertFalse(NotificationSetting::isEnabled('customer.new_offer', 'push'));

        // Update match reminder lead time to 120 minutes (2 hours)
        $component->set('reminderLeadMinutes', 120);
        $this->assertSame(120, NotificationSetting::configValue('customer.match_reminder', 'lead_minutes'));

        // Disable entire venue category
        $component->call('setCategoryEnabled', 'venue', false);
        $this->assertFalse(NotificationSetting::isEnabled('venue.booking_created', 'push'));
        $this->assertFalse(NotificationSetting::isEnabled('venue.daily_digest', 'push'));

        // Send test push to admin
        $component->call('sendTestPush', 'customer.booking_created');
        Bus::assertDispatched(SendPushNotification::class, fn ($job) => $job->event === 'test.customer.booking_created' && $job->userIds === [$admin->id]);
    }

    public function test_user_can_toggle_promo_notification_preferences_via_api(): void
    {
        $user = User::factory()->create(['promo_notifications_enabled' => true]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/user/notification-preferences', ['promo_notifications_enabled' => false])
            ->assertOk()
            ->assertJsonPath('promo_notifications_enabled', false);

        $this->assertFalse((bool) $user->fresh()->promo_notifications_enabled);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('promo_notifications_enabled', false);
    }
}
