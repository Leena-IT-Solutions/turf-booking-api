<?php

namespace App\Services;

use App\Jobs\SendPushNotification;
use App\Models\Booking;
use App\Models\CommissionInvoice;
use App\Models\Coupon;
use App\Models\DeviceToken;
use App\Models\NotificationDelivery;
use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Models\Review;
use App\Models\StaffMember;
use App\Models\SupportMessage;
use App\Models\Turf;
use App\Models\TurfPayout;
use App\Models\User;
use App\Notifications\CommissionInvoiceGeneratedNotification;
use App\Notifications\TurfSubmittedForVerification;
use App\Notifications\TurfVerificationStatusNotification;
use App\Support\NotificationEvents;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as LaravelNotification;
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
     * Check whether an event + channel ('push' | 'email') is enabled in NotificationSetting.
     */
    public static function isEnabled(string $eventKey, string $channel = 'push'): bool
    {
        return NotificationSetting::isEnabled($eventKey, $channel);
    }

    /**
     * Queue a push for a registered NotificationEvents key, respecting:
     * 1. Admin toggle (NotificationSetting::isEnabled($eventKey, 'push'))
     * 2. User-level promotional opt-out (promo_notifications_enabled) for promotional events
     * 3. Per-event Android notification channel
     *
     * @param  array<int|null>  $userIds
     */
    public static function dispatchEventPush(string $eventKey, array $userIds, string $title, string $body, array $data = []): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (empty($userIds)) {
            return;
        }

        $definition = NotificationEvents::get($eventKey);
        if ($definition && ! empty($definition['android_channel_id']) && ! isset($data['android_channel_id'])) {
            $data['android_channel_id'] = $definition['android_channel_id'];
        }

        $legacyEvent = (string) ($data['type'] ?? $eventKey);

        if (! self::isEnabled($eventKey, 'push')) {
            self::recordSkipped($userIds, $legacyEvent, $title, $body, $data, NotificationDelivery::STATUS_SKIPPED_DISABLED);

            return;
        }

        // Respect user promotional opt-out for promotional events
        if (($definition['kind'] ?? null) === NotificationEvents::KIND_PROMOTIONAL) {
            $optedOutIds = User::whereIn('id', $userIds)
                ->where('promo_notifications_enabled', false)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (! empty($optedOutIds)) {
                self::recordSkipped($optedOutIds, $legacyEvent, $title, $body, $data, NotificationDelivery::STATUS_SKIPPED_OPT_OUT);
                $userIds = array_values(array_diff($userIds, $optedOutIds));
            }

            if (empty($userIds)) {
                return;
            }
        }

        self::dispatchPush($userIds, $legacyEvent, $title, $body, $data);
    }

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
     * Record that an event was suppressed (admin toggle disabled or user opted out).
     *
     * @param  array<int|null>  $userIds
     */
    public static function recordSkipped(
        array $userIds,
        string $event,
        string $title,
        string $body,
        array $data = [],
        string $status = NotificationDelivery::STATUS_SKIPPED_DISABLED,
        string $channel = 'push'
    ): void {
        foreach (array_unique(array_filter($userIds)) as $userId) {
            try {
                NotificationDelivery::create([
                    'user_id' => $userId,
                    'event' => $event,
                    'channel' => $channel,
                    'title' => $title,
                    'body' => $body,
                    'data' => $data,
                    'status' => $status,
                ]);
            } catch (\Throwable $e) {
                // Logging must never break the calling flow.
            }
        }
    }

    /**
     * Record an email notification delivery row.
     *
     * @param  array<int|null>  $userIds
     */
    public static function recordEmailDelivery(array $userIds, string $event, string $title, string $body, string $status = NotificationDelivery::STATUS_SENT, ?string $error = null): void
    {
        foreach (array_unique(array_filter($userIds)) as $userId) {
            try {
                NotificationDelivery::create([
                    'user_id' => $userId,
                    'event' => $event,
                    'channel' => 'email',
                    'title' => $title,
                    'body' => $body,
                    'status' => $status,
                    'error' => $error,
                    'sent_at' => $status === NotificationDelivery::STATUS_SENT ? now() : null,
                ]);
            } catch (\Throwable $e) {
                // Logging must never break the calling flow.
            }
        }
    }

    /**
     * Human booking number shown to users (e.g. TB-202611-00001).
     */
    public static function bookingRef(Booking $booking): string
    {
        return $booking->booking_number
            ?: ($booking->getAttribute('booking_reference') ?: '#'.$booking->id);
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

        if ($booking->user_id) {
            self::dispatchEventPush(
                'customer.booking_created',
                [$booking->user_id],
                'Booking Confirmed! 🎉',
                "Your booking ($ref) at $turfName for $dateStr is confirmed.",
                ['type' => 'booking_created', 'booking_id' => (string) $booking->id]
            );
        }

        $venueIds = self::venueRecipientIds($booking->turf);
        if (! empty($venueIds)) {
            self::dispatchEventPush(
                'venue.booking_created',
                $venueIds,
                'New Booking Received! 🏟️',
                "New booking ($ref) created for $turfName ($dateStr).",
                ['type' => 'manager_booking_created', 'booking_id' => (string) $booking->id, 'turf_id' => (string) $booking->turf_id]
            );
        }
    }

    /**
     * Notify customer and venue on booking cancellation (full or partial).
     *
     * @param  int|null  $datesCancelled  Dates cancelled by THIS action (null = unknown/full)
     * @param  float  $refundAmount  Refund processed by THIS action
     */
    public static function notifyBookingCancelled(Booking $booking, ?int $datesCancelled = null, float $refundAmount = 0.0): void
    {
        $booking->loadMissing(['turf.location']);
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

        if ($booking->user_id) {
            self::dispatchEventPush(
                'customer.booking_cancelled',
                [$booking->user_id],
                $title,
                $body,
                ['type' => 'booking_cancelled', 'booking_id' => (string) $booking->id]
            );

            if ($refundAmount > 0) {
                self::notifyRefundProcessed($booking, $refundAmount);
            }
        }

        $venueIds = self::venueRecipientIds($booking->turf);
        if (! empty($venueIds)) {
            $venueBody = $isPartial
                ? "1 or more dates of booking ($ref) at $turfName were cancelled. Slots are now open."
                : "Booking ($ref) at $turfName was cancelled. Slots are now open.";

            self::dispatchEventPush(
                'venue.booking_cancelled',
                $venueIds,
                'Booking Cancelled (Slots Reopened) ⚠️',
                $venueBody,
                ['type' => 'manager_booking_cancelled', 'booking_id' => (string) $booking->id, 'turf_id' => (string) $booking->turf_id]
            );
        }
    }

    /**
     * Notify customer when a cancellation refund (> ₹0) is initiated or processed.
     */
    public static function notifyRefundProcessed(Booking $booking, float $refundAmount, string $channelLabel = 'original payment method'): void
    {
        if (! $booking->user_id || $refundAmount <= 0) {
            return;
        }

        $booking->loadMissing(['turf']);
        $turfName = $booking->turf?->name ?? 'Turf';
        $ref = self::bookingRef($booking);

        self::dispatchEventPush(
            'customer.refund_processed',
            [$booking->user_id],
            'Refund Initiated 💰',
            'A refund of ₹'.number_format($refundAmount, 2)." for booking ($ref) at $turfName has been initiated ($channelLabel).",
            ['type' => 'refund_processed', 'booking_id' => (string) $booking->id]
        );
    }

    /**
     * Notify customer and turf venue (owner + staff) on booking rescheduling.
     */
    public static function notifyBookingRescheduled(Booking $booking, string $fromDate, string $toDate): void
    {
        $booking->loadMissing(['turf.location']);
        $turfName = $booking->turf?->name ?? 'Turf';
        $ref = self::bookingRef($booking);

        if ($booking->user_id) {
            self::dispatchEventPush(
                'customer.booking_rescheduled',
                [$booking->user_id],
                'Booking Rescheduled 🔄',
                "Your booking ($ref) at $turfName has been rescheduled from $fromDate to $toDate.",
                ['type' => 'booking_rescheduled', 'booking_id' => (string) $booking->id]
            );
        }

        $venueIds = self::venueRecipientIds($booking->turf);
        if (! empty($venueIds)) {
            self::dispatchEventPush(
                'venue.booking_rescheduled',
                $venueIds,
                'Booking Rescheduled 🔄',
                "Booking ($ref) for $turfName rescheduled from $fromDate to $toDate.",
                ['type' => 'manager_booking_rescheduled', 'booking_id' => (string) $booking->id, 'turf_id' => (string) $booking->turf_id]
            );
        }
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

        self::dispatchEventPush(
            'customer.payment_received',
            [$booking->user_id],
            'Payment Received 💳',
            'Payment of ₹'.number_format($amountPaid, 2)." received for booking $ref.",
            ['type' => 'payment_received', 'booking_id' => (string) $booking->id]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Wrapped existing Payout, Trial & Onboarding triggers
    |--------------------------------------------------------------------------
    */

    /**
     * Notify SaaS admins of a manual payout request (wrapped with admin.payout_requested toggle).
     */
    public static function notifyManualPayoutRequested(TurfPayout $payout, User $owner): void
    {
        $saasAdminIds = User::whereHas('roles', fn ($q) => $q->where('name', 'saas-admin'))->pluck('id')->all();
        if (empty($saasAdminIds)) {
            return;
        }

        $netFormatted = number_format((float) $payout->net_amount, 2);

        self::dispatchEventPush(
            'admin.payout_requested',
            $saasAdminIds,
            'Payout request',
            "{$owner->name} requested ₹{$netFormatted}. Transfer it and mark it paid in Payout Manager.",
            ['type' => 'manual_payout_requested', 'payout_id' => (string) $payout->id]
        );
    }

    /**
     * Notify turf owner that their payout was transferred (wrapped with venue.payout_paid toggle).
     */
    public static function notifyPayoutPaid(TurfPayout $payout, ?string $utr = null): void
    {
        if (! $payout->user_id) {
            return;
        }

        $netFormatted = number_format((float) $payout->net_amount, 2);
        $utrText = $utr ?: ($payout->utr ?: 'N/A');

        self::dispatchEventPush(
            'venue.payout_paid',
            [(int) $payout->user_id],
            'Payout sent',
            "₹{$netFormatted} was transferred. UTR {$utrText}.",
            ['type' => 'payout_paid', 'payout_id' => (string) $payout->id]
        );
    }

    /**
     * Notify turf owner that their payout was rejected and reversed (wrapped with venue.payout_rejected toggle).
     */
    public static function notifyPayoutRejected(TurfPayout $payout, string $reason): void
    {
        if (! $payout->user_id) {
            return;
        }

        $reqFormatted = number_format((float) $payout->requested_amount, 2);

        self::dispatchEventPush(
            'venue.payout_rejected',
            [(int) $payout->user_id],
            'Payout rejected',
            "₹{$reqFormatted} was returned to your wallet. Reason: {$reason}",
            ['type' => 'payout_rejected', 'payout_id' => (string) $payout->id]
        );
    }

    /**
     * Notify turf owner of upcoming trial expiry (wrapped with venue.trial_reminder toggle).
     */
    public static function notifyTrialReminder(User $owner, string $title, string $body, int $daysLeft): bool
    {
        $enabled = self::isEnabled('venue.trial_reminder', 'push');

        self::dispatchEventPush(
            'venue.trial_reminder',
            [$owner->id],
            $title,
            $body,
            ['type' => 'trial_expiring', 'days_left' => (string) $daysLeft]
        );

        return $enabled;
    }

    /**
     * Notify SaaS Admins & Marketing Executives when a turf is submitted for verification
     * (Push + Email controlled independently by admin.turf_submitted).
     */
    public static function notifyTurfSubmittedForVerification(Turf $turf, User $submittedBy): void
    {
        $verifiers = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['saas-admin', 'marketing']);
        })->get();

        if ($verifiers->isEmpty()) {
            return;
        }

        $verifierIds = $verifiers->pluck('id')->all();
        $turf->loadMissing(['location.user', 'onboardedBy']);
        $title = "New Turf Submitted for Verification: {$turf->name}";
        $body = "{$turf->name} ({$turf->location?->name}) has been submitted by {$submittedBy->name} and is awaiting verification.";

        // 1. Push notification channel
        self::dispatchEventPush(
            'admin.turf_submitted',
            $verifierIds,
            'New Turf Submitted for Verification 🏟️',
            $body,
            ['type' => 'admin_turf_submitted', 'turf_id' => (string) $turf->id]
        );

        // 2. Email notification channel
        if (self::isEnabled('admin.turf_submitted', 'email')) {
            try {
                LaravelNotification::send($verifiers, new TurfSubmittedForVerification($turf, $submittedBy));
                self::recordEmailDelivery($verifierIds, 'admin.turf_submitted', $title, $body, NotificationDelivery::STATUS_SENT);
            } catch (\Throwable $e) {
                Log::warning('Failed to send TurfSubmittedForVerification email: '.$e->getMessage());
                self::recordEmailDelivery($verifierIds, 'admin.turf_submitted', $title, $body, NotificationDelivery::STATUS_FAILED, $e->getMessage());
            }
        } else {
            self::recordSkipped($verifierIds, 'admin.turf_submitted', $title, $body, ['turf_id' => (string) $turf->id], NotificationDelivery::STATUS_SKIPPED_DISABLED, 'email');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Phase 2: Real-Time Event Triggers (Support, Verification, Reviews,
    | Invoices, Payout Failures, Targeted Offers)
    |--------------------------------------------------------------------------
    */

    /**
     * Notify SaaS Admins when a customer or turf owner sends a Support Chat message.
     */
    public static function notifySupportMessageToAdmins(SupportMessage $message): void
    {
        $adminIds = User::whereHas('roles', fn ($q) => $q->where('name', 'saas-admin'))->pluck('id')->all();
        if (empty($adminIds)) {
            return;
        }

        $senderName = User::find($message->sender_id)?->name ?? 'User';
        $preview = mb_strimwidth((string) $message->message, 0, 120, '…');

        self::dispatchEventPush(
            'admin.support_message',
            $adminIds,
            'New Support Message 💬',
            "{$senderName}: {$preview}",
            ['type' => 'admin_support_message', 'user_id' => (string) $message->user_id]
        );
    }

    /**
     * Notify the customer/owner when a SaaS Admin replies in Support Chat.
     */
    public static function notifySupportReplyToUser(SupportMessage $message): void
    {
        if (! $message->user_id) {
            return;
        }

        $preview = mb_strimwidth((string) $message->message, 0, 120, '…');

        self::dispatchEventPush(
            'customer.support_reply',
            [(int) $message->user_id],
            'New Support Reply 💬',
            $preview,
            ['type' => 'support_reply']
        );
    }

    /**
     * Notify the turf owner when their turf verification status changes (Approved / Rejected / Review / Hold).
     * Supports both Push and Email independently via venue.turf_verification.
     */
    public static function notifyTurfVerificationStatus(Turf $turf, string $status): void
    {
        $turf->loadMissing(['location.user']);
        $owner = $turf->location?->user;
        if (! $owner) {
            return;
        }

        $isApproved = $status === 'Approved';
        $title = $isApproved ? 'Turf Verified & Live! 🎉' : "Turf Verification Update ({$status})";
        $body = $isApproved
            ? "Congratulations! {$turf->name} has been approved and is now live for bookings."
            : "The verification status of {$turf->name} has been updated to {$status}. Please check your dashboard.";

        self::dispatchEventPush(
            'venue.turf_verification',
            [$owner->id],
            $title,
            $body,
            ['type' => 'turf_verification', 'turf_id' => (string) $turf->id, 'status' => $status]
        );

        if (self::isEnabled('venue.turf_verification', 'email')) {
            try {
                $owner->notify(new TurfVerificationStatusNotification($turf, $status));
                self::recordEmailDelivery([$owner->id], 'venue.turf_verification', $title, $body, NotificationDelivery::STATUS_SENT);
            } catch (\Throwable $e) {
                Log::warning('Failed to send TurfVerificationStatusNotification email: '.$e->getMessage());
                self::recordEmailDelivery([$owner->id], 'venue.turf_verification', $title, $body, NotificationDelivery::STATUS_FAILED, $e->getMessage());
            }
        } else {
            self::recordSkipped([$owner->id], 'venue.turf_verification', $title, $body, ['turf_id' => (string) $turf->id], NotificationDelivery::STATUS_SKIPPED_DISABLED, 'email');
        }
    }

    /**
     * Notify the turf owner when a customer leaves a review on their turf.
     */
    public static function notifyNewReview(Review $review): void
    {
        $review->loadMissing(['turf.location', 'user']);
        $ownerId = $review->turf?->location?->user_id;
        if (! $ownerId || (int) $ownerId === (int) $review->user_id) {
            return;
        }

        $turfName = $review->turf?->name ?? 'your turf';
        $stars = (int) $review->rating;
        $reviewerName = $review->user?->name ?? 'A player';
        $comment = trim((string) ($review->comment ?? ''));
        $body = $comment !== ''
            ? "{$reviewerName} rated {$turfName} {$stars}★: \"".mb_strimwidth($comment, 0, 100, '…').'"'
            : "{$reviewerName} rated {$turfName} {$stars}★.";

        self::dispatchEventPush(
            'venue.new_review',
            [(int) $ownerId],
            "New {$stars}★ Review for {$turfName} ⭐",
            $body,
            ['type' => 'new_review', 'turf_id' => (string) $review->turf_id, 'review_id' => (string) $review->id]
        );
    }

    /**
     * Notify the turf owner when a monthly Commission Tax Invoice is generated (Push + Email).
     */
    public static function notifyCommissionInvoiceGenerated(CommissionInvoice $invoice): void
    {
        $invoice->loadMissing(['turf.location.user']);
        $owner = $invoice->owner ?? $invoice->turf?->location?->user;
        if (! $owner) {
            return;
        }

        $turfName = $invoice->recipient_snapshot['turf_name'] ?? ($invoice->turf?->name ?? 'Your Turf');
        $totalFormatted = number_format((float) $invoice->total_amount, 2);
        $title = 'Commission Tax Invoice Generated 🧾';
        $body = "Monthly commission tax invoice {$invoice->invoice_number} (₹{$totalFormatted}) for {$turfName} is ready.";

        self::dispatchEventPush(
            'venue.commission_invoice',
            [$owner->id],
            $title,
            $body,
            ['type' => 'commission_invoice', 'invoice_id' => (string) $invoice->id]
        );

        if (self::isEnabled('venue.commission_invoice', 'email')) {
            try {
                $owner->notify(new CommissionInvoiceGeneratedNotification($invoice));
                self::recordEmailDelivery([$owner->id], 'venue.commission_invoice', $title, $body, NotificationDelivery::STATUS_SENT);
            } catch (\Throwable $e) {
                Log::warning('Failed to send CommissionInvoiceGeneratedNotification email: '.$e->getMessage());
                self::recordEmailDelivery([$owner->id], 'venue.commission_invoice', $title, $body, NotificationDelivery::STATUS_FAILED, $e->getMessage());
            }
        } else {
            self::recordSkipped([$owner->id], 'venue.commission_invoice', $title, $body, ['invoice_id' => (string) $invoice->id], NotificationDelivery::STATUS_SKIPPED_DISABLED, 'email');
        }
    }

    /**
     * Notify SaaS Admins when an automated RazorpayX payout fails or is reversed.
     */
    public static function notifyPayoutFailedToAdmins(TurfPayout $payout, string $reason): void
    {
        $adminIds = User::whereHas('roles', fn ($q) => $q->where('name', 'saas-admin'))->pluck('id')->all();
        if (empty($adminIds)) {
            return;
        }

        $reqFormatted = number_format((float) $payout->requested_amount, 2);

        self::dispatchEventPush(
            'admin.payout_failed',
            $adminIds,
            'Payout Failed ⚠️',
            "RazorpayX payout #{$payout->id} (₹{$reqFormatted}) failed and was reversed. Reason: {$reason}",
            ['type' => 'admin_payout_failed', 'payout_id' => (string) $payout->id]
        );
    }

    /**
     * Notify targeted players when a coupon is activated on a turf.
     * Safeguards:
     * - Only active coupons within valid date window
     * - Quiet hours check (10:00 PM – 08:00 AM IST): suppressed during quiet hours
     * - Only players who booked that turf in the last 180 days
     * - Rate-limited to max 1 offer push per user per 24 hours
     */
    public static function notifyNewOffer(Coupon $coupon, ?Carbon $nowIst = null): int
    {
        if (! $coupon->is_active) {
            return 0;
        }

        $nowIst = ($nowIst ?? Carbon::now('Asia/Kolkata'))->copy()->setTimezone('Asia/Kolkata');
        $todayStr = $nowIst->toDateString();

        if ($coupon->starts_at && Carbon::parse($coupon->starts_at)->toDateString() > $todayStr) {
            return 0;
        }
        if ($coupon->expires_at && Carbon::parse($coupon->expires_at)->toDateString() < $todayStr) {
            return 0;
        }

        // Quiet hours safeguard: no promotional offer push between 10 PM and 8 AM IST
        $hour = (int) $nowIst->format('G');
        if ($hour >= 22 || $hour < 8) {
            return 0;
        }

        $coupon->loadMissing(['turf']);
        $turf = $coupon->turf;
        if (! $turf) {
            return 0;
        }

        // Target only customers who booked this turf in the last 180 days
        $cutoff = $nowIst->copy()->subDays(180);
        $candidateUserIds = Booking::where('turf_id', $turf->id)
            ->whereNotNull('user_id')
            ->where('status', '!=', 'Cancelled')
            ->where('created_at', '>=', $cutoff)
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($candidateUserIds)) {
            return 0;
        }

        // Rate limit: exclude users who already received a new_offer push in the last 24 hours
        $recentlyNotifiedUserIds = NotificationDelivery::whereIn('user_id', $candidateUserIds)
            ->where('event', 'new_offer')
            ->where('status', NotificationDelivery::STATUS_SENT)
            ->where('created_at', '>=', now()->subDay())
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $eligibleUserIds = array_values(array_diff($candidateUserIds, $recentlyNotifiedUserIds));
        if (empty($eligibleUserIds)) {
            return 0;
        }

        $discountLabel = $coupon->discount_type === 'percentage'
            ? rtrim(rtrim(number_format((float) $coupon->discount_value, 2), '0'), '.').'% off'
            : '₹'.number_format((float) $coupon->discount_value, 0).' off';

        $title = "New Offer at {$turf->name}! 🎁";
        $body = "Use coupon code {$coupon->code} to get {$discountLabel} on your next booking at {$turf->name}!";

        self::dispatchEventPush(
            'customer.new_offer',
            $eligibleUserIds,
            $title,
            $body,
            ['type' => 'new_offer', 'turf_id' => (string) $turf->id, 'coupon_code' => (string) $coupon->code]
        );

        return count($eligibleUserIds);
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
    public static function sendFcmNotification(
        array $tokens,
        string $title,
        string $body,
        array $data = [],
        ?string $imageUrl = 'https://www.leenaitsolutions.in/turf-logo.png',
        ?string $channelId = null,
    ): array {
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

            $androidNotification = [
                'icon' => 'ic_notification',
                'color' => '#10B981',
                'notification_priority' => 'PRIORITY_HIGH',
                'default_sound' => true,
            ];
            if (! empty($channelId)) {
                $androidNotification['channel_id'] = $channelId;
            }

            $androidConfig = AndroidConfig::fromArray([
                'notification' => $androidNotification,
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
