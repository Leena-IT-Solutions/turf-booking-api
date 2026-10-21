<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\BookingDate;
use App\Models\BookingReschedule;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

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

        // Due at venue: sum of balance_due over non-cancelled bookings
        $nonCancelledBookings = (clone $baseQuery)
            ->where('status', '!=', 'Cancelled')
            ->with([
                'bookingDates' => fn($q) => $q->select('id', 'booking_id', 'amount', 'status'),
                'bookingDates.reschedules' => fn($q) => $q->where('settlement_type', 'refund')->select('id', 'booking_date_id', 'refund_amount', 'settlement_type'),
                'payments' => fn($q) => $q->select('id', 'booking_id', 'amount', 'payment_method', 'status'),
                'bookingCancellations' => fn($q) => $q->whereIn('refund_status', ['Refunded', 'Cash / Offline Refund'])->select('id', 'booking_id', 'refund_amount', 'refund_status'),
            ])
            ->get();

        $dueAtVenue = 0.00;
        foreach ($nonCancelledBookings as $b) {
            $rowMetrics = $this->rowMetrics($b);
            $dueAtVenue += $rowMetrics['balance_due'];
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
                    $f = Carbon::parse($bs->slot->from_time);
                    $t = Carbon::parse($bs->slot->to_time);
                    $totalMinutes += max(0, $t->diffInMinutes($f));
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
