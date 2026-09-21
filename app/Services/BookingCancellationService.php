<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\BookingDate;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\SaasSetting;
use App\Models\Turf;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BookingCancellationService
{
    /**
     * Phase 1: Immediately cancel selected booking dates.
     * Frees slots, updates date/booking status, creates cancellation records
     * with refund_status = 'Pending Resolution'.
     *
     * @param  Booking  $booking
     * @param  array    $bookingDateIds  IDs of BookingDate records to cancel
     * @param  User     $cancelledBy     The user initiating cancellation
     * @param  string   $reason          Cancellation reason
     * @param  bool     $isAdmin         Whether the canceller is turf admin (bypasses cutoff)
     * @return array    ['success' => bool, 'message' => string, 'cancellations' => Collection]
     */
    public function cancelBookingDates(
        Booking $booking,
        array $bookingDateIds,
        User $cancelledBy,
        string $reason = 'Cancellation requested',
        bool $isAdmin = false
    ): array {
        if ($booking->status === 'Cancelled') {
            return ['success' => false, 'message' => 'Booking is already fully cancelled.'];
        }

        $turf = $booking->turf;

        // Customer-initiated: enforce turf cancellation settings
        if (!$isAdmin) {
            if (!$turf || !$turf->is_cancellation_active) {
                return ['success' => false, 'message' => 'Cancellation is not allowed for this turf.'];
            }
        }

        // Get targeted dates
        $targetedDatesQuery = $booking->bookingDates()->where('status', '!=', 'Cancelled');
        if (!empty($bookingDateIds)) {
            $targetedDatesQuery->whereIn('id', $bookingDateIds);
        }
        $targetedDates = $targetedDatesQuery->get();

        if ($targetedDates->isEmpty()) {
            return ['success' => false, 'message' => 'No active booking dates available for cancellation.'];
        }

        // Customer-initiated: enforce cutoff hours
        if (!$isAdmin) {
            $cancellationHours = (int) $turf->cancellation_hours;
            $now = Carbon::now('Asia/Kolkata');

            foreach ($targetedDates as $bDate) {
                $bDate->load(['bookingSlots.slot']);
                $earliestStart = null;

                foreach ($bDate->bookingSlots as $bSlot) {
                    $slot = $bSlot->slot;
                    if ($slot && $slot->from_time) {
                        $dt = Carbon::parse($bDate->booking_date . ' ' . $slot->from_time, 'Asia/Kolkata');
                        if ($earliestStart === null || $dt->lt($earliestStart)) {
                            $earliestStart = $dt;
                        }
                    }
                }

                if ($earliestStart === null) {
                    $earliestStart = Carbon::parse($bDate->booking_date, 'Asia/Kolkata')->startOfDay();
                }

                $diffInHours = $now->diffInHours($earliestStart, false);
                if ($diffInHours < $cancellationHours) {
                    return [
                        'success' => false,
                        'message' => "Cancellation for date {$bDate->booking_date} is only allowed up to {$cancellationHours} hours before session start.",
                    ];
                }
            }
        }

        $cancelledAt = Carbon::now('Asia/Kolkata');
        $saas = SaasSetting::first();
        $cancellationFeePerSlot = (float) ($turf->cancellation_fee ?? 0.00);
        $platformFeePercentage = $saas ? (float) ($saas->cancellation_fee_percentage ?? 5.00) : 0.00;

        $totalBookingPlatformFee = (float) ($booking->platform_fee ?? 0) + (float) ($booking->platform_fee_gst ?? 0);
        $allActiveDatesCount = $booking->bookingDates()->where('status', '!=', 'Cancelled')->count();

        $createdCancellations = [];
        $totalDatesCancelledNow = 0;

        DB::beginTransaction();
        try {
            foreach ($targetedDates as $bDate) {
                // Free slots immediately
                $bDate->bookingSlots()->update(['status' => 'cancelled']);

                $slotCount = $bDate->bookingSlots()->count();
                $datePaidSum = (float) Payment::where('booking_date_id', $bDate->id)
                    ->where('status', 'Success')
                    ->sum('amount');

                if ($datePaidSum <= 0 && $booking->payment_status === 'Paid') {
                    $datePaidSum = (float) $bDate->amount;
                }

                // Calculate standard policy estimates (stored as reference for resolution)
                $datePlatformFee = ($allActiveDatesCount > 0) ? round($totalBookingPlatformFee / $allActiveDatesCount, 2) : 0.00;
                $datePlatformFee = min($datePaidSum, $datePlatformFee);

                $feeBreakdown = (new \App\Services\CancellationFeeCalculator())->calculate($turf, $datePaidSum, $datePlatformFee, $slotCount);
                $saasFee = $feeBreakdown['saas_fee'];
                $turfFee = $feeBreakdown['turf_fee'];
                $standardTotalFee = $feeBreakdown['total_deductions'];
                $standardRefund = $feeBreakdown['refund_amount'];

                // Update BookingDate to Cancelled with Pending Resolution refund status
                $bDate->update([
                    'status' => 'Cancelled',
                    'cancelled_at' => $cancelledAt,
                    'cancellation_fee_applied' => $standardTotalFee,
                    'refund_amount' => $standardRefund,
                    'refund_status' => 'Pending Resolution',
                    'refunded_at' => null,
                ]);

                // Create cancellation audit record
                $cancellation = BookingCancellation::create([
                    'booking_id' => $booking->id,
                    'booking_date_id' => $bDate->id,
                    'cancelled_by_user_id' => $cancelledBy->id,
                    'canceller_role' => $cancelledBy->roles()->pluck('name')->first() ?? 'customer',
                    'cancellation_scope' => (count($targetedDates) === $booking->bookingDates()->count()) ? 'full_booking' : 'date',
                    'reason' => $reason,
                    'gross_cancelled_amount' => $datePaidSum,
                    'turf_cancellation_fee' => $turfFee,
                    'turf_fee_gst_amount' => $feeBreakdown['turf_fee_gst'],
                    'turf_fee_cgst_amount' => $feeBreakdown['turf_fee_cgst'],
                    'turf_fee_sgst_amount' => $feeBreakdown['turf_fee_sgst'],
                    'platform_fee_retained' => $datePlatformFee,
                    'saas_cancellation_fee' => $saasFee,
                    'saas_fee_gst_amount' => $feeBreakdown['saas_fee_gst'],
                    'saas_fee_cgst_amount' => $feeBreakdown['saas_fee_cgst'],
                    'saas_fee_sgst_amount' => $feeBreakdown['saas_fee_sgst'],
                    'saas_fee_igst_amount' => $feeBreakdown['saas_fee_igst'],
                    'platform_cancellation_fee' => round($saasFee + $datePlatformFee, 2),
                    'total_cancellation_fee' => $standardTotalFee,
                    'refund_amount' => $standardRefund,
                    'refund_status' => 'Pending Resolution',
                    'resolution_mode' => null,
                    'disbursement_channel' => null,
                ]);

                $createdCancellations[] = $cancellation;
                $totalDatesCancelledNow++;
            }

            // Update parent booking status
            $this->syncParentBookingStatus($booking);

            DB::commit();

            // Send notification
            try {
                NotificationService::notifyBookingCancelled($booking->fresh());
            } catch (\Exception $e) {
                Log::warning('Cancellation notification failed: ' . $e->getMessage());
            }

            return [
                'success' => true,
                'message' => "Successfully cancelled {$totalDatesCancelledNow} booking date(s). Slots have been released. Refund is pending resolution.",
                'cancellations' => collect($createdCancellations),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('BookingCancellationService::cancelBookingDates failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Cancellation failed: ' . $e->getMessage()];
        }
    }

    /**
     * Phase 2: Resolve refund for a cancellation record.
     *
     * @param  BookingCancellation  $cancellation
     * @param  string  $resolutionMode       standard_policy|full_compensation|custom|no_refund
     * @param  string  $disbursementChannel  online_gateway|offline
     * @param  User    $resolvedBy
     * @param  float|null  $customAmount     Required when resolutionMode = 'custom'
     * @param  string|null $offlineReference  Reference note for offline settlements
     * @return array   ['success' => bool, 'message' => string]
     */
    public function resolveRefund(
        BookingCancellation $cancellation,
        string $resolutionMode,
        string $disbursementChannel,
        User $resolvedBy,
        ?float $customAmount = null,
        ?string $offlineReference = null
    ): array {
        if (!in_array($cancellation->refund_status, ['Pending Resolution', 'Pending'])) {
            return ['success' => false, 'message' => 'This cancellation has already been resolved.'];
        }

        $grossAmount = (float) $cancellation->gross_cancelled_amount;
        $booking = $cancellation->booking;
        $bDate = $cancellation->bookingDate;

        // Calculate final refund based on resolution mode
        switch ($resolutionMode) {
            case 'full_compensation':
                $finalRefund = $grossAmount;
                $finalFee = 0.00;
                $refundStatusLabel = 'Compensated';
                break;

            case 'standard_policy':
                $finalRefund = (float) $cancellation->refund_amount;
                $finalFee = (float) $cancellation->total_cancellation_fee;
                $refundStatusLabel = 'Refunded';
                break;

            case 'custom':
                if ($customAmount === null || $customAmount < 0) {
                    return ['success' => false, 'message' => 'Custom refund amount is required and must be ≥ 0.'];
                }
                $finalRefund = min($customAmount, $grossAmount);
                $finalFee = max(0.00, round($grossAmount - $finalRefund, 2));
                $refundStatusLabel = 'Refunded';
                break;

            case 'no_refund':
                $finalRefund = 0.00;
                $finalFee = $grossAmount;
                $refundStatusLabel = 'Forfeited';
                break;

            default:
                return ['success' => false, 'message' => 'Invalid resolution mode.'];
        }

        DB::beginTransaction();
        try {
            $razorpayRefundId = null;

            // Process gateway refund if online and there's an amount to refund
            if ($disbursementChannel === 'online_gateway' && $finalRefund > 0) {
                $refundResult = $this->processGatewayRefund($booking, $bDate, $finalRefund);
                if ($refundResult['success']) {
                    $razorpayRefundId = $refundResult['razorpay_refund_id'];
                } else {
                    DB::rollBack();
                    return ['success' => false, 'message' => 'Gateway refund failed: ' . $refundResult['message']];
                }
            }

            // Update payment records
            if ($bDate) {
                $this->updatePaymentRecords($booking, $bDate, $finalRefund, $disbursementChannel, $resolvedBy);
            }

            // Update the cancellation record
            $cancellation->update([
                'refund_amount' => $finalRefund,
                'total_cancellation_fee' => $finalFee,
                'refund_status' => $refundStatusLabel,
                'resolution_mode' => $resolutionMode,
                'disbursement_channel' => $disbursementChannel,
                'offline_reference' => $offlineReference,
                'resolved_by_user_id' => $resolvedBy->id,
                'resolved_at' => Carbon::now('Asia/Kolkata'),
                'razorpay_refund_id' => $razorpayRefundId,
            ]);

            // Update BookingDate refund status
            if ($bDate) {
                $dateRefundStatus = match ($refundStatusLabel) {
                    'Compensated' => 'Compensated',
                    'Forfeited' => 'Forfeited',
                    default => ($disbursementChannel === 'offline') ? 'Cash / Offline Refund' : 'Refunded',
                };

                $bDate->update([
                    'cancellation_fee_applied' => $finalFee,
                    'refund_amount' => $finalRefund,
                    'refund_status' => $dateRefundStatus,
                    'refunded_at' => Carbon::now('Asia/Kolkata'),
                ]);
            }

            // Sync parent booking status
            $this->syncParentBookingStatus($booking);

            DB::commit();

            $modeLabels = [
                'full_compensation' => 'Full Compensation',
                'standard_policy' => 'Standard Policy',
                'custom' => 'Custom Amount',
                'no_refund' => 'No Refund',
            ];

            return [
                'success' => true,
                'message' => "Refund resolved as '{$modeLabels[$resolutionMode]}'. " .
                    ($finalRefund > 0 ? "₹" . number_format($finalRefund, 2) . " refund processed via " . ($disbursementChannel === 'offline' ? 'offline settlement' : 'payment gateway') . "." : "No refund issued."),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('BookingCancellationService::resolveRefund failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Refund resolution failed: ' . $e->getMessage()];
        }
    }

    /**
     * Process Razorpay gateway refund for a booking date.
     */
    private function processGatewayRefund(Booking $booking, ?BookingDate $bDate, float $refundAmount): array
    {
        if (!$bDate) {
            return ['success' => false, 'message' => 'No booking date associated.', 'razorpay_refund_id' => null];
        }

        $saas = SaasSetting::first();
        $razorpayKey = $saas?->razorpay_key ?: config('services.razorpay.key');
        $razorpaySecret = $saas?->razorpay_secret ?: config('services.razorpay.secret');

        if (!$razorpayKey || !$razorpaySecret) {
            return ['success' => false, 'message' => 'Payment gateway keys not configured.', 'razorpay_refund_id' => null];
        }

        $successfulPayments = Payment::where('booking_date_id', $bDate->id)
            ->where('status', 'Success')
            ->where('payment_method', 'App')
            ->get();

        if ($successfulPayments->isEmpty()) {
            // No online payments to refund — treat as offline fallback
            return ['success' => true, 'message' => 'No online payments found. Treat as offline.', 'razorpay_refund_id' => null];
        }

        $remainingToRefund = $refundAmount;
        $lastRefundId = null;

        foreach ($successfulPayments as $payment) {
            if ($remainingToRefund <= 0) break;

            $gateway = PaymentGateway::where('payment_id', $payment->id)->first();
            if (!$gateway || $gateway->gateway_name !== 'razorpay' || !$gateway->gateway_payment_id) {
                continue;
            }

            $paymentRefund = min((float) $payment->amount, $remainingToRefund);
            $refundPaise = (int) round($paymentRefund * 100);

            try {
                $paymentId = $gateway->gateway_payment_id;

                // Check if payment needs capture first
                $fetchResponse = Http::withBasicAuth($razorpayKey, $razorpaySecret)
                    ->get("https://api.razorpay.com/v1/payments/{$paymentId}");

                if ($fetchResponse->successful()) {
                    $pData = $fetchResponse->json();
                    if (($pData['status'] ?? '') === 'authorized') {
                        Http::withBasicAuth($razorpayKey, $razorpaySecret)
                            ->asForm()
                            ->post("https://api.razorpay.com/v1/payments/{$paymentId}/capture", [
                                'amount' => $pData['amount'] ?? (int) round((float) $payment->amount * 100),
                                'currency' => $pData['currency'] ?? 'INR',
                            ]);
                    }
                }

                // Issue refund
                $response = Http::withBasicAuth($razorpayKey, $razorpaySecret)
                    ->asForm()
                    ->post("https://api.razorpay.com/v1/payments/{$paymentId}/refund", [
                        'amount' => $refundPaise,
                    ]);

                if ($response->successful()) {
                    $resData = $response->json();
                    $lastRefundId = $resData['id'] ?? null;
                    $gateway->update([
                        'gateway_refund_id' => $lastRefundId,
                        'refund_response_payload' => $resData,
                    ]);
                } else {
                    $errBody = $response->json();
                    $gateway->update(['refund_response_payload' => $errBody]);
                    Log::error('Razorpay refund failed: ' . json_encode($errBody));
                    return ['success' => false, 'message' => $errBody['error']['description'] ?? 'Gateway refund failed.', 'razorpay_refund_id' => null];
                }

                $remainingToRefund -= $paymentRefund;
            } catch (\Exception $e) {
                Log::error('Razorpay refund exception: ' . $e->getMessage());
                return ['success' => false, 'message' => $e->getMessage(), 'razorpay_refund_id' => null];
            }
        }

        return ['success' => true, 'message' => 'Gateway refund processed.', 'razorpay_refund_id' => $lastRefundId];
    }

    /**
     * Update payment records with refund information and handle wallet reversals.
     */
    private function updatePaymentRecords(Booking $booking, BookingDate $bDate, float $finalRefund, string $channel, User $resolvedBy): void
    {
        $successfulPayments = Payment::where('booking_date_id', $bDate->id)
            ->where('status', 'Success')
            ->get();

        $remainingRefund = $finalRefund;
        $cancelledAt = Carbon::now('Asia/Kolkata');

        foreach ($successfulPayments as $payment) {
            if ($remainingRefund <= 0) {
                $payment->update(['refunded_amount' => 0.00, 'refund_status' => 'None']);
                continue;
            }

            $paymentRefund = min((float) $payment->amount, $remainingRefund);
            $isOnline = ($payment->payment_method === 'App');

            $refundStatus = match (true) {
                $channel === 'online_gateway' && $isOnline => 'Refunded',
                $channel === 'offline' => 'Cash / Offline Refund',
                default => 'Cash / Offline Refund',
            };

            $payment->update([
                'refunded_amount' => $paymentRefund,
                'refund_status' => $refundStatus,
                'refunded_at' => $cancelledAt,
            ]);

            // Wallet reversal logic: Platform retains all fees/costs; entire refund is debited from turf owner
            $turfAdminOwner = $booking->turf->location->user ?? null;
            if ($turfAdminOwner && $paymentRefund > 0) {
                if ($payment->wallet_cleared_at) {
                    $reversalAmount = -round($paymentRefund, 2);
                    $walletService = new WalletService();
                    $walletService->applyDelta($turfAdminOwner, $reversalAmount, 'refund_adjustment', $payment);

                    $newPayoutAmount = max(0.00, round(((float) $payment->turf_payout_amount) - $paymentRefund, 2));
                    $payment->update([
                        'turf_payout_amount' => $newPayoutAmount,
                    ]);
                } else {
                    $newPayoutAmount = max(0.00, round(((float) $payment->turf_payout_amount) - $paymentRefund, 2));
                    $payment->update([
                        'turf_payout_amount' => $newPayoutAmount,
                    ]);
                }
            }

            $remainingRefund -= $paymentRefund;
        }
    }

    /**
     * Sync parent booking status based on all booking dates.
     */
    private function syncParentBookingStatus(Booking $booking): void
    {
        $allDates = $booking->bookingDates()->get();
        $totalCount = $allDates->count();
        $cancelledCount = $allDates->where('status', 'Cancelled')->count();

        $parentStatus = match (true) {
            $cancelledCount === $totalCount => 'Cancelled',
            $cancelledCount > 0 => 'Partially Cancelled',
            default => 'Confirmed',
        };

        // Aggregate refund info from cancellation records
        $aggregateFee = (float) $allDates->sum('cancellation_fee_applied');
        $aggregateRefund = (float) $allDates->sum('refund_amount');
        $earliestCancelledAt = $allDates->whereNotNull('cancelled_at')->min('cancelled_at');
        $latestRefundedAt = $allDates->whereNotNull('refunded_at')->max('refunded_at');

        // Determine parent refund status
        $pendingCount = $allDates->where('refund_status', 'Pending Resolution')->count();
        if ($cancelledCount === 0) {
            $parentRefundStatus = 'Not Applicable';
        } elseif ($pendingCount > 0) {
            $parentRefundStatus = 'Pending Resolution';
        } elseif ($aggregateRefund > 0) {
            $hasOnline = $allDates->where('refund_status', 'Refunded')->isNotEmpty();
            $hasCompensated = $allDates->where('refund_status', 'Compensated')->isNotEmpty();
            $parentRefundStatus = $hasCompensated ? 'Compensated' : ($hasOnline ? 'Refunded' : 'Cash / Offline Refund');
        } else {
            $parentRefundStatus = 'Forfeited';
        }

        $booking->update([
            'status' => $parentStatus,
            'cancelled_at' => $earliestCancelledAt,
            'cancellation_fee_applied' => $aggregateFee,
            'refund_amount' => $aggregateRefund,
            'refund_status' => $parentRefundStatus,
            'refunded_at' => $latestRefundedAt,
        ]);
    }
}
