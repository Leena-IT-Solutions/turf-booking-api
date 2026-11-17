<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\Booking;
use App\Models\DeviceToken;
use App\Models\Location;
use App\Models\NotificationDelivery;
use App\Models\SaasSetting;
use App\Models\StaffMember;
use App\Models\Turf;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\MulticastSendReport;
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
}
