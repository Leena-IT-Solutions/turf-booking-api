<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\BookingSlot;
use App\Models\Location;
use App\Models\NotificationSetting;
use App\Models\Review;
use App\Models\SaasSetting;
use App\Models\Slot;
use App\Models\SlotCategory;
use App\Models\Turf;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class NotificationScheduledCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        SaasSetting::create(['app_name' => 'TurfBooking']);
    }

    private function makeTurfWithSlot(User $owner, string $fromTime = '18:00:00', string $toTime = '19:00:00'): array
    {
        $location = Location::create([
            'user_id' => $owner->id,
            'name' => 'Mumbai Arena',
            'address' => 'Ghatkopar',
        ]);

        $turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Legends Turf',
            'type' => 'Synthetic',
        ]);

        $category = SlotCategory::firstOrCreate(
            ['name' => 'Evening'],
            ['is_active' => true]
        );

        $slot = Slot::create([
            'slot_category_id' => $category->id,
            'from_time' => $fromTime,
            'to_time' => $toTime,
            'duration' => 60,
            'is_active' => true,
        ]);

        return [$turf, $slot];
    }

    public function test_match_reminder_respects_configurable_lead_time_idempotency_and_quiet_hours(): void
    {
        Bus::fake([SendPushNotification::class]);

        $owner = User::factory()->create();
        $customer = User::factory()->create();
        [$turf, $slot] = $this->makeTurfWithSlot($owner, '18:00:00', '19:00:00');

        $booking = Booking::forceCreate([
            'user_id' => $customer->id,
            'turf_id' => $turf->id,
            'status' => 'Confirmed',
            'date_of_booking' => '2026-11-20 10:00:00',
            'booking_number' => 'TB-REM-001',
        ]);

        $bDate = BookingDate::forceCreate([
            'booking_id' => $booking->id,
            'booking_date' => '2026-11-20',
            'status' => 'Confirmed',
            'amount' => 1500,
            'paid_amount' => 500,
            'balance_amount' => 1000,
        ]);

        BookingSlot::create([
            'booking_date_id' => $bDate->id,
            'slot_id' => $slot->id,
            'status' => 'active',
        ]);

        // 1. Default lead_minutes = 60. At 16:00 (2 hours before 18:00), no reminder sent yet
        Artisan::call('notifications:match-reminders', ['--now' => '2026-11-20 16:00:00']);
        Bus::assertNotDispatched(SendPushNotification::class);

        // 2. Admin changes lead_minutes to 120 (2 hours) -> now at 16:00 it IS inside the window!
        NotificationSetting::updateOrCreate(
            ['event_key' => 'customer.match_reminder'],
            ['push_enabled' => true, 'config' => ['lead_minutes' => 120]]
        );

        Artisan::call('notifications:match-reminders', ['--now' => '2026-11-20 16:00:00']);

        Bus::assertDispatched(SendPushNotification::class, function ($job) use ($customer) {
            return $job->event === 'match_reminder'
                && $job->userIds === [$customer->id]
                && str_contains($job->body, '06:00 PM')
                && str_contains($job->body, 'Balance due at venue: ₹1,000.00');
        });

        $this->assertNotNull($bDate->fresh()->reminder_sent_at);

        // 3. Running again does NOT send a duplicate reminder
        Bus::fake([SendPushNotification::class]);
        Artisan::call('notifications:match-reminders', ['--now' => '2026-11-20 17:15:00']);
        Bus::assertNotDispatched(SendPushNotification::class);
    }

    public function test_match_reminder_holds_daytime_match_during_quiet_hours_but_sends_for_early_morning_match(): void
    {
        Bus::fake([SendPushNotification::class]);

        $owner = User::factory()->create();
        $customer = User::factory()->create();
        [$turf, $slot9am] = $this->makeTurfWithSlot($owner, '09:00:00', '10:00:00');

        $slot6am = Slot::create([
            'slot_category_id' => $slot9am->slot_category_id,
            'from_time' => '06:00:00',
            'to_time' => '07:00:00',
            'duration' => 60,
            'is_active' => true,
        ]);

        // Set lead time to 120 minutes (2 hours)
        NotificationSetting::updateOrCreate(
            ['event_key' => 'customer.match_reminder'],
            ['push_enabled' => true, 'config' => ['lead_minutes' => 120]]
        );

        // Booking A: 09:00 AM match -> at 07:15 AM (inside quiet hours), reminder is HELD until 08:00 AM
        $booking9am = Booking::forceCreate([
            'user_id' => $customer->id,
            'turf_id' => $turf->id,
            'status' => 'Confirmed',
            'date_of_booking' => '2026-11-21 00:00:00',
            'booking_number' => 'TB-9AM',
        ]);
        $bd9am = BookingDate::forceCreate([
            'booking_id' => $booking9am->id,
            'booking_date' => '2026-11-21',
            'status' => 'Confirmed',
            'amount' => 1000,
        ]);
        BookingSlot::create(['booking_date_id' => $bd9am->id, 'slot_id' => $slot9am->id, 'status' => 'active']);

        Artisan::call('notifications:match-reminders', ['--now' => '2026-11-21 07:15:00']);
        Bus::assertNotDispatched(SendPushNotification::class);
        $this->assertNull($bd9am->fresh()->reminder_sent_at);

        // At 08:00 AM (quiet hours end), Booking A reminder goes out!
        Artisan::call('notifications:match-reminders', ['--now' => '2026-11-21 08:00:00']);
        Bus::assertDispatched(SendPushNotification::class, fn ($job) => ($job->data['booking_id'] ?? '') === (string) $booking9am->id);

        // Booking B: 06:00 AM match (starts inside quiet hours) -> at 05:00 AM, reminder DOES go out on time!
        Bus::fake([SendPushNotification::class]);
        $booking6am = Booking::forceCreate([
            'user_id' => $customer->id,
            'turf_id' => $turf->id,
            'status' => 'Confirmed',
            'date_of_booking' => '2026-11-22 00:00:00',
            'booking_number' => 'TB-6AM',
        ]);
        $bd6am = BookingDate::forceCreate([
            'booking_id' => $booking6am->id,
            'booking_date' => '2026-11-22',
            'status' => 'Confirmed',
            'amount' => 1000,
        ]);
        BookingSlot::create(['booking_date_id' => $bd6am->id, 'slot_id' => $slot6am->id, 'status' => 'active']);

        Artisan::call('notifications:match-reminders', ['--now' => '2026-11-22 05:00:00']);
        Bus::assertDispatched(SendPushNotification::class, fn ($job) => ($job->data['booking_id'] ?? '') === (string) $booking6am->id);
    }

    public function test_review_prompt_sends_once_after_booking_completes_and_skips_if_already_reviewed(): void
    {
        Bus::fake([SendPushNotification::class]);

        $owner = User::factory()->create();
        $player1 = User::factory()->create();
        $player2 = User::factory()->create();
        [$turf, $slot] = $this->makeTurfWithSlot($owner, '17:00:00', '18:00:00');

        // Player 1 finished game at 18:00, has not reviewed
        $b1 = Booking::forceCreate([
            'user_id' => $player1->id,
            'turf_id' => $turf->id,
            'status' => 'Confirmed',
            'date_of_booking' => '2026-11-20 10:00:00',
            'booking_number' => 'TB-REV-1',
        ]);
        $bd1 = BookingDate::forceCreate([
            'booking_id' => $b1->id,
            'booking_date' => '2026-11-20',
            'status' => 'Confirmed',
            'amount' => 1000,
        ]);
        BookingSlot::create(['booking_date_id' => $bd1->id, 'slot_id' => $slot->id, 'status' => 'active']);

        // Player 2 finished game at 18:00, but ALREADY reviewed the turf
        $b2 = Booking::forceCreate([
            'user_id' => $player2->id,
            'turf_id' => $turf->id,
            'status' => 'Confirmed',
            'date_of_booking' => '2026-11-20 10:00:00',
            'booking_number' => 'TB-REV-2',
        ]);
        $bd2 = BookingDate::forceCreate([
            'booking_id' => $b2->id,
            'booking_date' => '2026-11-20',
            'status' => 'Confirmed',
            'amount' => 1000,
        ]);
        BookingSlot::create(['booking_date_id' => $bd2->id, 'slot_id' => $slot->id, 'status' => 'active']);
        Review::create(['user_id' => $player2->id, 'turf_id' => $turf->id, 'rating' => 5, 'comment' => 'Great!']);

        // Run at 19:30 (1.5 hours after 18:00 end time)
        Artisan::call('notifications:review-prompts', ['--now' => '2026-11-20 19:30:00']);

        Bus::assertDispatched(SendPushNotification::class, function ($job) use ($player1, $turf) {
            return $job->event === 'review_prompt'
                && $job->userIds === [$player1->id]
                && ($job->data['turf_id'] ?? '') === (string) $turf->id;
        });
        Bus::assertNotDispatched(SendPushNotification::class, fn ($job) => in_array($player2->id, $job->userIds, true));

        $this->assertNotNull($b1->fresh()->review_prompt_sent_at);
        $this->assertNotNull($b2->fresh()->review_prompt_sent_at);
    }

    public function test_venue_daily_digest_sends_summary_only_to_turfs_with_bookings_today(): void
    {
        Bus::fake([SendPushNotification::class]);

        $ownerWithBookings = User::factory()->create();
        $ownerWithoutBookings = User::factory()->create();

        [$turfBusy, $slot] = $this->makeTurfWithSlot($ownerWithBookings, '07:00:00', '08:00:00');
        $this->makeTurfWithSlot($ownerWithoutBookings, '07:00:00', '08:00:00');

        $customer = User::factory()->create();
        $booking = Booking::forceCreate([
            'user_id' => $customer->id,
            'turf_id' => $turfBusy->id,
            'status' => 'Confirmed',
            'date_of_booking' => '2026-11-25 00:00:00',
            'booking_number' => 'TB-DIGEST-1',
        ]);
        $bd = BookingDate::forceCreate([
            'booking_id' => $booking->id,
            'booking_date' => '2026-11-25',
            'status' => 'Confirmed',
            'amount' => 1200,
            'paid_amount' => 400,
            'balance_amount' => 800,
        ]);
        BookingSlot::create(['booking_date_id' => $bd->id, 'slot_id' => $slot->id, 'status' => 'active']);

        Artisan::call('notifications:venue-daily-digest', ['--date' => '2026-11-25']);

        Bus::assertDispatched(SendPushNotification::class, function ($job) use ($ownerWithBookings) {
            return $job->event === 'daily_digest'
                && $job->userIds === [$ownerWithBookings->id]
                && str_contains($job->body, '1 booking scheduled today (first slot 07:00 AM)')
                && str_contains($job->body, '₹800.00');
        });
        Bus::assertNotDispatched(SendPushNotification::class, fn ($job) => in_array($ownerWithoutBookings->id, $job->userIds, true));

        // Running a second time on the same day does not re-send
        Bus::fake([SendPushNotification::class]);
        Artisan::call('notifications:venue-daily-digest', ['--date' => '2026-11-25']);
        Bus::assertNotDispatched(SendPushNotification::class);
    }
}
