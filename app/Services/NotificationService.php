<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DeviceToken;
use App\Models\NotificationLog;
use App\Models\SaasSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class NotificationService
{
    /**
     * Send push notification to a specific user across all registered devices.
     */
    public static function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        $tokens = DeviceToken::where('user_id', $userId)->pluck('device_token')->toArray();
        if (empty($tokens)) {
            return;
        }

        self::sendFcmNotification($tokens, $title, $body, $data);
    }

    /**
     * Send push notification to all staff/managers assigned to a turf.
     */
    public static function sendToTurfManagers(int $turfId, string $title, string $body, array $data = []): void
    {
        $managerUserIds = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['saas-admin', 'turf-admin', 'manager', 'admin']);
        })->pluck('id')->toArray();

        if (empty($managerUserIds)) {
            return;
        }

        $tokens = DeviceToken::whereIn('user_id', $managerUserIds)->pluck('device_token')->toArray();
        if (!empty($tokens)) {
            self::sendFcmNotification($tokens, $title, $body, $data);
        }
    }

    /**
     * Notify customer and turf managers on new booking creation.
     */
    public static function notifyBookingCreated(Booking $booking): void
    {
        if (!(SaasSetting::first()?->notify_booking_created ?? true)) {
            return;
        }

        $booking->load(['turf', 'user', 'bookingDates']);
        $turfName = $booking->turf?->name ?? 'Turf';
        $ref = $booking->booking_reference ?? ('#' . $booking->id);

        $dateCount = $booking->bookingDates->count();
        $dateStr = $dateCount > 1 ? "$dateCount dates" : ($booking->bookingDates->first()?->booking_date ?? '');

        // Customer Notification
        if ($booking->user_id) {
            self::sendToUser(
                $booking->user_id,
                "Booking Confirmed! 🎉",
                "Your booking ($ref) at $turfName for $dateStr is confirmed.",
                ['type' => 'booking_created', 'booking_id' => (string)$booking->id]
            );
        }

        // Manager Notification
        self::sendToTurfManagers(
            $booking->turf_id,
            "New Booking Received! 🏟️",
            "New booking ($ref) created for $turfName ($dateStr).",
            ['type' => 'manager_booking_created', 'booking_id' => (string)$booking->id]
        );
    }

    /**
     * Notify customer and turf managers on booking cancellation.
     */
    public static function notifyBookingCancelled(Booking $booking): void
    {
        if (!(SaasSetting::first()?->notify_booking_cancelled ?? true)) {
            return;
        }

        $booking->load(['turf']);
        $turfName = $booking->turf?->name ?? 'Turf';
        $ref = $booking->booking_reference ?? ('#' . $booking->id);

        if ($booking->user_id) {
            self::sendToUser(
                $booking->user_id,
                "Booking Cancelled ⚠️",
                "Your booking ($ref) at $turfName has been cancelled.",
                ['type' => 'booking_cancelled', 'booking_id' => (string)$booking->id]
            );
        }
    }

    /**
     * Notify customer on payment received.
     */
    public static function notifyPaymentRecorded(Booking $booking, float $amountPaid): void
    {
        if (!(SaasSetting::first()?->notify_payment_received ?? true)) {
            return;
        }

        if ($booking->user_id && $amountPaid > 0) {
            $ref = $booking->booking_reference ?? ('#' . $booking->id);
            self::sendToUser(
                $booking->user_id,
                "Payment Received 💳",
                "Payment of ₹" . number_format($amountPaid, 2) . " received for booking $ref.",
                ['type' => 'payment_received', 'booking_id' => (string)$booking->id]
            );
        }
    }

    /**
     * Send custom broadcast notification to selected audience and log the event.
     */
    public static function sendCustomNotification(string $title, string $body, string $audience, ?int $targetUserId, User $sentBy): int
    {
        $query = User::query();

        if ($audience === 'customers') {
            $query->whereHas('roles', fn($q) => $q->where('name', 'customer'));
        } elseif ($audience === 'turf_admins') {
            $query->whereHas('roles', fn($q) => $q->whereIn('name', ['turf-admin', 'manager', 'admin']));
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

    /**
     * Dispatch FCM HTTP v1 payload to device tokens via Kreait Messaging.
     */
    public static function sendFcmNotification(array $tokens, string $title, string $body, array $data = [], ?string $imageUrl = 'https://www.leenaitsolutions.in/turf-logo.png'): void
    {
        $tokens = array_values(array_filter(array_unique($tokens)));
        if (empty($tokens)) {
            return;
        }

        /** @var Messaging|null $messaging */
        $messaging = app()->make(Messaging::class);

        if (!$messaging) {
            Log::info("FCM Notification Log (Service Account not configured): Title='{$title}', Body='{$body}', Tokens=" . count($tokens));
            return;
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

            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withAndroidConfig($androidConfig)
                ->withData(array_merge($data, [
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'title' => $title,
                    'body' => $body,
                ]));

            $report = $messaging->sendMulticast($message, $tokens);

            if ($report->hasFailures()) {
                $unknownTokens = $report->unknownTokens();
                $invalidTokens = $report->invalidTokens();
                $unregisteredTokens = array_unique(array_merge($unknownTokens, $invalidTokens));

                if (!empty($unregisteredTokens)) {
                    DeviceToken::whereIn('device_token', $unregisteredTokens)->delete();
                    Log::info('Pruned unregistered FCM device tokens: ' . count($unregisteredTokens));
                }
            }
        } catch (\Throwable $e) {
            Log::error('FCM Push Notification Error: ' . $e->getMessage());
        }
    }
}
