<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\Booking;
use App\Models\CommissionInvoice;
use App\Models\Coupon;
use App\Models\Location;
use App\Models\NotificationDelivery;
use App\Models\NotificationSetting;
use App\Models\SaasSetting;
use App\Models\SupportMessage;
use App\Models\Turf;
use App\Models\TurfPayout;
use App\Models\User;
use App\Notifications\CommissionInvoiceGeneratedNotification;
use App\Notifications\TurfVerificationStatusNotification;
use App\Services\NotificationService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationRealtimeTriggersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        SaasSetting::create(['app_name' => 'TurfBooking']);
    }

    private function makeTurf(User $owner, string $name = 'Legends Turf'): Turf
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

    public function test_support_chat_notifies_admins_on_user_message_and_user_on_admin_reply(): void
    {
        Bus::fake([SendPushNotification::class]);

        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $customer = User::factory()->create(['name' => 'Aarav Patel']);
        $customer->assignRole('customer');

        // 1. Customer sends message via API -> notifies SaaS Admin
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/support/messages', ['message' => 'Can I add 1 more hour to my booking?'])
            ->assertCreated();

        Bus::assertDispatched(SendPushNotification::class, function ($job) use ($admin) {
            return $job->event === 'admin_support_message'
                && in_array($admin->id, $job->userIds, true)
                && str_contains($job->body, 'Aarav Patel');
        });

        // 2. Admin replies -> notifies Customer
        $reply = SupportMessage::create([
            'user_id' => $customer->id,
            'sender_id' => $admin->id,
            'message' => 'Yes, we have extended your slot by 1 hour!',
            'is_read_by_user' => false,
            'is_read_by_admin' => true,
        ]);

        NotificationService::notifySupportReplyToUser($reply);

        Bus::assertDispatched(SendPushNotification::class, function ($job) use ($customer) {
            return $job->event === 'support_reply'
                && $job->userIds === [$customer->id]
                && str_contains($job->body, 'extended your slot');
        });
    }

    public function test_turf_verification_notifies_owner_via_push_and_email_and_respects_toggles(): void
    {
        Bus::fake([SendPushNotification::class]);
        Notification::fake();

        $owner = User::factory()->create();
        $owner->assignRole('turf-admin');
        $turf = $this->makeTurf($owner, 'Champions Arena');

        NotificationService::notifyTurfVerificationStatus($turf, 'Approved');

        Bus::assertDispatched(SendPushNotification::class, function ($job) use ($owner) {
            return $job->event === 'turf_verification'
                && $job->userIds === [$owner->id]
                && str_contains($job->title, 'Verified & Live');
        });
        Notification::assertSentTo($owner, TurfVerificationStatusNotification::class);

        // Disable email channel only
        Notification::fake();
        NotificationSetting::updateOrCreate(
            ['event_key' => 'venue.turf_verification'],
            ['push_enabled' => true, 'email_enabled' => false]
        );

        NotificationService::notifyTurfVerificationStatus($turf, 'Rejected');
        Notification::assertNothingSent();
    }

    public function test_new_review_submission_notifies_turf_owner(): void
    {
        Bus::fake([SendPushNotification::class]);

        $owner = User::factory()->create();
        $customer = User::factory()->create(['name' => 'Rohan']);
        $turf = $this->makeTurf($owner, 'Skyline Turf');

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/turfs/{$turf->id}/reviews", [
                'rating' => 5,
                'comment' => 'Best turf in Mumbai!',
            ])
            ->assertOk();

        Bus::assertDispatched(SendPushNotification::class, function ($job) use ($owner) {
            return $job->event === 'new_review'
                && $job->userIds === [$owner->id]
                && str_contains($job->title, '5★ Review')
                && str_contains($job->body, 'Best turf in Mumbai!');
        });
    }

    public function test_commission_invoice_and_payout_failed_notifications(): void
    {
        Bus::fake([SendPushNotification::class]);
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $owner = User::factory()->create();
        $owner->assignRole('turf-admin');
        $turf = $this->makeTurf($owner, 'Metro Turf');

        $invoice = CommissionInvoice::forceCreate([
            'invoice_number' => 'INV-202610-0001',
            'turf_id' => $turf->id,
            'owner_user_id' => $owner->id,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'is_gst_invoice' => true,
            'is_inter_state' => false,
            'taxable_amount' => 1000,
            'cgst_amount' => 90,
            'sgst_amount' => 90,
            'igst_amount' => 0,
            'total_amount' => 1180,
            'line_count' => 1,
            'line_items' => [],
            'supplier_snapshot' => ['company_name' => 'TurfBooking'],
            'recipient_snapshot' => ['turf_name' => 'Metro Turf'],
            'generated_at' => now(),
        ]);

        NotificationService::notifyCommissionInvoiceGenerated($invoice);

        Bus::assertDispatched(SendPushNotification::class, fn ($job) => $job->event === 'commission_invoice' && $job->userIds === [$owner->id]);
        Notification::assertSentTo($owner, CommissionInvoiceGeneratedNotification::class);

        $payout = TurfPayout::forceCreate([
            'user_id' => $owner->id,
            'requested_amount' => 2500,
            'charge_applied' => 10,
            'net_amount' => 2490,
            'mode' => 'razorpayx',
            'status' => TurfPayout::STATUS_FAILED,
        ]);

        NotificationService::notifyPayoutFailedToAdmins($payout, 'Bank account frozen');
        Bus::assertDispatched(SendPushNotification::class, fn ($job) => $job->event === 'admin_payout_failed' && in_array($admin->id, $job->userIds, true));
    }

    public function test_targeted_coupon_offer_enforces_180_day_booking_window_rate_limit_and_quiet_hours(): void
    {
        Bus::fake([SendPushNotification::class]);

        $owner = User::factory()->create();
        $turf = $this->makeTurf($owner, 'Goal Arena');
        $otherTurf = $this->makeTurf($owner, 'Other Arena');

        $recentPlayer = User::factory()->create();
        $oldPlayer = User::factory()->create();
        $otherTurfPlayer = User::factory()->create();
        $rateLimitedPlayer = User::factory()->create();

        // Recent booking (30 days ago) -> eligible
        $b1 = Booking::forceCreate([
            'user_id' => $recentPlayer->id,
            'turf_id' => $turf->id,
            'status' => 'Confirmed',
            'date_of_booking' => now()->subDays(30),
            'booking_number' => 'TB-OFFER-1',
        ]);
        $b1->timestamps = false;
        $b1->forceFill(['created_at' => now()->subDays(30)])->save();

        // Old booking (200 days ago, outside 180-day window) -> ineligible
        $b2 = Booking::forceCreate([
            'user_id' => $oldPlayer->id,
            'turf_id' => $turf->id,
            'status' => 'Confirmed',
            'date_of_booking' => now()->subDays(200),
            'booking_number' => 'TB-OFFER-2',
        ]);
        $b2->timestamps = false;
        $b2->forceFill(['created_at' => now()->subDays(200)])->save();

        // Other turf booking -> ineligible
        Booking::forceCreate([
            'user_id' => $otherTurfPlayer->id,
            'turf_id' => $otherTurf->id,
            'status' => 'Confirmed',
            'date_of_booking' => now()->subDays(5),
            'booking_number' => 'TB-OFFER-3',
        ]);

        // Rate-limited player (booked 10 days ago, but already received new_offer 2 hours ago) -> ineligible
        Booking::forceCreate([
            'user_id' => $rateLimitedPlayer->id,
            'turf_id' => $turf->id,
            'status' => 'Confirmed',
            'date_of_booking' => now()->subDays(10),
            'booking_number' => 'TB-OFFER-4',
        ]);
        NotificationDelivery::create([
            'user_id' => $rateLimitedPlayer->id,
            'event' => 'new_offer',
            'channel' => 'push',
            'title' => 'Earlier offer',
            'body' => 'Body',
            'status' => NotificationDelivery::STATUS_SENT,
            'sent_at' => now()->subHours(2),
        ]);

        $coupon = Coupon::create([
            'turf_id' => $turf->id,
            'code' => 'GOAL25',
            'discount_type' => 'percentage',
            'discount_value' => 25,
            'minimum_slots_to_be_ordered' => 1,
            'is_active' => true,
        ]);

        // 1. Quiet hours (11:00 PM IST) -> returns 0 and dispatches nothing
        $quietHourTime = Carbon::parse('2026-11-17 23:00:00', 'Asia/Kolkata');
        $this->assertSame(0, NotificationService::notifyNewOffer($coupon, $quietHourTime));
        Bus::assertNotDispatched(SendPushNotification::class);

        // 2. Daytime (02:00 PM IST) -> sends ONLY to $recentPlayer
        $dayTime = Carbon::parse('2026-11-17 14:00:00', 'Asia/Kolkata');
        $sentCount = NotificationService::notifyNewOffer($coupon, $dayTime);
        $this->assertSame(1, $sentCount);

        Bus::assertDispatched(SendPushNotification::class, function ($job) use ($recentPlayer) {
            return $job->event === 'new_offer'
                && $job->userIds === [$recentPlayer->id]
                && str_contains($job->body, 'GOAL25')
                && str_contains($job->body, '25% off');
        });
    }
}
