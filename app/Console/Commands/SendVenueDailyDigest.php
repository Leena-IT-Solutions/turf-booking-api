<?php

namespace App\Console\Commands;

use App\Models\BookingDate;
use App\Models\Turf;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendVenueDailyDigest extends Command
{
    protected $signature = 'notifications:venue-daily-digest {--date= : Optional YYYY-MM-DD date override for testing}';

    protected $description = 'Send an 8:00 AM IST morning digest to venues that have at least 1 confirmed booking today.';

    public function handle(): int
    {
        $todayStr = $this->option('date')
            ? Carbon::parse((string) $this->option('date'), 'Asia/Kolkata')->toDateString()
            : Carbon::now('Asia/Kolkata')->toDateString();

        $bookingDates = BookingDate::with(['booking.turf.location', 'bookingSlots' => function ($q) {
            $q->where('status', 'active')->with('slot');
        }])
            ->where('status', 'Confirmed')
            ->where('booking_date', $todayStr)
            ->whereHas('booking', fn ($q) => $q->where('status', '!=', 'Cancelled'))
            ->get();

        $byTurf = $bookingDates->groupBy(fn (BookingDate $bd) => $bd->booking?->turf_id)->filter(fn ($_, $turfId) => ! empty($turfId));

        $sentTurfs = 0;

        foreach ($byTurf as $turfId => $datesForTurf) {
            $cacheKey = "venue_daily_digest:{$turfId}:{$todayStr}";
            if (Cache::has($cacheKey)) {
                continue;
            }

            /** @var Turf|null $turf */
            $turf = $datesForTurf->first()?->booking?->turf ?? Turf::with('location')->find($turfId);
            if (! $turf) {
                continue;
            }

            $venueUserIds = NotificationService::venueRecipientIds($turf);
            if (empty($venueUserIds)) {
                continue;
            }

            $count = $datesForTurf->count();
            $expectedBalance = (float) $datesForTurf->sum('balance_amount');

            $earliestTime = $datesForTurf
                ->flatMap(fn (BookingDate $bd) => $bd->bookingSlots->map(fn ($bs) => $bs->slot?->from_time))
                ->filter()
                ->sort()
                ->first();

            $firstSlotText = $earliestTime
                ? ' (first slot '.Carbon::parse("{$todayStr} {$earliestTime}", 'Asia/Kolkata')->format('h:i A').')'
                : '';

            $bookingWord = $count === 1 ? '1 booking' : "{$count} bookings";
            $body = "{$bookingWord} scheduled today{$firstSlotText}. Expected venue collection: ₹".number_format($expectedBalance, 2).'.';

            NotificationService::dispatchEventPush(
                'venue.daily_digest',
                $venueUserIds,
                "Today at {$turf->name} 📊",
                $body,
                [
                    'type' => 'daily_digest',
                    'turf_id' => (string) $turf->id,
                    'date' => $todayStr,
                ]
            );

            Cache::put($cacheKey, true, now()->addHours(36));
            $sentTurfs++;
        }

        $this->info("Venue daily digests processed: {$sentTurfs}");

        return self::SUCCESS;
    }
}
