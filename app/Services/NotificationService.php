<?php

namespace App\Services;

use App\Jobs\SendPushNotification;
use App\Models\Booking;
use App\Models\DeviceToken;
use App\Models\NotificationDelivery;
use App\Models\NotificationLog;
use App\Models\SaasSetting;
use App\Models\StaffMember;
use App\Models\Turf;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class NotificationService
{
    /*
    |--------------------------------------------------------------------------
    | Delivery primitives
    |--------------------------------------------------------------------------
    */

    /**
     * Queue a push to the given users. Always dispatched after the surrounding
     * DB transaction commits, so rolled-back work never notifies anyone.
     *
     * @param  array<int|null>  $userIds
     */
    public static function dispatchPush(array $userIds, string $event, string $title, string $body, array $data = []): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (empty($userIds)) {
            return;
        }

        try {
            SendPushNotification::dispatch($userIds, $event, $title, $body, $data);
        } catch (\Throwable $e) {
            Log::error("Failed to dispatch push notification [{$event}]: ".$e->getMessage());
        }
    }

    /**
     * Send push notification to a specific user across all registered devices.
     */
    public static function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        self::dispatchPush([$userId], (string) ($data['type'] ?? 'generic'), $title, $body, $data);
    }

    /**
     * Send push notification to the owner and staff of one turf only.
     */
    public static function sendToTurfManagers(int $turfId, string $title, string $body, array $data = []): void
    {
        $recipientIds = self::venueRecipientIds($turfId);

        self::dispatchPush($recipientIds, (string) ($data['type'] ?? 'venue_generic'), $title, $body, $data);
    }

    /**
     * Owner of the turf (location owner) plus staff members added by that owner.
     * SaaS admins are intentionally NOT included.
     *
     * @return array<int>
     */
    public static function venueRecipientIds(Turf|int|null $turf): array
    {
        if (is_int($turf)) {
            $turf = Turf::with('location')->find($turf);
        }

        $ownerId = $turf?->location?->user_id;
        if (! $ownerId) {
            return [];
        }

        $staffIds = StaffMember::where('turf_admin_id', $ownerId)->pluck('user_id')->all();

        return array_values(array_unique(array_map('intval', array_merge([$ownerId], $staffIds))));
    }

    /**
     * Record that an event was suppressed because its admin toggle is off.
     *
     * @param  array<int|null>  $userIds
     */
    private static function recordSkipped(array $userIds, string $event, string $title, string $body, array $data = []): void
    {
        foreach (array_unique(array_filter($userIds)) as $userId) {
            try {
                NotificationDelivery::create([
                    'user_id' => $userId,
                    'event' => $event,
                    'channel' => 'push',
                    'title' => $title,
                    'body' => $body,
                    'data' => $data,
                    'status' => NotificationDelivery::STATUS_SKIPPED_DISABLED,
                ]);
            } catch (\Throwable $e) {
                // Logging must never break the calling flow.
            }
        }
    }

    /**
     * Human booking number shown to users (e.g. TB-202611-00001).
     */
    private static function bookingRef(Booking $booking): string
    {
        return $booking->booking_number
            ?: ($booking->getAttribute('booking_reference') ?: '#'.$booking->id);
    }

    private static function setting(string $column, bool $default = true): bool
    {
        $value = SaasSetting::cached()?->{$column};

        return $value === null ? $default : (bool) $value;
    }

    /*
    |--------------------------------------------------------------------------
    | Booking lifecycle
    |--------------------------------------------------------------------------
    */

    /**
     * Notify customer and turf venue (owner + staff) on new booking creation.
     */
    public static function notifyBookingCreated(Booking $booking): void
    {
        $booking->loadMissing(['turf.location', 'bookingDates']);
        $turfName = $booking->turf?->name ?? 'Turf';
        $ref = self::bookingRef($booking);

        $dateCount = $booking->bookingDates->count();
        $dateStr = $dateCount > 1 ? "$dateCount dates" : ($booking->bookingDates->first()?->booking_date ?? '');
        if ($dateStr instanceof \DateTimeInterface) {
            $dateStr = $dateStr->format('d M Y');
        }

        $customerTitle = 'Booking Confirmed! 🎉';
        $customerBody = "Your booking ($ref) at $turfName for $dateStr is confirmed.";
        $customerData = ['type' => 'booking_created', 'booking_id' => (string) $booking->id];

        $venueTitle = 'New Booking Received! 🏟️';
        $venueBody = "New booking ($ref) created for $turfName ($dateStr).";
        $venueData = ['type' => 'manager_booking_created', 'booking_id' => (string) $booking->id];
        $venueIds = self::venueRecipientIds($booking->turf);

        if (! self::setting('notify_booking_created')) {
            self::recordSkipped([$booking->user_id], 'booking_created', $customerTitle, $customerBody, $customerData);
            self::recordSkipped($venueIds, 'manager_booking_created', $venueTitle, $venueBody, $venueData);

            return;
        }

        if ($booking->user_id) {
            self::dispatchPush([$booking->user_id], 'booking_created', $customerTitle, $customerBody, $customerData);
        }

        self::dispatchPush($venueIds, 'manager_booking_created', $venueTitle, $venueBody, $venueData);
    }

    /**
     * Notify customer on booking cancellation (full or partial).
     *
     * @param  int|null  $datesCancelled  Dates cancelled by THIS action (null = unknown/full)
     * @param  float  $refundAmount  Refund processed by THIS action
     */
    public static function notifyBookingCancelled(Booking $booking, ?int $datesCancelled = null, float $refundAmount = 0.0): void
    {
        if (! $booking->user_id) {
            return;
        }

        $booking->loadMissing(['turf']);
        $turfName = $booking->turf?->name ?? 'Turf';
        $ref = self::bookingRef($booking);

        $totalDates = $booking->exists ? $booking->bookingDates()->count() : 0;
        $isPartial = $datesCancelled !== null && $totalDates > 0 && $datesCancelled < $totalDates;

        if ($isPartial) {
            $title = 'Booking Date Cancelled ⚠️';
            $datesLabel = $datesCancelled === 1 ? '1 date' : "$datesCancelled dates";
            $body = "$datesLabel of your booking ($ref) at $turfName ".($datesCancelled === 1 ? 'was' : 'were').' cancelled. Your other dates are still confirmed.';
        } else {
            $title = 'Booking Cancelled ⚠️';
            $body = "Your booking ($ref) at $turfName has been cancelled.";
        }

        if ($refundAmount > 0) {
            $body .= ' Refund of ₹'.number_format($refundAmount, 2).' initiated.';
        }

        $data = ['type' => 'booking_cancelled', 'booking_id' => (string) $booking->id];

        if (! self::setting('notify_booking_cancelled')) {
            self::recordSkipped([$booking->user_id], 'booking_cancelled', $title, $body, $data);

            return;
        }

        self::dispatchPush([$booking->user_id], 'booking_cancelled', $title, $body, $data);
    }

    /**
     * Notify customer and turf venue (owner + staff) on booking rescheduling.
     */
    public static function notifyBookingRescheduled(Booking $booking, string $fromDate, string $toDate): void
    {
        $booking->loadMissing(['turf.location']);
        $turfName = $booking->turf?->name ?? 'Turf';
        $ref = self::bookingRef($booking);

        $customerTitle = 'Booking Rescheduled 🔄';
        $customerBody = "Your booking ($ref) at $turfName has been rescheduled from $fromDate to $toDate.";
        $customerData = ['type' => 'booking_rescheduled', 'booking_id' => (string) $booking->id];

        $venueBody = "Booking ($ref) for $turfName rescheduled from $fromDate to $toDate.";
        $venueData = ['type' => 'manager_booking_rescheduled', 'booking_id' => (string) $booking->id];
        $venueIds = self::venueRecipientIds($booking->turf);

        if (! self::setting('notify_booking_rescheduled')) {
            self::recordSkipped([$booking->user_id], 'booking_rescheduled', $customerTitle, $customerBody, $customerData);
            self::recordSkipped($venueIds, 'manager_booking_rescheduled', $customerTitle, $venueBody, $venueData);

            return;
        }

        if ($booking->user_id) {
            self::dispatchPush([$booking->user_id], 'booking_rescheduled', $customerTitle, $customerBody, $customerData);
        }

        self::dispatchPush($venueIds, 'manager_booking_rescheduled', $customerTitle, $venueBody, $venueData);
    }

    /**
     * Notify customer on payment received.
     */
    public static function notifyPaymentRecorded(Booking $booking, float $amountPaid): void
    {
        if (! $booking->user_id || $amountPaid <= 0) {
            return;
        }

        $ref = self::bookingRef($booking);
        $title = 'Payment Received 💳';
        $body = 'Payment of ₹'.number_format($amountPaid, 2)." received for booking $ref.";
        $data = ['type' => 'payment_received', 'booking_id' => (string) $booking->id];

        if (! self::setting('notify_payment_received')) {
            self::recordSkipped([$booking->user_id], 'payment_received', $title, $body, $data);

            return;
        }

        self::dispatchPush([$booking->user_id], 'payment_received', $title, $body, $data);
    }

    /*
    |--------------------------------------------------------------------------
    | Manual broadcast (SaaS Admin composer)
    |--------------------------------------------------------------------------
    */

    /**
     * Send custom broadcast notification to selected audience and log the event.
     * Explicit admin action: sent immediately and not affected by event toggles.
     */
    public static function sendCustomNotification(string $title, string $body, string $audience, ?int $targetUserId, User $sentBy): int
    {
        $query = User::query();

        if ($audience === 'customers') {
            $query->whereHas('roles', fn ($q) => $q->where('name', 'customer'));
        } elseif ($audience === 'turf_admins') {
            $query->whereHas('roles', fn ($q) => $q->whereIn('name', ['turf-admin', 'manager', 'admin']));
        } elseif ($audience === 'specific_user') {
            $query->where('id', $targetUserId);
        }

        // Only target users who have at least one registered device token
        $userIds = $query->whereHas('deviceTokens')->pluck('id')->toArray();
        $tokens = DeviceToken::whereIn('user_id', $userIds)->pluck('device_token')->unique()->values()->toArray();

        $recipientCount = count($tokens);

        if ($recipientCount > 0) {
            self::sendFcmNotification($tokens, $title, $body, [
                'type' => 'custom_notification',
            ]);
        }

        NotificationLog::create([
            'sent_by_user_id' => $sentBy->id,
            'title' => $title,
            'body' => $body,
            'audience_type' => $audience,
            'target_user_id' => $audience === 'specific_user' ? $targetUserId : null,
            'recipient_count' => $recipientCount,
            'created_at' => now(),
        ]);

        return $recipientCount;
    }

    /*
    |--------------------------------------------------------------------------
    | FCM transport
    |--------------------------------------------------------------------------
    */

    /**
     * Dispatch FCM HTTP v1 payload to device tokens via Kreait Messaging.
     *
     * @return array{status: string, error?: string|null}
     */
    public static function sendFcmNotification(array $tokens, string $title, string $body, array $data = [], ?string $imageUrl = 'https://www.leenaitsolutions.in/turf-logo.png'): array
    {
        $tokens = array_values(array_filter(array_unique($tokens)));
        if (empty($tokens)) {
            return ['status' => NotificationDelivery::STATUS_NO_DEVICE];
        }

        /** @var Messaging|null $messaging */
        $messaging = app()->make(Messaging::class);

        if (! $messaging) {
            Log::info("FCM Notification Log (Service Account not configured): Title='{$title}', Body='{$body}', Tokens=".count($tokens));

            return ['status' => NotificationDelivery::STATUS_NOT_CONFIGURED];
        }

        try {
            $notification = Notification::create($title, $body, $imageUrl);

            $androidConfig = AndroidConfig::fromArray([
                'notification' => [
                    'icon' => 'ic_notification',
                    'color' => '#10B981',
                    'notification_priority' => 'PRIORITY_HIGH',
                    'default_sound' => true,
                ],
            ]);

            // FCM data payload values must be strings.
            $stringData = array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $data);

            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withAndroidConfig($androidConfig)
                ->withData(array_merge($stringData, [
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'title' => $title,
                    'body' => $body,
                ]));

            $report = $messaging->sendMulticast($message, $tokens);

            if ($report->hasFailures()) {
                $unregisteredTokens = array_unique(array_merge($report->unknownTokens(), $report->invalidTokens()));

                if (! empty($unregisteredTokens)) {
                    DeviceToken::whereIn('device_token', $unregisteredTokens)->delete();
                    Log::info('Pruned unregistered FCM device tokens: '.count($unregisteredTokens));
                }

                if ($report->successes()->count() === 0) {
                    return ['status' => NotificationDelivery::STATUS_FAILED, 'error' => 'All device tokens failed'];
                }
            }

            return ['status' => NotificationDelivery::STATUS_SENT];
        } catch (\Throwable $e) {
            Log::error('FCM Push Notification Error: '.$e->getMessage());

            return ['status' => NotificationDelivery::STATUS_FAILED, 'error' => $e->getMessage()];
        }
    }
}
