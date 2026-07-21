<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DeviceToken;
use App\Models\SaasSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
     * Dispatch FCM HTTP v1 / Legacy payload to device tokens.
     */
    private static function sendFcmNotification(array $tokens, string $title, string $body, array $data = []): void
    {
        $setting = SaasSetting::first();
        $fcmServerKey = config('services.fcm.key') ?: env('FCM_SERVER_KEY');

        if (!$fcmServerKey) {
            Log::info("FCM Notification Log (Key not set): Title='$title', Body='$body', Tokens=" . count($tokens));
            return;
        }

        try {
            foreach ($tokens as $token) {
                Http::withHeaders([
                    'Authorization' => 'key=' . $fcmServerKey,
                    'Content-Type' => 'application/json',
                ])->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'sound' => 'default',
                    ],
                    'data' => array_merge($data, [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'title' => $title,
                        'body' => $body,
                    ]),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('FCM Push Notification Error: ' . $e->getMessage());
        }
    }
}
