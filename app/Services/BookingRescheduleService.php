<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\BookingReschedule;
use App\Models\BookingSlot;
use App\Models\Payment;
use App\Models\Slot;
use App\Models\SlotLock;
use App\Models\Turf;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BookingRescheduleService
{
    /**
     * Get slots for a target date with pricing and availability for rescheduling.
     */
    public function slots(BookingDate $bDate, string $toDate): array
    {
        $bDate->load(['booking.turf', 'bookingSlots']);
        $booking = $bDate->booking;
        $turf = $booking->turf;

        $today = Carbon::today('Asia/Kolkata')->toDateString();
        $nowTime = Carbon::now('Asia/Kolkata')->toTimeString();
        $isToday = ($toDate === $today);

        // Own current slots on this booking date
        $ownSlotIds = $bDate->bookingSlots()->pluck('slot_id')->toArray();
        $isSameDate = ($toDate === $bDate->booking_date);

        // Occupied slots by other bookings on target date
        $occupiedSlotIds = BookingSlot::where('status', '!=', 'cancelled')
            ->whereHas('bookingDate', function ($q) use ($turf, $toDate, $bDate) {
                $q->where('booking_date', $toDate)
                    ->where('status', '!=', 'Cancelled')
                    ->where('id', '!=', $bDate->id) // Ignore own current session
                    ->whereHas('booking', function ($bq) use ($turf) {
                        $bq->where('turf_id', $turf->id)
                            ->where('status', 'Confirmed');
                    });
            })
            ->pluck('slot_id')
            ->toArray();

        // Locked slots map
        $lockedSlotMap = SlotLock::where('turf_id', $turf->id)
            ->where('lock_date', $toDate)
            ->pluck('reason', 'slot_id')
            ->toArray();

        // Load slots with pivot
        $allSlots = $turf->slots()
            ->with('category')
            ->wherePivot('is_active', true)
            ->get();

        $slotList = $allSlots->map(function ($slot) use ($turf, $toDate, $isToday, $nowTime, $isSameDate, $ownSlotIds, $occupiedSlotIds, $lockedSlotMap) {
            $isPast = ($isToday && $slot->from_time < $nowTime);
            $isLocked = isset($lockedSlotMap[$slot->id]);
            $isOccupied = in_array($slot->id, $occupiedSlotIds);
            $isCurrent = ($isSameDate && in_array($slot->id, $ownSlotIds));

            $price = SlotPricingService::priceForSlot($turf, $slot, $toDate);

            $fromFormatted = date('h:i A', strtotime($slot->from_time));
            $toFormatted = date('h:i A', strtotime($slot->to_time));

            $isUnavailable = ($isOccupied || $isPast || $isLocked);

            return [
                'id' => $slot->id,
                'from_time' => $slot->from_time,
                'to_time' => $slot->to_time,
                'time_label' => "{$fromFormatted} - {$toFormatted}",
                'price' => $price,
                'is_available' => (!$isUnavailable || $isCurrent),
                'is_booked' => $isOccupied,
                'is_locked' => $isLocked,
                'lock_reason' => $lockedSlotMap[$slot->id] ?? null,
                'is_past' => $isPast,
                'is_current_slot' => $isCurrent,
                'category' => $slot->category?->name ?? 'Other',
            ];
        })->sortBy('from_time')->values()->toArray();

        return [
            'slots' => $slotList,
            'reschedules_used' => $bDate->reschedules()->count(),
            'reschedules_allowed' => (int) ($turf->max_reschedules ?? 2),
            'is_reschedule_active' => (bool) ($turf->is_reschedule_active ?? true),
        ];
    }

    /**
     * Preview rescheduling calculations and validations without writing anything.
     */
    public function preview(BookingDate $bDate, string $newDate, array $slotIds): array
    {
        $this->validateEligibility($bDate, $newDate, $slotIds, false);

        $calculation = $this->calculateReschedulePricing($bDate, $newDate, $slotIds);

        return array_merge([
            'success' => true,
            'reschedules_used' => $bDate->reschedules()->count(),
            'reschedules_allowed' => (int) ($bDate->booking->turf->max_reschedules ?? 2),
        ], $calculation);
    }

    /**
     * Execute session rescheduling within an atomic database transaction.
     */
    public function reschedule(
        BookingDate $bDate,
        string $newDate,
        array $slotIds,
        User $by,
        ?string $reason = null
    ): BookingReschedule {
        return DB::transaction(function () use ($bDate, $newDate, $slotIds, $by, $reason) {
            // Re-lock booking date and booking rows
            $lockedBDate = BookingDate::where('id', $bDate->id)->lockForUpdate()->firstOrFail();
            $booking = Booking::where('id', $lockedBDate->booking_id)->lockForUpdate()->firstOrFail();
            $turf = $booking->turf;

            // Re-run validation with lockForUpdate on slots
            $this->validateEligibility($lockedBDate, $newDate, $slotIds, true);

            // Recompute pricing & settlement from locked state
            $calc = $this->calculateReschedulePricing($lockedBDate, $newDate, $slotIds);

            $fromDate = $lockedBDate->booking_date;
            $oldAmount = (float) $lockedBDate->amount;
            $newAmount = $calc['new_amount'];
            $priceDifference = $calc['price_difference'];
            $settlementType = $calc['settlement_type'];
            $refundAmount = $calc['refund_amount'];
            $refundChannel = $calc['refund_channel'];
            $pDate = $calc['pricing_date'];

            $lastRefundId = null;

            // Handle refund inside transaction holding locks
            if ($refundAmount > 0) {
                $lastRefundId = $this->processRescheduleRefund(
                    $booking,
                    $lockedBDate,
                    $refundAmount,
                    $refundChannel,
                    $reason
                );
            }

            // Capture old slot IDs before deleting
            $oldSlotIds = $lockedBDate->bookingSlots()->pluck('slot_id')->toArray();

            // Delete old slot rows (avoids ghost slots in invoices, reports, cancellation fee calculators)
            $lockedBDate->bookingSlots()->delete();

            // Insert new active slot rows
            foreach ($slotIds as $slotId) {
                $lockedBDate->bookingSlots()->create([
                    'slot_id' => $slotId,
                    'status' => 'active',
                ]);
            }

            // Update BookingDate amounts and new date
            $lockedBDate->update([
                'booking_date' => $newDate,
                'actual_amount' => $pDate['actual_amount'] ?? $calc['new_subtotal'],
                'taxable_amount' => $pDate['taxable_amount'] ?? 0.00,
                'turf_gst_amount' => $pDate['turf_gst_amount'] ?? 0.00,
                'turf_cgst_amount' => $pDate['turf_cgst_amount'] ?? 0.00,
                'turf_sgst_amount' => $pDate['turf_sgst_amount'] ?? 0.00,
                'amount' => $newAmount,
                'turf_payout_amount' => round((float) $lockedBDate->turf_payout_amount + $priceDifference, 2),
            ]);

            // Refresh parent booking and recompute totals across non-cancelled dates
            $nonCancelledDates = $booking->bookingDates()->where('status', '!=', 'Cancelled')->get();

            $booking->update([
                'date_of_booking' => $nonCancelledDates->first()?->booking_date ?? $newDate,
                'actual_amount' => round($nonCancelledDates->sum('actual_amount'), 2),
                'coupon_discount' => round($nonCancelledDates->sum('coupon_discount'), 2),
                'additional_discount' => round($nonCancelledDates->sum('additional_discount'), 2),
                'taxable_amount' => round($nonCancelledDates->sum('taxable_amount'), 2),
                'turf_gst_amount' => round($nonCancelledDates->sum('turf_gst_amount'), 2),
                'turf_cgst_amount' => round($nonCancelledDates->sum('turf_cgst_amount'), 2),
                'turf_sgst_amount' => round($nonCancelledDates->sum('turf_sgst_amount'), 2),
                'total_amount' => round($nonCancelledDates->sum('amount') + (float) $booking->platform_fee + (float) $booking->platform_fee_gst, 2),
                'turf_payout_amount' => round((float) $booking->turf_payout_amount + $priceDifference, 2),
            ]);

            // Recalculate payment status, paid amount, balance using net paid
            (new BookingPaymentDistributor())->recalculatePaymentStatus($booking);

            // Audit record in booking_reschedules
            $reschedule = BookingReschedule::create([
                'booking_id' => $booking->id,
                'booking_date_id' => $lockedBDate->id,
                'from_date' => $fromDate,
                'to_date' => $newDate,
                'from_slot_ids' => $oldSlotIds,
                'to_slot_ids' => array_values($slotIds),
                'old_amount' => $oldAmount,
                'new_amount' => $newAmount,
                'price_difference' => $priceDifference,
                'settlement_type' => $settlementType,
                'refund_amount' => $refundAmount,
                'refund_channel' => $refundChannel,
                'razorpay_refund_id' => $lastRefundId,
                'reason' => $reason,
                'rescheduled_by_user_id' => $by->id,
            ]);

            // Trigger notification after commit
            DB::afterCommit(function () use ($booking, $fromDate, $newDate) {
                try {
                    NotificationService::notifyBookingRescheduled($booking, $fromDate, $newDate);
                } catch (\Throwable $e) {
                    Log::error("Failed to send reschedule notification: " . $e->getMessage());
                }
            });

            return $reschedule;
        });
    }

    /**
     * Common validation logic for preview and execution.
     */
    protected function validateEligibility(BookingDate $bDate, string $newDate, array $slotIds, bool $lockForUpdate = false): void
    {
        $booking = $bDate->booking;
        $turf = $booking->turf;

        // 1. Rescheduling active setting
        if (!($turf->is_reschedule_active ?? true)) {
            throw ValidationException::withMessages([
                'reschedule' => 'Rescheduling is currently disabled for this venue.',
            ]);
        }

        // 2. Booking and Date status
        if ($bDate->status === 'Cancelled') {
            throw ValidationException::withMessages([
                'booking_date' => 'Cannot reschedule a cancelled booking date.',
            ]);
        }

        if ($booking->status !== 'Confirmed') {
            throw ValidationException::withMessages([
                'booking' => 'Only confirmed bookings can be rescheduled.',
            ]);
        }

        // 3. Max reschedules limit
        $maxAllowed = (int) ($turf->max_reschedules ?? 2);
        if ($bDate->reschedules()->count() >= $maxAllowed) {
            throw ValidationException::withMessages([
                'reschedule_limit' => "Maximum reschedules reached for this session ({$maxAllowed}).",
            ]);
        }

        // 4. Current session hasn't started yet
        $earliestSlotTime = $bDate->bookingSlots()
            ->with('slot')
            ->get()
            ->map(fn($bs) => $bs->slot?->from_time)
            ->filter()
            ->sort()
            ->first() ?? '00:00:00';

        $sessionStart = Carbon::parse($bDate->booking_date . ' ' . $earliestSlotTime, 'Asia/Kolkata');
        $now = Carbon::now('Asia/Kolkata');

        if ($now->gte($sessionStart)) {
            throw ValidationException::withMessages([
                'session_started' => 'Cannot reschedule a session that has already started or passed.',
            ]);
        }

        // 5. Target date today or later
        $today = Carbon::today('Asia/Kolkata')->toDateString();
        if ($newDate < $today) {
            throw ValidationException::withMessages([
                'new_date' => 'Target date cannot be in the past.',
            ]);
        }

        // 6. Slots non-empty and minimum consecutive slot requirement
        if (empty($slotIds)) {
            throw ValidationException::withMessages([
                'slot_ids' => 'Please select at least one target slot.',
            ]);
        }

        // Verify slots belong to the turf and are active
        $targetSlots = $turf->slots()
            ->whereIn('slots.id', $slotIds)
            ->wherePivot('is_active', true)
            ->get();

        if ($targetSlots->count() !== count($slotIds)) {
            throw ValidationException::withMessages([
                'slot_ids' => 'One or more selected slots are invalid for this venue.',
            ]);
        }

        // Target slots cannot be past if newDate is today
        if ($newDate === $today) {
            $nowTime = Carbon::now('Asia/Kolkata')->toTimeString();
            foreach ($targetSlots as $tSlot) {
                if ($tSlot->from_time < $nowTime) {
                    throw ValidationException::withMessages([
                        'slot_ids' => 'Cannot reschedule to a slot that has already passed today.',
                    ]);
                }
            }
        }

        // Minimum consecutive slots check
        $allTurfSlots = $turf->slots()->orderBy('from_time')->pluck('slots.id')->toArray();
        $slotIndices = [];
        foreach ($slotIds as $sId) {
            $idx = array_search($sId, $allTurfSlots);
            if ($idx !== false) {
                $slotIndices[] = $idx;
            }
        }
        sort($slotIndices);
        $minSlots = (int) ($turf->min_slots_per_booking ?? 1);
        if ($minSlots > 1) {
            $segments = [];
            $currSegment = [$slotIndices[0]];
            for ($i = 1; $i < count($slotIndices); $i++) {
                if ($slotIndices[$i] === $slotIndices[$i - 1] + 1) {
                    $currSegment[] = $slotIndices[$i];
                } else {
                    $segments[] = $currSegment;
                    $currSegment = [$slotIndices[$i]];
                }
            }
            $segments[] = $currSegment;
            foreach ($segments as $segment) {
                if (count($segment) < $minSlots) {
                    throw ValidationException::withMessages([
                        'slot_ids' => "Each consecutive block of selected slots must contain at least {$minSlots} slots.",
                    ]);
                }
            }
        }

        // 7. No-op reschedule check
        $currentSlotIds = $bDate->bookingSlots()->pluck('slot_id')->toArray();
        sort($currentSlotIds);
        $newSortedSlotIds = $slotIds;
        sort($newSortedSlotIds);

        if ($newDate === $bDate->booking_date && $currentSlotIds === $newSortedSlotIds) {
            throw ValidationException::withMessages([
                'slot_ids' => 'Target session is identical to the current session.',
            ]);
        }

        // 8. Lock and conflict checks
        $lockQuery = SlotLock::where('turf_id', $turf->id)
            ->whereIn('slot_id', $slotIds)
            ->where('lock_date', $newDate);

        if ($lockForUpdate) {
            $lockQuery->lockForUpdate();
        }

        if ($lockQuery->exists()) {
            throw ValidationException::withMessages([
                'slot_ids' => 'One or more selected slots are locked for maintenance on the target date.',
            ]);
        }

        $conflictQuery = BookingSlot::whereIn('slot_id', $slotIds)
            ->where('status', '!=', 'cancelled')
            ->whereHas('bookingDate', function ($q) use ($turf, $newDate, $bDate) {
                $q->where('booking_date', $newDate)
                    ->where('status', '!=', 'Cancelled')
                    ->where('id', '!=', $bDate->id) // Ignore own current session
                    ->whereHas('booking', function ($bq) use ($turf) {
                        $bq->where('turf_id', $turf->id)
                            ->where('status', 'Confirmed');
                    });
            });

        if ($lockForUpdate) {
            $conflictQuery->lockForUpdate();
        }

        if ($conflictQuery->exists()) {
            throw ValidationException::withMessages([
                'slot_ids' => 'One or more selected slots are already booked by another reservation.',
            ]);
        }
    }

    /**
     * Calculate pricing, differences, and settlement details.
     */
    protected function calculateReschedulePricing(BookingDate $bDate, string $newDate, array $slotIds): array
    {
        $turf = $bDate->booking->turf;

        // Load slots with pivot
        $targetSlots = $turf->slots()
            ->whereIn('slots.id', $slotIds)
            ->wherePivot('is_active', true)
            ->get();

        $newSubtotal = 0.00;
        foreach ($targetSlots as $slot) {
            $newSubtotal += SlotPricingService::priceForSlot($turf, $slot, $newDate);
        }
        $newSubtotal = round($newSubtotal, 2);

        // Carry existing discounts capped at new subtotal
        $couponDiscount = min((float) $bDate->coupon_discount, $newSubtotal);
        $remainingForAdditional = max(0.00, $newSubtotal - $couponDiscount);
        $additionalDiscount = min((float) $bDate->additional_discount, $remainingForAdditional);

        $dateItem = [
            'date' => $newDate,
            'subtotal' => $newSubtotal,
            'coupon_discount' => $couponDiscount,
            'additional_discount' => $additionalDiscount,
            'slots' => $slotIds,
        ];

        $pricingCalc = new BookingPricingCalculator();
        $pricing = $pricingCalc->calculatePricing($turf, [$dateItem], 0, 'offline', 'full');
        $pDate = $pricing['dates'][0];

        $newTurfTotal = (float) $pDate['turf_total'];
        $oldTurfTotal = round((float) $bDate->taxable_amount + (float) $bDate->turf_gst_amount, 2);

        $priceDifference = round($newTurfTotal - $oldTurfTotal, 2);
        $platformShare = max(0.00, round((float) $bDate->amount - $oldTurfTotal, 2));
        $newAmount = round($newTurfTotal + $platformShare, 2);

        // Calculate net paid for this booking date
        $payments = Payment::where('booking_date_id', $bDate->id)->where('status', 'Success')->get();
        $netPaid = (float) $payments->sum(fn($p) => max(0.00, (float) $p->amount - (float) $p->refunded_amount));

        $settlementType = 'none';
        $refundAmount = 0.00;
        $refundChannel = null;
        $newBalanceDue = 0.00;

        if ($newAmount >= $netPaid) {
            $newBalanceDue = round($newAmount - $netPaid, 2);
            $settlementType = ($newBalanceDue > 0 && $priceDifference > 0) ? 'balance_due' : 'none';
        } else {
            $refundAmount = round($netPaid - $newAmount, 2);
            $settlementType = 'refund';

            // Determine refund channel (online gateway if online payments exist, else offline)
            $onlinePaid = (float) $payments->where('payment_method', 'App')->sum(fn($p) => max(0.00, (float) $p->amount - (float) $p->refunded_amount));
            $refundChannel = ($onlinePaid > 0) ? 'online_gateway' : 'offline';
        }

        return [
            'from_date' => $bDate->booking_date,
            'to_date' => $newDate,
            'old_amount' => (float) $bDate->amount,
            'new_amount' => $newAmount,
            'new_subtotal' => $newSubtotal,
            'old_turf_total' => $oldTurfTotal,
            'new_turf_total' => $newTurfTotal,
            'price_difference' => $priceDifference,
            'net_paid' => $netPaid,
            'settlement_type' => $settlementType,
            'refund_amount' => $refundAmount,
            'refund_channel' => $refundChannel,
            'new_balance_due' => $newBalanceDue,
            'pricing_date' => $pDate,
        ];
    }

    /**
     * Process refund for rescheduling.
     */
    protected function processRescheduleRefund(
        Booking $booking,
        BookingDate $bDate,
        float $refundAmount,
        ?string $channel,
        ?string $reason
    ): ?string {
        $successfulPayments = Payment::where('booking_date_id', $bDate->id)
            ->where('status', 'Success')
            ->get();

        if ($successfulPayments->isEmpty()) {
            $successfulPayments = Payment::where('booking_id', $booking->id)
                ->where('status', 'Success')
                ->get();
        }

        // Sort online payments first
        $sortedPayments = $successfulPayments->sortBy(fn($p) => $p->payment_method === 'App' ? 0 : 1);

        $remainingRefund = $refundAmount;
        $lastRefundId = null;
        $now = Carbon::now('Asia/Kolkata');
        $walletOwner = $booking->turf->location->user ?? null;
        $walletService = new WalletService();

        foreach ($sortedPayments as $payment) {
            if ($remainingRefund <= 0) break;

            $refundable = max(0.00, (float) $payment->amount - (float) $payment->refunded_amount);
            if ($refundable <= 0) continue;

            $toRefund = min($remainingRefund, $refundable);
            $isOnline = ($payment->payment_method === 'App');

            if ($isOnline) {
                // Call Razorpay gateway refund helper
                $res = BookingCancellationService::refundSinglePayment($payment, $toRefund);
                if (!$res['success']) {
                    throw ValidationException::withMessages([
                        'gateway_refund' => $res['message'] ?? 'Payment gateway refund failed. Reschedule was not completed.',
                    ]);
                }
                $lastRefundId = $res['razorpay_refund_id'];

                // Wallet handling for online payments:
                if ($walletOwner && $toRefund > 0) {
                    if ($payment->wallet_cleared_at) {
                        // Mature: deduct refund adjustment from wallet
                        $walletService->applyDelta($walletOwner, -round($toRefund, 2), 'refund_adjustment', $payment);
                    }
                    // Adjust pending clearance amount
                    $newPayout = max(0.00, round((float) $payment->turf_payout_amount - $toRefund, 2));
                    $payment->turf_payout_amount = $newPayout;
                }

                $newRefunded = round((float) $payment->refunded_amount + $toRefund, 2);
                $payment->update([
                    'refunded_amount' => $newRefunded,
                    'refund_status' => ($newRefunded >= (float) $payment->amount) ? 'Refunded' : 'Partial Refund',
                    'refunded_at' => $now,
                    'refund_notes' => trim(($payment->refund_notes ? $payment->refund_notes . "\n" : '') . "Reschedule online refund: ₹{$toRefund}" . ($reason ? " ({$reason})" : '')),
                ]);
            } else {
                // Cash / offline refund: DO NOT touch wallet
                $newRefunded = round((float) $payment->refunded_amount + $toRefund, 2);
                $payment->update([
                    'refunded_amount' => $newRefunded,
                    'refund_status' => 'Cash / Offline Refund',
                    'refunded_at' => $now,
                    'refund_notes' => trim(($payment->refund_notes ? $payment->refund_notes . "\n" : '') . "Reschedule cash refund: ₹{$toRefund}" . ($reason ? " ({$reason})" : '')),
                ]);
            }

            $remainingRefund = round($remainingRefund - $toRefund, 2);
        }

        return $lastRefundId;
    }
}
