<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\BookingDate;
use App\Models\BookingReschedule;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BookingsLedgerService
{
    /**
     * Build the filtered query for the Bookings Ledger.
     */
    public function query(array $turfIds, array $filters = []): Builder
    {
        $dateBasis = $filters['date_basis'] ?? 'session'; // 'session' or 'booked'
        $startDate = $filters['start'] ?? null;
        $endDate = $filters['end'] ?? null;
        $status = $filters['status'] ?? 'all';
        $paymentStatus = $filters['payment_status'] ?? 'all';
        $channel = $filters['channel'] ?? 'all'; // 'all', 'online', 'venue'
        $search = $filters['search'] ?? null;

        $query = Booking::query()->whereIn('turf_id', $turfIds);

        // Date Basis filtering
        if ($startDate && $endDate) {
            if ($dateBasis === 'booked') {
                $query->whereBetween('created_at', [
                    Carbon::parse($startDate, 'Asia/Kolkata')->startOfDay(),
                    Carbon::parse($endDate, 'Asia/Kolkata')->endOfDay(),
                ]);
            } else {
                // Session basis: booking has at least one booking date in range
                $query->whereHas('bookingDates', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('booking_date', [$startDate, $endDate]);
                });
            }
        }

        // Status filter
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        // Payment status filter
        if ($paymentStatus && $paymentStatus !== 'all') {
            $query->where('payment_status', $paymentStatus);
        }

        // Channel filter
        if ($channel === 'online') {
            $query->whereHas('payments', function ($q) {
                $q->where('payment_method', 'App');
            });
        } elseif ($channel === 'venue') {
            $query->whereHas('payments', function ($q) {
                $q->whereIn('payment_method', ['Cash', 'UPI', 'Other', 'offline']);
            });
        }

        // Search filter: reference, booking_number, customer name, mobile
        if (!empty($search)) {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->where('booking_number', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('mobile', 'like', "%{$search}%");
                  });
            });
        }

        // Eager load relationships needed for rendering without N+1
        $query->with([
            'bookingDates.bookingSlots.slot',
            'bookingDates.couponUsage.coupon',
            'bookingDates.reschedules',
            'reschedules',
            'payments',
            'bookingCancellations',
            'turf',
            'user',
            'bookedBy',
        ]);

        // Order: newest first, by chosen date basis
        if ($dateBasis === 'booked') {
            $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');
        } else {
            $query->orderBy(
                BookingDate::selectRaw('MAX(booking_date)')
                    ->whereColumn('booking_dates.booking_id', 'bookings.id'),
                'desc'
            )->orderBy('id', 'desc');
        }

        return $query;
    }

    /**
     * Compute aggregated summary metrics over the filtered set.
     */
    public function summary(array $turfIds, array $filters = []): array
    {
        // Clone base query without heavy eager loads to extract IDs
        $baseQuery = $this->query($turfIds, $filters);
        $baseQuery->without([
            'bookingDates.bookingSlots.slot',
            'bookingDates.couponUsage.coupon',
            'bookingDates.reschedules',
            'turf',
            'user',
            'bookedBy',
        ]);

        $bookingIds = (clone $baseQuery)->pluck('id')->all();
        $count = count($bookingIds);

        if ($count === 0) {
            return [
                'count' => 0,
                'booking_value' => 0.00,
                'online_collected' => 0.00,
                'venue_collected' => 0.00,
                'venue_split' => [
                    'Cash' => 0.00,
                    'UPI' => 0.00,
                    'Other' => 0.00,
                ],
                'refunded' => 0.00,
                'refund_pending' => 0.00,
                'net_collected' => 0.00,
                'due_at_venue' => 0.00,
                'by_channel_count' => [
                    'online' => 0,
                    'venue' => 0,
                    'mixed' => 0,
                ],

                // Part 1 New Figures
                'count_active' => 0,
                'count_cancelled' => 0,
                'count_partly_cancelled' => 0,
                'active_value' => 0.00,
                'cancelled_value' => 0.00,
                'received' => 0.00,
                'due_count' => 0,
                'app_booking_count' => 0,
                'cancelled_sessions' => 0,
                'all_sessions' => 0,
                'cancellation_refunded' => 0.00,
                'cancellation_kept_turf' => 0.00,
                'cancellation_kept_platform' => 0.00,
                'discount_coupon' => 0.00,
                'discount_staff' => 0.00,
                'active_hours' => 0.0,
                'avg_booking_value' => 0.00,
                'collection_rate' => 0.0,
                'cancellation_rate' => 0.0,
                'booked_by' => [
                    'app' => 0,
                    'staff' => 0,
                    'unknown' => 0,
                ],
            ];
        }

        $bookingValue = (float) (clone $baseQuery)->sum('total_amount');

        // Aggregated payments by channel and method
        $paymentsAgg = Payment::whereIn('booking_id', $bookingIds)
            ->where('status', 'Success')
            ->selectRaw("
                SUM(CASE WHEN payment_method = 'App' THEN amount ELSE 0 END) as online_collected,
                SUM(CASE WHEN payment_method = 'Cash' THEN amount ELSE 0 END) as cash_collected,
                SUM(CASE WHEN payment_method = 'UPI' THEN amount ELSE 0 END) as upi_collected,
                SUM(CASE WHEN payment_method NOT IN ('App', 'Cash', 'UPI') THEN amount ELSE 0 END) as other_collected
            ")
            ->first();

        $onlineCollected = (float) ($paymentsAgg?->online_collected ?? 0.0);
        $cashCollected = (float) ($paymentsAgg?->cash_collected ?? 0.0);
        $upiCollected = (float) ($paymentsAgg?->upi_collected ?? 0.0);
        $otherCollected = (float) ($paymentsAgg?->other_collected ?? 0.0);
        $venueCollected = round($cashCollected + $upiCollected + $otherCollected, 2);
        $received = round($onlineCollected + $venueCollected, 2);

        // Aggregated refunds from cancellation and reschedule records
        $cancellationRefund = (float) BookingCancellation::whereIn('booking_id', $bookingIds)
            ->whereIn('refund_status', ['Refunded', 'Cash / Offline Refund'])
            ->sum('refund_amount');

        $rescheduleRefund = (float) BookingReschedule::whereIn('booking_id', $bookingIds)
            ->where('settlement_type', 'refund')
            ->sum('refund_amount');

        $refunded = round($cancellationRefund + $rescheduleRefund, 2);

        $refundPending = (float) BookingCancellation::whereIn('booking_id', $bookingIds)
            ->whereIn('refund_status', ['Pending', 'Pending Resolution'])
            ->sum('refund_amount');

        $netCollected = round(($onlineCollected + $venueCollected) - $refunded, 2);

        // Due at venue and due_count: sum of balance_due over non-cancelled bookings
        $nonCancelledBookings = (clone $baseQuery)
            ->where('status', '!=', 'Cancelled')
            ->with([
                'bookingDates' => fn($q) => $q->select('id', 'booking_id', 'amount', 'status'),
                'bookingDates.reschedules' => fn($q) => $q->where('settlement_type', 'refund')->select('id', 'booking_date_id', 'refund_amount', 'settlement_type'),
                'reschedules' => fn($q) => $q->where('settlement_type', 'refund')->select('id', 'booking_id', 'refund_amount', 'settlement_type'),
                'payments' => fn($q) => $q->select('id', 'booking_id', 'amount', 'payment_method', 'status'),
                'bookingCancellations' => fn($q) => $q->whereIn('refund_status', ['Refunded', 'Cash / Offline Refund'])->select('id', 'booking_id', 'refund_amount', 'refund_status'),
            ])
            ->get();

        $dueAtVenue = 0.00;
        $dueCount = 0;
        foreach ($nonCancelledBookings as $b) {
            $rowMetrics = $this->rowMetrics($b);
            $dueAtVenue += $rowMetrics['balance_due'];
            if ($rowMetrics['balance_due'] > 0) {
                $dueCount++;
            }
        }
        $dueAtVenue = round($dueAtVenue, 2);

        // Channel breakdown counts
        $channelBookings = (clone $baseQuery)
            ->with(['payments' => fn($q) => $q->select('id', 'booking_id', 'payment_method', 'status', 'amount')])
            ->get();

        $byChannelCount = ['online' => 0, 'venue' => 0, 'mixed' => 0];
        foreach ($channelBookings as $b) {
            $ch = $this->resolveChannel($b);
            if (isset($byChannelCount[$ch])) {
                $byChannelCount[$ch]++;
            }
        }

        // Part 1 New Figures
        // 1. Booking status counts, discounts, and booking_source breakdown
        $bookingsAgg = Booking::whereIn('id', $bookingIds)
            ->selectRaw("
                SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as count_active,
                SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as count_cancelled,
                SUM(CASE WHEN status = 'Partially Cancelled' THEN 1 ELSE 0 END) as count_partly_cancelled,
                SUM(COALESCE(coupon_discount, 0)) as discount_coupon,
                SUM(COALESCE(additional_discount, 0)) as discount_staff,
                SUM(CASE WHEN booking_source = 'app' THEN 1 ELSE 0 END) as booked_app,
                SUM(CASE WHEN booking_source = 'staff' THEN 1 ELSE 0 END) as booked_staff,
                SUM(CASE WHEN booking_source IS NULL OR booking_source NOT IN ('app', 'staff') THEN 1 ELSE 0 END) as booked_unknown
            ")
            ->first();

        $countActive = (int) ($bookingsAgg?->count_active ?? 0);
        $countCancelled = (int) ($bookingsAgg?->count_cancelled ?? 0);
        $countPartlyCancelled = (int) ($bookingsAgg?->count_partly_cancelled ?? 0);
        $discountCoupon = round((float) ($bookingsAgg?->discount_coupon ?? 0.0), 2);
        $discountStaff = round((float) ($bookingsAgg?->discount_staff ?? 0.0), 2);
        $bookedBy = [
            'app' => (int) ($bookingsAgg?->booked_app ?? 0),
            'staff' => (int) ($bookingsAgg?->booked_staff ?? 0),
            'unknown' => (int) ($bookingsAgg?->booked_unknown ?? 0),
        ];

        // 2. Booking dates aggregates: active_value, cancelled_value, session counts
        $datesAgg = BookingDate::whereIn('booking_id', $bookingIds)
            ->selectRaw("
                SUM(CASE WHEN status != 'Cancelled' THEN amount ELSE 0 END) as active_value,
                SUM(CASE WHEN status = 'Cancelled' THEN amount ELSE 0 END) as cancelled_value,
                SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_sessions,
                COUNT(*) as all_sessions
            ")
            ->first();

        $activeValue = round((float) ($datesAgg?->active_value ?? 0.0), 2);
        $cancelledValue = round((float) ($datesAgg?->cancelled_value ?? 0.0), 2);
        $cancelledSessions = (int) ($datesAgg?->cancelled_sessions ?? 0);
        $allSessions = (int) ($datesAgg?->all_sessions ?? 0);

        // 3. App booking count (bookings with any Success App payment)
        $appBookingCount = Payment::whereIn('booking_id', $bookingIds)
            ->where('status', 'Success')
            ->where('payment_method', 'App')
            ->distinct()
            ->count('booking_id');

        // 4. Cancellation figures: refunded, kept by turf, kept by platform
        $cancellationsAgg = BookingCancellation::whereIn('booking_id', $bookingIds)
            ->selectRaw("
                SUM(CASE WHEN refund_status IN ('Refunded', 'Cash / Offline Refund') THEN refund_amount ELSE 0 END) as cancellation_refunded,
                SUM(CASE WHEN refund_status NOT IN ('Pending', 'Pending Resolution') THEN turf_cancellation_fee ELSE 0 END) as cancellation_kept_turf,
                SUM(CASE WHEN refund_status NOT IN ('Pending', 'Pending Resolution') THEN (COALESCE(platform_fee_retained, 0) + COALESCE(saas_cancellation_fee, 0)) ELSE 0 END) as cancellation_kept_platform
            ")
            ->first();

        $cancellationRefunded = round((float) ($cancellationsAgg?->cancellation_refunded ?? 0.0), 2);
        $cancellationKeptTurf = round((float) ($cancellationsAgg?->cancellation_kept_turf ?? 0.0), 2);
        $cancellationKeptPlatform = round((float) ($cancellationsAgg?->cancellation_kept_platform ?? 0.0), 2);

        // 5. Active hours (cross-db compatible for MySQL & SQLite)
        $knownDurationMinutes = (int) DB::table('booking_slots')
            ->join('booking_dates', 'booking_slots.booking_date_id', '=', 'booking_dates.id')
            ->join('slots', 'booking_slots.slot_id', '=', 'slots.id')
            ->whereIn('booking_dates.booking_id', $bookingIds)
            ->where('booking_dates.status', '!=', 'Cancelled')
            ->where('booking_slots.status', '!=', 'cancelled')
            ->where('slots.duration', '>', 0)
            ->sum('slots.duration');

        $fallbackSlots = DB::table('booking_slots')
            ->join('booking_dates', 'booking_slots.booking_date_id', '=', 'booking_dates.id')
            ->join('slots', 'booking_slots.slot_id', '=', 'slots.id')
            ->whereIn('booking_dates.booking_id', $bookingIds)
            ->where('booking_dates.status', '!=', 'Cancelled')
            ->where('booking_slots.status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->whereNull('slots.duration')->orWhere('slots.duration', '<=', 0);
            })
            ->select('slots.from_time', 'slots.to_time')
            ->get();

        $fallbackMinutes = 0;
        foreach ($fallbackSlots as $fs) {
            if ($fs->from_time && $fs->to_time) {
                $f = Carbon::parse($fs->from_time);
                $t = Carbon::parse($fs->to_time);
                $fallbackMinutes += abs((float) $t->diffInMinutes($f));
            }
        }

        $activeHours = round(($knownDurationMinutes + $fallbackMinutes) / 60, 1);

        // 6. Derived ratios (unclamped)
        $activeBookingsCount = $countActive + $countPartlyCancelled;
        $avgBookingValue = $activeBookingsCount > 0 ? round($activeValue / $activeBookingsCount, 2) : 0.00;
        $collectionRate = $activeValue > 0 ? round((($activeValue - $dueAtVenue) / $activeValue) * 100, 1) : 0.0;
        $cancellationRate = $allSessions > 0 ? round(($cancelledSessions / $allSessions) * 100, 1) : 0.0;

        return [
            'count' => $count,
            'booking_value' => $bookingValue,
            'online_collected' => $onlineCollected,
            'venue_collected' => $venueCollected,
            'venue_split' => [
                'Cash' => $cashCollected,
                'UPI' => $upiCollected,
                'Other' => $otherCollected,
            ],
            'refunded' => $refunded,
            'refund_pending' => $refundPending,
            'net_collected' => $netCollected,
            'due_at_venue' => $dueAtVenue,
            'by_channel_count' => $byChannelCount,

            // Part 1 New Figures
            'count_active' => $countActive,
            'count_cancelled' => $countCancelled,
            'count_partly_cancelled' => $countPartlyCancelled,
            'active_value' => $activeValue,
            'cancelled_value' => $cancelledValue,
            'received' => $received,
            'due_count' => $dueCount,
            'app_booking_count' => $appBookingCount,
            'cancelled_sessions' => $cancelledSessions,
            'all_sessions' => $allSessions,
            'cancellation_refunded' => $cancellationRefunded,
            'cancellation_kept_turf' => $cancellationKeptTurf,
            'cancellation_kept_platform' => $cancellationKeptPlatform,
            'discount_coupon' => $discountCoupon,
            'discount_staff' => $discountStaff,
            'active_hours' => $activeHours,
            'avg_booking_value' => $avgBookingValue,
            'collection_rate' => $collectionRate,
            'cancellation_rate' => $cancellationRate,
            'booked_by' => $bookedBy,
        ];
    }

    /**
     * Resolve the channel ('online', 'venue', 'mixed', 'none') for a booking.
     */
    public function resolveChannel(Booking $booking): string
    {
        $onlinePaid = (float) $booking->payments
            ->where('status', 'Success')
            ->where('payment_method', 'App')
            ->sum('amount');

        $venuePaid = (float) $booking->payments
            ->where('status', 'Success')
            ->whereIn('payment_method', ['Cash', 'UPI', 'Other'])
            ->sum('amount');

        if ($onlinePaid > 0 && $venuePaid > 0) {
            return 'mixed';
        }
        if ($onlinePaid > 0) {
            return 'online';
        }
        if ($venuePaid > 0) {
            return 'venue';
        }

        // Check pending/offline payments when no success payment has been recorded yet
        $hasApp = $booking->payments->where('payment_method', 'App')->isNotEmpty();
        $hasVenue = $booking->payments->whereIn('payment_method', ['offline', 'Cash', 'UPI', 'Other'])->isNotEmpty();

        if ($hasApp && $hasVenue) {
            return 'mixed';
        }
        if ($hasApp) {
            return 'online';
        }
        if ($hasVenue) {
            return 'venue';
        }

        return 'none';
    }

    /**
     * Compute all per-row financial metrics for a booking.
     */
    public function rowMetrics(Booking $booking): array
    {
        $onlinePaid = (float) $booking->payments
            ->where('status', 'Success')
            ->where('payment_method', 'App')
            ->sum('amount');

        $venuePaid = (float) $booking->payments
            ->where('status', 'Success')
            ->whereIn('payment_method', ['Cash', 'UPI', 'Other'])
            ->sum('amount');

        $cash = (float) $booking->payments
            ->where('status', 'Success')
            ->where('payment_method', 'Cash')
            ->sum('amount');

        $upi = (float) $booking->payments
            ->where('status', 'Success')
            ->where('payment_method', 'UPI')
            ->sum('amount');

        $other = (float) $booking->payments
            ->where('status', 'Success')
            ->where('payment_method', 'Other')
            ->sum('amount');

        // Cancellations with confirmed refunds
        $cancellationRefund = (float) $booking->bookingCancellations
            ->whereIn('refund_status', ['Refunded', 'Cash / Offline Refund'])
            ->sum('refund_amount');

        // Reschedule refunds
        $rescheduleRefund = (float) $booking->bookingDates
            ->flatMap(fn($bd) => $bd->reschedules ?? collect())
            ->where('settlement_type', 'refund')
            ->sum('refund_amount');

        if ($booking->relationLoaded('reschedules')) {
            $directRescheduleRefund = (float) $booking->reschedules
                ->where('settlement_type', 'refund')
                ->sum('refund_amount');
            $rescheduleRefund = max($rescheduleRefund, $directRescheduleRefund);
        }

        $refunded = round($cancellationRefund + $rescheduleRefund, 2);

        $refundPending = (float) $booking->bookingCancellations
            ->whereIn('refund_status', ['Pending', 'Pending Resolution'])
            ->sum('refund_amount');

        $grossPaid = round($onlinePaid + $venuePaid, 2);
        $netPaid = round($grossPaid - $refunded, 2);

        $bookingValue = (float) $booking->total_amount;
        $activeValue = (float) $booking->bookingDates->where('status', '!=', 'Cancelled')->sum('amount');

        $balanceDue = ($booking->status === 'Cancelled') ? 0.00 : max(0.00, round($activeValue - $netPaid, 2));

        $channel = $this->resolveChannel($booking);
        $discount = round((float)$booking->coupon_discount + (float)$booking->additional_discount, 2);

        $couponCodes = $booking->bookingDates
            ->map(fn($bd) => $bd->couponUsage?->coupon?->code)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $sessionData = $this->formatSessions($booking);

        $bookedByLabel = match ($booking->booking_source) {
            'app' => 'Player (app)',
            'staff' => 'Staff: ' . ($booking->bookedBy?->name ?? 'Staff'),
            default => '—',
        };

        return [
            'online_paid' => $onlinePaid,
            'venue_paid' => $venuePaid,
            'venue_split' => [
                'Cash' => $cash,
                'UPI' => $upi,
                'Other' => $other,
            ],
            'refunded' => $refunded,
            'refund_pending' => $refundPending,
            'gross_paid' => $grossPaid,
            'net_paid' => $netPaid,
            'booking_value' => $bookingValue,
            'active_value' => $activeValue,
            'balance_due' => $balanceDue,
            'channel' => $channel,
            'discount' => $discount,
            'coupon_codes' => $couponCodes,
            'sessions' => $sessionData['lines'],
            'total_hours' => $sessionData['total_hours'],
            'hours_text' => $sessionData['hours_text'],
            'booked_by_label' => $bookedByLabel,
        ];
    }

    /**
     * Format session date/time strings and total hours for display.
     */
    public function formatSessions(Booking $booking): array
    {
        $lines = [];
        $totalMinutes = 0;

        foreach ($booking->bookingDates as $bd) {
            $dateStr = Carbon::parse($bd->booking_date)->format('D d M');
            $isCancelled = ($bd->status === 'Cancelled');

            $activeSlots = $bd->bookingSlots->filter(fn($bs) => $bs->slot !== null);

            if ($activeSlots->isNotEmpty()) {
                $earliestFrom = $activeSlots->min(fn($bs) => $bs->slot->from_time);
                $latestTo = $activeSlots->max(fn($bs) => $bs->slot->to_time);
                $timeStr = self::formatTimeRange($earliestFrom, $latestTo);
                $line = "{$dateStr}, {$timeStr}" . ($isCancelled ? ' (cancelled)' : '');

                foreach ($activeSlots as $bs) {
                    $dur = (int) ($bs->slot->duration ?? 0);
                    if ($dur > 0) {
                        $totalMinutes += $dur;
                    } elseif ($bs->slot->from_time && $bs->slot->to_time) {
                        $f = Carbon::parse($bs->slot->from_time);
                        $t = Carbon::parse($bs->slot->to_time);
                        $totalMinutes += abs((float) $t->diffInMinutes($f));
                    }
                }
            } else {
                $line = $dateStr . ($isCancelled ? ' (cancelled)' : '');
            }

            $lines[] = $line;
        }

        $totalHours = round($totalMinutes / 60, 1);
        $hoursText = ($totalHours == 1) ? '1 hr' : ($totalHours . ' hrs');

        return [
            'lines' => $lines,
            'total_hours' => $totalHours,
            'hours_text' => $hoursText,
        ];
    }

    /**
     * Format time range: "5:00–6:30 PM" (if same meridian) or "11:00 AM–12:30 PM".
     */
    public static function formatTimeRange(string $fromTime, string $toTime): string
    {
        $fromCarbon = Carbon::parse($fromTime);
        $toCarbon = Carbon::parse($toTime);

        $fromMeridian = $fromCarbon->format('A');
        $toMeridian = $toCarbon->format('A');

        if ($fromMeridian === $toMeridian) {
            return $fromCarbon->format('g:i') . '–' . $toCarbon->format('g:i A');
        }

        return $fromCarbon->format('g:i A') . '–' . $toCarbon->format('g:i A');
    }
}
