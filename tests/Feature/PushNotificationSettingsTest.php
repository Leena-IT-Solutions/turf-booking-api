<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\DeviceToken;
use App\Models\NotificationLog;
use App\Models\SaasSetting;
use App\Models\Turf;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use Mockery;
use Tests\TestCase;

class PushNotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        SaasSetting::create([
            'app_name' => 'TurfBooking',
            'notify_booking_created' => true,
            'notify_booking_cancelled' => true,
            'notify_payment_received' => true,
            'fcm_project_id' => 'test-project',
            'fcm_service_account_json' => json_encode([
                'type' => 'service_account',
                'project_id' => 'test-project',
                'private_key' => "-----BEGIN RSA PRIVATE KEY-----\nMIIEowIBAAKCAQEA0...\n-----END RSA PRIVATE KEY-----\n",
                'client_email' => 'firebase-adminsdk@test-project.iam.gserviceaccount.com',
            ]),
        ]);
    }

    public function test_booking_cancelled_toggle_off_does_not_call_messaging_client()
    {
        SaasSetting::first()->update(['notify_booking_cancelled' => false]);

        $messagingMock = Mockery::mock(Messaging::class);
        $messagingMock->shouldNotReceive('sendMulticast');
        $this->app->instance(Messaging::class, $messagingMock);

        $customer = User::factory()->create();
        DeviceToken::create([
            'user_id' => $customer->id,
            'device_token' => 'cust_token_123',
            'device_type' => 'android',
        ]);

        $booking = new Booking([
            'user_id' => $customer->id,
            'booking_reference' => 'TB-CANCEL-1',
        ]);
        $booking->id = 99;
        $booking->setRelation('turf', new Turf(['name' => 'Arena Alpha']));

        NotificationService::notifyBookingCancelled($booking);
    }

    public function test_booking_cancelled_toggle_on_calls_messaging_client()
    {
        SaasSetting::first()->update(['notify_booking_cancelled' => true]);

        $report = MulticastSendReport::withItems([
            SendReport::success(
                \Kreait\Firebase\Messaging\MessageTarget::with(\Kreait\Firebase\Messaging\MessageTarget::TOKEN, 'cust_token_123'),
                ['name' => 'projects/test-project/messages/123']
            ),
        ]);

        $messagingMock = Mockery::mock(Messaging::class);
        $messagingMock->shouldReceive('sendMulticast')
            ->once()
            ->with(Mockery::type(CloudMessage::class), ['cust_token_123'])
            ->andReturn($report);
        $this->app->instance(Messaging::class, $messagingMock);

        $customer = User::factory()->create();
        DeviceToken::create([
            'user_id' => $customer->id,
            'device_token' => 'cust_token_123',
            'device_type' => 'android',
        ]);

        $booking = new Booking([
            'user_id' => $customer->id,
            'booking_reference' => 'TB-CANCEL-2',
        ]);
        $booking->id = 100;
        $booking->setRelation('turf', new Turf(['name' => 'Arena Beta']));

        NotificationService::notifyBookingCancelled($booking);
    }

    public function test_send_custom_notification_targeting_and_audit_log()
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $turfAdmin = User::factory()->create();
        $turfAdmin->assignRole('turf-admin');
        DeviceToken::create(['user_id' => $turfAdmin->id, 'device_token' => 'ta_token_1', 'device_type' => 'android']);

        $manager = User::factory()->create();
        $manager->assignRole('manager');
        DeviceToken::create(['user_id' => $manager->id, 'device_token' => 'mgr_token_1', 'device_type' => 'ios']);

        $cust1 = User::factory()->create();
        $cust1->assignRole('customer');
        DeviceToken::create(['user_id' => $cust1->id, 'device_token' => 'c1_token_1', 'device_type' => 'android']);

        $cust2 = User::factory()->create();
        $cust2->assignRole('customer');
        DeviceToken::create(['user_id' => $cust2->id, 'device_token' => 'c2_token_1', 'device_type' => 'ios']);

        $report = MulticastSendReport::withItems([]);

        $messagingMock = Mockery::mock(Messaging::class);
        $messagingMock->shouldReceive('sendMulticast')->andReturn($report);
        $this->app->instance(Messaging::class, $messagingMock);

        // 1. Target customers: should resolve to exactly cust1 and cust2 tokens (count: 2)
        $countCust = NotificationService::sendCustomNotification(
            'Special Offer',
            '20% off slots today!',
            'customers',
            null,
            $admin
        );
        $this->assertEquals(2, $countCust);

        $this->assertDatabaseHas('notification_logs', [
            'sent_by_user_id' => $admin->id,
            'title' => 'Special Offer',
            'audience_type' => 'customers',
            'recipient_count' => 2,
        ]);

        // 2. Target specific_user: cust1 only
        $countSpecific = NotificationService::sendCustomNotification(
            'Personal Alert',
            'Your slot is ready',
            'specific_user',
            $cust1->id,
            $admin
        );
        $this->assertEquals(1, $countSpecific);

        $this->assertDatabaseHas('notification_logs', [
            'sent_by_user_id' => $admin->id,
            'title' => 'Personal Alert',
            'audience_type' => 'specific_user',
            'target_user_id' => $cust1->id,
            'recipient_count' => 1,
        ]);
    }

    public function test_unregistered_fcm_token_is_pruned_from_database()
    {
        $user = User::factory()->create();
        DeviceToken::create(['user_id' => $user->id, 'device_token' => 'valid_token_1', 'device_type' => 'android']);
        DeviceToken::create(['user_id' => $user->id, 'device_token' => 'dead_token_2', 'device_type' => 'android']);

        $deadTarget = \Kreait\Firebase\Messaging\MessageTarget::with(\Kreait\Firebase\Messaging\MessageTarget::TOKEN, 'dead_token_2');
        $failure = SendReport::failure($deadTarget, new \Kreait\Firebase\Exception\Messaging\NotFound('Requested entity was not found. Token unregistered.'));

        $report = MulticastSendReport::withItems([$failure]);

        $messagingMock = Mockery::mock(Messaging::class);
        $messagingMock->shouldReceive('sendMulticast')->once()->andReturn($report);
        $this->app->instance(Messaging::class, $messagingMock);

        NotificationService::sendFcmNotification([$user->id], 'Hello', 'World');

        $this->assertDatabaseHas('device_tokens', ['device_token' => 'valid_token_1']);
        $this->assertDatabaseMissing('device_tokens', ['device_token' => 'dead_token_2']);
    }

    public function test_malformed_or_empty_credentials_does_not_throw()
    {
        SaasSetting::first()->update(['fcm_service_account_json' => '{invalid_json:']);

        // Container resolution with invalid JSON should return null without crashing
        $this->app->forgetInstance(Messaging::class);

        // Call sendFcmNotification: should log warning and return early gracefully
        $user = User::factory()->create();
        DeviceToken::create(['user_id' => $user->id, 'device_token' => 'tok_1', 'device_type' => 'android']);

        NotificationService::sendFcmNotification([$user->id], 'Test Title', 'Test Body');
        $this->assertTrue(true); // Reaching here means no unhandled exception
    }

    public function test_delete_device_token_removes_only_authenticated_users_token()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        DeviceToken::create(['user_id' => $userA->id, 'device_token' => 'token_a', 'device_type' => 'android']);
        DeviceToken::create(['user_id' => $userB->id, 'device_token' => 'token_b', 'device_type' => 'ios']);

        $this->actingAs($userA, 'sanctum');

        $response = $this->deleteJson('/api/user/device-token', [
            'device_token' => 'token_a',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('device_tokens', ['device_token' => 'token_a']);
        $this->assertDatabaseHas('device_tokens', ['device_token' => 'token_b']);
    }
}
