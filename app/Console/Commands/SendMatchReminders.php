<?php

namespace App\Console\Commands;

use App\Models\BookingDate;
use App\Models\NotificationSetting;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendMatchReminders extends Command
{
    protected $signature = 'notifications:match-reminders {--now= : Optional IST timestamp override for testing}';

    protected $description = 'Send upcoming match reminders to players based on the configurable SaaS Admin lead time.';

    public function handle(): int
    {
        $nowIst = $this->option('now')
            ? Carbon::parse((string) $this->option('now'), 'Asia/Kolkata')
            : Carbon::now('Asia/Kolkata');

        $leadMinutes = (int) NotificationSetting::configValue('customer.match_reminder', 'lead_minutes', 60);
        if ($leadMinutes <= 0) {
            $leadMinutes = 60;
        }

        $windowEndIst = $nowIst->copy()->addMinutes($leadMinutes);
        $isQuietHourNow = ((int) $nowIst->format('G') >= 22 || (int) $nowIst->format('G') < 8);

        $dateFrom = $nowIst->copy()->subDay()->toDateString();
        $dateTo = $windowEndIst->copy()->addDay()->toDateString();

        $bookingDates = BookingDate::with(['booking.turf', 'bookingSlots' => function ($q) {
            $q->where('status', 'active')->with('slot');
        }])
            ->where('status', 'Confirmed')
            ->whereNull('reminder_sent_at')
            ->whereBetween('booking_date', [$dateFrom, $dateTo])
            ->whereHas('booking', fn ($q) => $q->where('status', '!=', 'Cancelled')->whereNotNull('user_id'))
            ->get();

        $sentCount = 0;

        foreach ($bookingDates as $bDate) {
            $booking = $bDate->booking;
            if (! $booking || ! $booking->user_id) {
                continue;
            }

            $slotTimes = $bDate->bookingSlots
                ->map(fn ($bs) => $bs->slot?->from_time)
                ->filter()
                ->sort()
                ->values();

            if ($slotTimes->isEmpty()) {
                continue;
            }

            $earliestTime = $slotTimes->first();
            $dateStr = Carbon::parse($bDate->booking_date)->toDateString();
            $startIst = Carbon::parse("{$dateStr} {$earliestTime}", 'Asia/Kolkata');

            // Must be in the future and within [now, now + leadMinutes]
            if (! $startIst->gt($nowIst) || ! $startIst->lte($windowEndIst)) {
                continue;
            }

            // Quiet hours check: if now is 10 PM – 8 AM IST and the match itself starts
            // at or after 8:00 AM IST, hold the reminder until 8:00 AM IST.
            // Matches starting inside quiet hours (e.g. 6:00 AM game) still get reminded on time.
            $matchStartHour = (int) $startIst->format('G');
            $matchStartsInQuietHours = ($matchStartHour >= 22 || $matchStartHour < 8);
            if ($isQuietHourNow && ! $matchStartsInQuietHours) {
                continue;
            }

            $turfName = $booking->turf?->name ?? 'Turf';
            $formattedSlotTime = $startIst->format('h:i A');
            $minsUntilStart = max(1, (int) round($nowIst->diffInMinutes($startIst)));

            if ($minsUntilStart >= 120 && $startIst->toDateString() !== $nowIst->toDateString()) {
                $whenPhrase = "tomorrow at {$formattedSlotTime}";
            } elseif ($minsUntilStart >= 90) {
                $hours = round($minsUntilStart / 60, 1);
                $hoursStr = rtrim(rtrim(number_format($hours, 1), '0'), '.');
                $whenPhrase = "in {$hoursStr} hours ({$formattedSlotTime})";
            } elseif ($minsUntilStart >= 55 && $minsUntilStart <= 65) {
                $whenPhrase = "in 1 hour ({$formattedSlotTime})";
            } else {
                $whenPhrase = "in {$minsUntilStart} minutes ({$formattedSlotTime})";
            }

            $body = "Your match at {$turfName} starts {$whenPhrase}.";
            if ((float) $bDate->balance_amount > 0) {
                $body .= ' Balance due at venue: ₹'.number_format((float) $bDate->balance_amount, 2).'.';
            }

            NotificationService::dispatchEventPush(
                'customer.match_reminder',
                [(int) $booking->user_id],
                'Match Reminder ⏰',
                $body,
                [
                    'type' => 'match_reminder',
                    'booking_id' => (string) $booking->id,
                    'booking_date_id' => (string) $bDate->id,
                ]
            );

            $bDate->update(['reminder_sent_at' => now()]);
            $sentCount++;
        }

        $this->info("Match reminders processed: {$sentCount}");

        return self::SUCCESS;
    }
}
