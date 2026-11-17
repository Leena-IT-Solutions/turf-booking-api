<?php

namespace App\Support;

class NotificationEvents
{
    public const CATEGORY_CUSTOMER = 'customer';

    public const CATEGORY_VENUE = 'venue';

    public const CATEGORY_ADMIN = 'admin';

    public const KIND_TRANSACTIONAL = 'transactional';

    public const KIND_PROMOTIONAL = 'promotional';

    /**
     * Allowed lead-time options (in minutes) for customer.match_reminder.
     *
     * @return array<int, string>
     */
    public static function reminderLeadTimeOptions(): array
    {
        return [
            15 => '15 minutes before',
            30 => '30 minutes before',
            45 => '45 minutes before',
            60 => '1 hour before',
            90 => '1.5 hours before',
            120 => '2 hours before',
            180 => '3 hours before',
            360 => '6 hours before',
            720 => '12 hours before',
            1440 => '24 hours (1 day) before',
        ];
    }

    /**
     * Master registry of all configurable notification events.
     *
     * @return array<string, array{
     *     key: string,
     *     category: string,
     *     label: string,
     *     description: string,
     *     audience_label: string,
     *     kind: string,
     *     channels: array<int, string>,
     *     android_channel_id: string,
     *     legacy_saas_column?: string,
     *     default_config?: array<string, mixed>,
     *     sample_title: string,
     *     sample_body: string,
     *     sample_data: array<string, string>
     * }>
     */
    public static function all(): array
    {
        return [
            // =================================================================
            // A. CUSTOMER (PLAYER) NOTIFICATIONS
            // =================================================================
            'customer.booking_created' => [
                'key' => 'customer.booking_created',
                'category' => self::CATEGORY_CUSTOMER,
                'label' => 'Booking Confirmed',
                'description' => 'Sent to the player when a new turf slot booking is confirmed.',
                'audience_label' => 'Customer',
                'kind' => self::KIND_TRANSACTIONAL,
                'channels' => ['push'],
                'android_channel_id' => 'bookings',
                'legacy_saas_column' => 'notify_booking_created',
                'sample_title' => 'Booking Confirmed! 🎉',
                'sample_body' => 'Your booking (TB-202611-00001) at Legends Turf for 18 Nov 2026 is confirmed.',
                'sample_data' => ['type' => 'booking_created', 'booking_id' => '1'],
            ],
            'customer.booking_cancelled' => [
                'key' => 'customer.booking_cancelled',
                'category' => self::CATEGORY_CUSTOMER,
                'label' => 'Booking / Date Cancelled',
                'description' => 'Sent to the player when their booking (or an individual date) is cancelled.',
                'audience_label' => 'Customer',
                'kind' => self::KIND_TRANSACTIONAL,
                'channels' => ['push'],
                'android_channel_id' => 'bookings',
                'legacy_saas_column' => 'notify_booking_cancelled',
                'sample_title' => 'Booking Cancelled ⚠️',
                'sample_body' => 'Your booking (TB-202611-00001) at Legends Turf has been cancelled.',
                'sample_data' => ['type' => 'booking_cancelled', 'booking_id' => '1'],
            ],
            'customer.refund_processed' => [
                'key' => 'customer.refund_processed',
                'category' => self::CATEGORY_CUSTOMER,
                'label' => 'Refund Initiated / Processed',
                'description' => 'Sent to the player when a booking cancellation refund (> ₹0) is initiated or resolved.',
                'audience_label' => 'Customer',
                'kind' => self::KIND_TRANSACTIONAL,
                'channels' => ['push'],
                'android_channel_id' => 'bookings',
                'sample_title' => 'Refund Initiated 💰',
                'sample_body' => 'A refund of ₹600.00 for booking TB-202611-00001 at Legends Turf has been initiated.',
                'sample_data' => ['type' => 'refund_processed', 'booking_id' => '1'],
            ],
            'customer.booking_rescheduled' => [
                'key' => 'customer.booking_rescheduled',
                'category' => self::CATEGORY_CUSTOMER,
                'label' => 'Booking Rescheduled',
                'description' => 'Sent to the player when their booking date or slot time is rescheduled.',
                'audience_label' => 'Customer',
                'kind' => self::KIND_TRANSACTIONAL,
                'channels' => ['push'],
                'android_channel_id' => 'bookings',
                'legacy_saas_column' => 'notify_booking_rescheduled',
                'sample_title' => 'Booking Rescheduled 🔄',
                'sample_body' => 'Your booking (TB-202611-00001) at Legends Turf has been rescheduled from 18 Nov to 19 Nov.',
                'sample_data' => ['type' => 'booking_rescheduled', 'booking_id' => '1'],
            ],
            'customer.payment_received' => [
                'key' => 'customer.payment_received',
                'category' => self::CATEGORY_CUSTOMER,
                'label' => 'Payment Received',
                'description' => 'Sent to the player when an online or venue balance payment is recorded.',
                'audience_label' => 'Customer',
                'kind' => self::KIND_TRANSACTIONAL,
                'channels' => ['push'],
                'android_channel_id' => 'bookings',
                'legacy_saas_column' => 'notify_payment_received',
                'sample_title' => 'Payment Received 💳',
                'sample_body' => 'Payment of ₹1,200.00 received for booking TB-202611-00001.',
                'sample_data' => ['type' => 'payment_received', 'booking_id' => '1'],
            ],
            'customer.match_reminder' => [
                'key' => 'customer.match_reminder',
                'category' => self::CATEGORY_CUSTOMER,
                'label' => 'Upcoming Match Reminder',
                'description' => 'Scheduled reminder before the player\'s first slot starts (includes pending venue balance if > ₹0).',
                'audience_label' => 'Customer',
                'kind' => self::KIND_PROMOTIONAL,
                'channels' => ['push'],
                'android_channel_id' => 'reminders',
                'default_config' => ['lead_minutes' => 60],
                'sample_title' => 'Match Reminder ⏰',
                'sample_body' => 'Your match at Legends Turf starts in 1 hour (06:00 PM). Balance due at venue: ₹500.00.',
                'sample_data' => ['type' => 'match_reminder', 'booking_id' => '1'],
            ],
            'customer.review_prompt' => [
                'key' => 'customer.review_prompt',
                'category' => self::CATEGORY_CUSTOMER,
                'label' => 'Rate Your Game (Post-Match Review)',
                'description' => 'Sent once per booking after the final slot ends, inviting the player to rate the turf.',
                'audience_label' => 'Customer',
                'kind' => self::KIND_PROMOTIONAL,
                'channels' => ['push'],
                'android_channel_id' => 'reminders',
                'sample_title' => 'How was your game? ⭐',
                'sample_body' => 'Hope you enjoyed playing at Legends Turf! Tap to rate your experience.',
                'sample_data' => ['type' => 'review_prompt', 'turf_id' => '1'],
            ],
            'customer.support_reply' => [
                'key' => 'customer.support_reply',
                'category' => self::CATEGORY_CUSTOMER,
                'label' => 'Support Chat Reply',
                'description' => 'Sent to the user when an administrator replies in Support Chat.',
                'audience_label' => 'Customer / Owner',
                'kind' => self::KIND_TRANSACTIONAL,
                'channels' => ['push'],
                'android_channel_id' => 'support',
                'sample_title' => 'New Support Reply 💬',
                'sample_body' => 'Turf Booking Support replied to your message. Tap to view.',
                'sample_data' => ['type' => 'support_reply'],
            ],
            'customer.new_offer' => [
                'key' => 'customer.new_offer',
                'category' => self::CATEGORY_CUSTOMER,
                'label' => 'New Offer Near You (Coupon Alert)',
                'description' => 'Sent to players who booked that turf in the last 180 days when a new coupon is activated (rate-limited, quiet hours respected).',
                'audience_label' => 'Customer',
                'kind' => self::KIND_PROMOTIONAL,
                'channels' => ['push'],
                'android_channel_id' => 'offers',
                'sample_title' => 'New Offer at Legends Turf! 🎁',
                'sample_body' => 'Use code PLAY20 for 20% off your next booking at Legends Turf!',
                'sample_data' => ['type' => 'new_offer', 'turf_id' => '1'],
            ],

            // =================================================================
            // B. VENUE (TURF OWNER & STAFF) NOTIFICATIONS
            // =================================================================
            'venue.booking_created' => [
                'key' => 'venue.booking_created',
                'category' => self::CATEGORY_VENUE,
                'label' => 'New Booking Alert (To Venue)',
                'description' => 'Sent only to that turf\'s owner and their assigned staff when a new booking is confirmed.',
                'audience_label' => 'Turf Owner & Staff',
                'kind' => self::KIND_TRANSACTIONAL,
                'channels' => ['push'],
                'android_channel_id' => 'bookings',
                'legacy_saas_column' => 'notify_booking_created',
                'sample_title' => 'New Booking Received! 🏟️',
                'sample_body' => 'New booking (TB-202611-00001) created for Legends Turf (18 Nov 2026).',
                'sample_data' => ['type' => 'manager_booking_created', 'booking_id' => '1'],
            ],
            'venue.booking_cancelled' => [
                'key' => 'venue.booking_cancelled',
                'category' => self::CATEGORY_VENUE,
                'label' => 'Booking Cancelled Alert (To Venue)',
                'description' => 'Sent to the turf\'s owner and staff when a booking is cancelled so they know slots reopened.',
                'audience_label' => 'Turf Owner & Staff',
                'kind' => self::KIND_TRANSACTIONAL,
                'channels' => ['push'],
                'android_channel_id' => 'bookings',
                'legacy_saas_column' => 'notify_booking_cancelled',
                'sample_title' => 'Booking Cancelled (Slots Reopened) ⚠️',
                'sample_body' => 'Booking TB-202611-00001 at Legends Turf was cancelled. Slots are now open.',
                'sample_data' => ['type' => 'manager_booking_cancelled', 'booking_id' => '1'],
            ],
            'venue.booking_rescheduled' => [
                'key' => 'venue.booking_rescheduled',
                'category' => self::CATEGORY_VENUE,
                'label' => 'Booking Rescheduled Alert (To Venue)',
                'description' => 'Sent to the turf\'s owner and staff when a booking date or slot is rescheduled.',
                'audience_label' => 'Turf Owner & Staff',
                'kind' => self::KIND_TRANSACTIONAL,
                'channels' => ['push'],
                'android_channel_id' => 'bookings',
                'legacy_saas_column' => 'notify_booking_rescheduled',
                'sample_title' => 'Booking Rescheduled 🔄',
                'sample_body' => 'Booking (TB-202611-00001) for Legends Turf rescheduled from 18 Nov to 19 Nov.',
                'sample_data' => ['type' => 'manager_booking_rescheduled', 'booking_id' => '1'],
            ],
            'venue.turf_verification' => [
                'key' => 'venue.turf_verification',
                'category' => self::CATEGORY_VENUE,
                'label' => 'Turf Approved / Rejected',
                'description' => 'Sent to the turf owner when SaaS Admin or Marketing Executive approves or rejects their turf.',
                'audience_label' => 'Turf Owner',
                'kind' => self::KIND_TRANSACTIONAL,
                'channels' => ['push', 'email'],
                'android_channel_id' => 'bookings',
                'sample_title' => 'Turf Verified & Live! 🎉',
                'sample_body' => 'Congratulations! Legends Turf has been approved and is now live for bookings.',
                'sample_data' => ['type' => 'turf_verification', 'turf_id' => '1'],
            ],
            'venue.payout_paid' => [
                'key' => 'venue.payout_paid',
                'category' => self::CATEGORY_VENUE,
                'label' => 'Payout Sent / Disbursed',
                'description' => 'Sent to the turf owner when a manual or automatic payout is transferred to their bank/UPI.',
                'audience_label' => 'Turf Owner',
                'kind' => self::KIND_TRANSACTIONAL,
                'channels' => ['push'],
                'android_channel_id' => 'payouts',
                'sample_title' => 'Payout Sent 🏦',
                'sample_body' => '₹4,500.00 was transferred to your bank account. UTR AXIS12345678.',
                'sample_data' => ['type' => 'payout_paid', 'payout_id' => '1'],
            ],
            'venue.payout_rejected' => [
                'key' => 'venue.payout_rejected',
                'category' => self::CATEGORY_VENUE,
                'label' => 'Payout Rejected / Reversed',
                'description' => 'Sent to the turf owner when a payout request is rejected and funds are returned to their wallet.',
                'audience_label' => 'Turf Owner',
                'kind' => self::KIND_TRANSACTIONAL,
                'channels' => ['push'],
                'android_channel_id' => 'payouts',
                'sample_title' => 'Payout Rejected ⚠️',
                'sample_body' => '₹4,500.00 was returned to your wallet. Reason: Invalid IFSC code.',
                'sample_data' => ['type' => 'payout_rejected', 'payout_id' => '1'],
            ],
            'venue.new_review' => [
                'key' => 'venue.new_review',
                'category' => self::CATEGORY_VENUE,
                'label' => 'New Customer Review Received',
                'description' => 'Sent to the turf owner when a customer submits a rating and review for their turf.',
                'audience_label' => 'Turf Owner',
                'kind' => self::KIND_TRANSACTIONAL,
                'channels' => ['push'],
                'android_channel_id' => 'bookings',
                'sample_title' => 'New 5★ Review for Legends Turf ⭐',
                'sample_body' => 'Great turf quality and floodlights!',
                'sample_data' => ['type' => 'new_review', 'turf_id' => '1'],
            ],
            'venue.trial_reminder' => [
                'key' => 'venue.trial_reminder',
                'category' => self::CATEGORY_VENUE,
                'label' => 'Free Trial & Subscription Expiry Reminders',
                'description' => 'Sent to turf owners 3 days before and on the last day of their 0% commission free trial.',
                'audience_label' => 'Turf Owner',
                'kind' => self::KIND_TRANSACTIONAL,
                'channels' => ['push'],
                'android_channel_id' => 'reminders',
                'sample_title' => 'Free Trial Ending in 3 Days',
                'sample_body' => 'Your free trial with 0% commission ends in 3 days. Subscribe to keep 0% commission.',
                'sample_data' => ['type' => 'trial_expiring', 'days_left' => '3'],
            ],
            'venue.commission_invoice' => [
                'key' => 'venue.commission_invoice',
                'category' => self::CATEGORY_VENUE,
                'label' => 'Commission Tax Invoice Generated',
                'description' => 'Sent to the turf owner when a monthly platform commission tax invoice is generated.',
                'audience_label' => 'Turf Owner',
                'kind' => self::KIND_TRANSACTIONAL,
                'channels' => ['push', 'email'],
                'android_channel_id' => 'payouts',
                'sample_title' => 'Commission Tax Invoice Generated 🧾',
                'sample_body' => 'Your monthly commission tax invoice INV-202611-001 (₹850.00) is ready.',
                'sample_data' => ['type' => 'commission_invoice'],
            ],
            'venue.daily_digest' => [
                'key' => 'venue.daily_digest',
                'category' => self::CATEGORY_VENUE,
                'label' => 'Today\'s Bookings Morning Digest (8:00 AM)',
                'description' => 'Sent at 8:00 AM IST to venues that have at least 1 confirmed booking scheduled that day.',
                'audience_label' => 'Turf Owner & Staff',
                'kind' => self::KIND_PROMOTIONAL,
                'channels' => ['push'],
                'android_channel_id' => 'reminders',
                'sample_title' => 'Today at Legends Turf 📊',
                'sample_body' => '5 bookings scheduled today (first slot 07:00 AM). Expected venue collection: ₹3,200.00.',
                'sample_data' => ['type' => 'daily_digest', 'turf_id' => '1'],
            ],

            // =================================================================
            // C. SAAS ADMIN & MARKETING ALERTS
            // =================================================================
            'admin.turf_submitted' => [
                'key' => 'admin.turf_submitted',
                'category' => self::CATEGORY_ADMIN,
                'label' => 'Turf Submitted for Verification',
                'description' => 'Sent to SaaS Admins and Marketing Executives when a turf completes the 12-Step Onboarding Wizard.',
                'audience_label' => 'SaaS Admin & Marketing',
                'kind' => self::KIND_TRANSACTIONAL,
                'channels' => ['push', 'email'],
                'android_channel_id' => 'bookings',
                'sample_title' => 'New Turf Submitted for Verification 🏟️',
                'sample_body' => 'Legends Turf (Mumbai) has been submitted for verification.',
                'sample_data' => ['type' => 'admin_turf_submitted', 'turf_id' => '1'],
            ],
            'admin.support_message' => [
                'key' => 'admin.support_message',
                'category' => self::CATEGORY_ADMIN,
                'label' => 'New Support Message from User',
                'description' => 'Sent to SaaS Admins when a player or turf owner sends a new Support Chat message.',
                'audience_label' => 'SaaS Admin',
                'kind' => self::KIND_TRANSACTIONAL,
                'channels' => ['push'],
                'android_channel_id' => 'support',
                'sample_title' => 'New Support Message 💬',
                'sample_body' => 'Rahul Sharma: Need help rescheduling my slot.',
                'sample_data' => ['type' => 'admin_support_message'],
            ],
            'admin.payout_requested' => [
                'key' => 'admin.payout_requested',
                'category' => self::CATEGORY_ADMIN,
                'label' => 'Manual Payout Request',
                'description' => 'Sent to SaaS Admins when a turf owner requests a payout while manual payout mode is active.',
                'audience_label' => 'SaaS Admin',
                'kind' => self::KIND_TRANSACTIONAL,
                'channels' => ['push'],
                'android_channel_id' => 'payouts',
                'sample_title' => 'Payout Request 🏦',
                'sample_body' => 'Sandeep requested ₹4,500.00. Transfer it and mark it paid in Payout Manager.',
                'sample_data' => ['type' => 'manual_payout_requested', 'payout_id' => '1'],
            ],
            'admin.payout_failed' => [
                'key' => 'admin.payout_failed',
                'category' => self::CATEGORY_ADMIN,
                'label' => 'Payout Failed / Reversed Alert',
                'description' => 'Sent to SaaS Admins when an automated RazorpayX payout webhook reports failed or reversed.',
                'audience_label' => 'SaaS Admin',
                'kind' => self::KIND_TRANSACTIONAL,
                'channels' => ['push'],
                'android_channel_id' => 'payouts',
                'sample_title' => 'Payout Failed ⚠️',
                'sample_body' => 'RazorpayX payout #12 (₹4,500.00) failed and was reversed to the owner wallet.',
                'sample_data' => ['type' => 'admin_payout_failed', 'payout_id' => '1'],
            ],
        ];
    }

    /**
     * Get definition for a single event key.
     */
    public static function get(string $eventKey): ?array
    {
        return self::all()[$eventKey] ?? null;
    }

    /**
     * Group events by category.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function byCategory(): array
    {
        $grouped = [
            self::CATEGORY_CUSTOMER => [],
            self::CATEGORY_VENUE => [],
            self::CATEGORY_ADMIN => [],
        ];

        foreach (self::all() as $key => $definition) {
            $grouped[$definition['category']][$key] = $definition;
        }

        return $grouped;
    }
}
