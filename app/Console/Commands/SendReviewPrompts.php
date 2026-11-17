<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Review;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendReviewPrompts extends Command
{
    protected $signature = 'notifications:review-prompts {--now= : Optional IST timestamp override for testing}';

    protected $description = 'Send post-match review prompts once per booking after the final slot finishes.';

    public function handle(): int
    {
        $nowIst = $this->option('now')
            ? Carbon::parse((string) $this->option('now'), 'Asia/Kolkata')
            : Carbon::now('Asia/Kolkata');

        // Promotional safeguard: do not send review prompts during quiet hours (10 PM – 8 AM IST)
        $hour = (int) $nowIst->format('G');
        if ($hour >= 22 || $hour < 8) {
            $this->info('Quiet hours active; holding review prompts until 08:00 AM IST.');

            return self::SUCCESS;
        }

        $minEndedAt = $nowIst->copy()->subHours(12);
        $maxEndedAt = $nowIst->copy()->subHour();

        $bookings = Booking::with(['turf', 'bookingDates' => function ($q) {
            $q->where('status', 'Confirmed')->with(['bookingSlots' => function ($sq) {
                $sq->where('status', 'active')->with('slot');
            }]);
        }])
            ->where('status', '!=', 'Cancelled')
            ->whereNotNull('user_id')
            ->whereNull('review_prompt_sent_at')
            ->whereHas('bookingDates', function ($q) use ($nowIst) {
                $q->where('status', 'Confirmed')
                    ->where('booking_date', '<=', $nowIst->toDateString());
            })
            ->get();

        $sentCount = 0;

        foreach ($bookings as $booking) {
            if ($booking->bookingDates->isEmpty()) {
                continue;
            }

            // Compute the end timestamp of the very last confirmed date/slot of this booking
            $latestEndIst = null;

            foreach ($booking->bookingDates as $bDate) {
                $dateStr = Carbon::parse($bDate->booking_date)->toDateString();

                foreach ($bDate->bookingSlots as $bs) {
                    $slot = $bs->slot;
                    if (! $slot || ! $slot->to_time) {
                        continue;
                    }

                    $endIst = Carbon::parse("{$dateStr} {$slot->to_time}", 'Asia/Kolkata');
                    // Handle midnight-crossing slot (e.g. 23:00 -> 00:00)
                    if ($slot->from_time && $slot->to_time <= $slot->from_time) {
                        $endIst->addDay();
                    }

                    if ($latestEndIst === null || $endIst->gt($latestEndIst)) {
                        $latestEndIst = $endIst;
                    }
                }
            }

            if (! $latestEndIst) {
                continue;
            }

            // Only prompt when the entire booking has finished between 1 and 12 hours ago
            if (! $latestEndIst->lte($maxEndedAt) || ! $latestEndIst->gte($minEndedAt)) {
                continue;
            }

            // Skip (and mark done) if the player has already reviewed this turf
            $alreadyReviewed = Review::where('user_id', $booking->user_id)
                ->where('turf_id', $booking->turf_id)
                ->exists();

            if ($alreadyReviewed) {
                $booking->update(['review_prompt_sent_at' => now()]);

                continue;
            }

            $turfName = $booking->turf?->name ?? 'the turf';

            NotificationService::dispatchEventPush(
                'customer.review_prompt',
                [(int) $booking->user_id],
                'How was your game? ⭐',
                "Hope you enjoyed playing at {$turfName}! Tap to rate your experience.",
                [
                    'type' => 'review_prompt',
                    'turf_id' => (string) $booking->turf_id,
                    'booking_id' => (string) $booking->id,
                ]
            );

            $booking->update(['review_prompt_sent_at' => now()]);
            $sentCount++;
        }

        $this->info("Review prompts processed: {$sentCount}");

        return self::SUCCESS;
    }
}
