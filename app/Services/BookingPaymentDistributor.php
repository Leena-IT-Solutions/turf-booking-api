<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentGateway;
use Carbon\Carbon;

/**
 * Shared payment-distribution logic used by both the mobile API
 * (BookingController) and the turf-admin web dashboard (booking-manager
 * Livewire component) — kept in one place so commission, platform fee,
 * and payout math can't drift between the two entry points.
 */
class BookingPaymentDistributor
{
    /**
     * Distribute a payment amount proportionally across a booking's active
     * dates, charging commission/platform fee and settling the turf owner's
     * wallet as each date's payments land.
     */
    public function distribute(
        $booking,
        float $amountToDistribute,
        string $paymentMethod,
        ?string $razorpayPaymentId = null,
        float $gatewayCharge = 0.00,
        float $gatewayTax = 0.00,
        ?string $razorpayOrderId = null,
        ?string $razorpaySignature = null,
        ?array $responsePayload = null,
        string $paymentStatus = 'Success'
    ): void {
        if ($amountToDistribute <= 0) {
            return;
        }

        $bookingDates = $booking->bookingDates()->where('status', '!=', 'Cancelled')->get();
        if ($bookingDates->isEmpty()) {
            $bookingDates = $booking->bookingDates()->get();
        }
        if ($bookingDates->isEmpty()) {
            return;
        }

        $dateBalances = [];
        $totalRemainingBalance = 0.00;

        foreach ($bookingDates as $bDate) {
            $paidSum = (float) Payment::where('booking_date_id', $bDate->id)->where('status', 'Success')->sum('amount');
            $balance = max(0.00, (float)$bDate->amount - $paidSum);
            $dateBalances[$bDate->id] = $balance;
            $totalRemainingBalance += $balance;
        }

        if ($totalRemainingBalance <= 0) {
            return;
        }

        $actualAmountToDistribute = min($amountToDistribute, $totalRemainingBalance);
        $remainingToDistribute = $actualAmountToDistribute;
        $unpaidDates = $bookingDates->filter(fn($d) => ($dateBalances[$d->id] ?? 0) > 0)->values();
        $count = $unpaidDates->count();

        $turf = $booking->turf;
        $walletOwner = $turf?->location?->user ?? null;
        $commissionCalc = new CommissionCalculator();
        $walletService = new WalletService();

        $allocatedGatewayCharge = 0.00;
        $allocatedGatewayTax = 0.00;
        $totalPlatformFeeWithGst = round((float)$booking->platform_fee + (float)$booking->platform_fee_gst, 2);
        $allocatedPlatformFee = 0.00;

        foreach ($unpaidDates as $index => $bDate) {
            if ($remainingToDistribute <= 0) {
                break;
            }

            if ($index === $count - 1) {
                $paidForDate = round($remainingToDistribute, 2);
                $dateGatewayCharge = round($gatewayCharge - $allocatedGatewayCharge, 2);
                $dateGatewayTax = round($gatewayTax - $allocatedGatewayTax, 2);
                $datePlatformFee = round($totalPlatformFeeWithGst - $allocatedPlatformFee, 2);
            } else {
                $ratio = $dateBalances[$bDate->id] / $totalRemainingBalance;
                $paidForDate = round($actualAmountToDistribute * $ratio, 2);
                $paidForDate = min($paidForDate, $remainingToDistribute);

                $dateGatewayCharge = round($gatewayCharge * $ratio, 2);
                $dateGatewayTax = round($gatewayTax * $ratio, 2);
                $allocatedGatewayCharge += $dateGatewayCharge;
                $allocatedGatewayTax += $dateGatewayTax;

                $datePlatformFee = round($totalPlatformFeeWithGst * ($paidForDate / $actualAmountToDistribute), 2);
                $allocatedPlatformFee += $datePlatformFee;
            }

            if ($paidForDate > 0) {
                // Calculate commission breakdown strictly on turf's taxable earnings, excluding platform fee.
                // Commission for a date is charged ONCE IN FULL, on whichever payment settles that date first
                // (part online, offline, or full) — not re-charged and not prorated on later installments
                // (e.g. the remaining "Pay at Venue" collection), so it must never be charged more than once.
                $dateAlreadyCommissioned = Payment::where('booking_date_id', $bDate->id)
                    ->where('status', 'Success')
                    ->where('commission_amount', '>', 0)
                    ->exists();

                if ($dateAlreadyCommissioned) {
                    $turfTaxableBase = 0.00;
                } elseif ((float)$bDate->taxable_amount > 0) {
                    $turfTaxableBase = (float)$bDate->taxable_amount;
                } else {
                    $turfTaxableBase = $paidForDate;
                }
                $commData = $turf
                    ? $commissionCalc->calculate($turf, $paymentMethod, $turfTaxableBase)
                    : [
                        'rate' => 7.00,
                        'commission_amount' => round($turfTaxableBase * 0.07, 2),
                        'commission_gst_amount' => 0.00,
                        'commission_cgst_amount' => 0.00,
                        'commission_sgst_amount' => 0.00,
                        'commission_igst_amount' => 0.00,
                        'total_commission_deduction' => round($turfTaxableBase * 0.07, 2),
                        'cash_held_amount' => $paymentMethod === 'App' ? $turfTaxableBase : 0.00,
                        'turf_payout_amount' => ($paymentMethod === 'App' ? $turfTaxableBase : 0.00) - round($turfTaxableBase * 0.07, 2),
                    ];

                $turfShareWithGst = round($turfTaxableBase + (float)$bDate->turf_gst_amount, 2);
                $cashHeld = $paymentMethod === 'App' ? min($paidForDate, $turfShareWithGst) : 0.00;
                $dateGatewayTotal = round($dateGatewayCharge + $dateGatewayTax, 2);

                // For offline payment, customer pays turf owner directly. The platform did not collect funds online.
                // Therefore, the SaaS Commission and the Platform Fee must be debited from the turf owner's wallet.
                // For online payment (App), the platform fee was already retained at the gateway level.
                $effectivePlatformFee = $paymentMethod === 'App' ? 0.00 : $datePlatformFee;
                $commDeduction = (float)($commData['total_commission_deduction'] ?? $commData['commission_amount']);
                $totalSaaSCutForDate = round($commDeduction + $effectivePlatformFee, 2);
                $payoutContribution = round($cashHeld - $totalSaaSCutForDate - $dateGatewayTotal, 2);

                $existingPending = Payment::where('booking_date_id', $bDate->id)
                    ->where('status', 'Pending')
                    ->first();

                if ($existingPending && $paymentStatus === 'Success') {
                    $existingPending->update([
                        'payment_method' => $paymentMethod,
                        'amount' => $paidForDate,
                        'status' => 'Success',
                        'paid_at' => Carbon::now(),
                    ]);
                    $payment = $existingPending;
                } else {
                    $payment = Payment::create([
                        'booking_id' => $booking->id,
                        'booking_date_id' => $bDate->id,
                        'payment_method' => $paymentMethod,
                        'amount' => $paidForDate,
                        'commission_percentage' => $commData['rate'],
                        'commission_amount' => $commData['commission_amount'],
                        'commission_gst_amount' => $commData['commission_gst_amount'] ?? 0.00,
                        'commission_cgst_amount' => $commData['commission_cgst_amount'] ?? 0.00,
                        'commission_sgst_amount' => $commData['commission_sgst_amount'] ?? 0.00,
                        'commission_igst_amount' => $commData['commission_igst_amount'] ?? 0.00,
                        'cash_held_amount' => $cashHeld,
                        'turf_payout_amount' => $payoutContribution,
                        'gateway_charge_amount' => $dateGatewayCharge,
                        'gateway_tax_amount' => $dateGatewayTax,
                        'wallet_cleared_at' => null,
                        'status' => $paymentStatus,
                        'paid_at' => $paymentStatus === 'Success' ? Carbon::now() : null,
                    ]);
                }

                // Update this booking date's paid_amount and balance_amount
                $currentPaidForDate = (float) Payment::where('booking_date_id', $bDate->id)
                    ->where('status', 'Success')
                    ->sum('amount');
                $bDate->update([
                    'paid_amount' => $currentPaidForDate,
                    'balance_amount' => max(0.00, round((float)$bDate->amount - $currentPaidForDate, 2)),
                ]);

                // Check wallet clearance logic
                $isBookingMatured = $bDate->booking_date <= Carbon::today()->format('Y-m-d');
                $isNegativeOrZeroContribution = $payoutContribution <= 0;

                if ($walletOwner && ($isBookingMatured || $isNegativeOrZeroContribution)) {
                    $isOnline = ($paymentMethod === 'App');
                    if (!$payment->wallet_cleared_at) {
                        $walletService->settlePaymentWithTraits($walletOwner, $payment, $isOnline);
                    }
                }

                if ($razorpayPaymentId && $paymentMethod === 'App') {
                    PaymentGateway::create([
                        'payment_id' => $payment->id,
                        'gateway_name' => 'razorpay',
                        'gateway_order_id' => $razorpayOrderId ?? ($responsePayload['order_id'] ?? null),
                        'gateway_payment_id' => $razorpayPaymentId,
                        'gateway_signature' => $razorpaySignature,
                        'response_payload' => $responsePayload,
                    ]);
                }

                $remainingToDistribute -= $paidForDate;
            }
        }

        // Update overall booking gateway charge and tax totals
        $booking->update([
            'gateway_charge_amount' => (float) Payment::where('booking_id', $booking->id)->where('status', 'Success')->sum('gateway_charge_amount'),
            'gateway_tax_amount' => (float) Payment::where('booking_id', $booking->id)->where('status', 'Success')->sum('gateway_tax_amount'),
            'turf_payout_amount' => (float) Payment::where('booking_id', $booking->id)->where('status', 'Success')->sum('turf_payout_amount'),
        ]);

        $this->recalculatePaymentStatus($booking);
    }

    /**
     * Recompute per-date and parent-booking payment_status/paid_amount/balance_amount
     * from the booking's actual Success payments.
     */
    public function recalculatePaymentStatus($booking): void
    {
        $booking->load('bookingDates');
        $allDatesPaid = true;
        $anyDatePaid = false;
        $totalBookingAmount = 0.00;

        foreach ($booking->bookingDates as $bDate) {
            $totalBookingAmount += (float)$bDate->amount;
            $datePaidSum = (float) Payment::where('booking_date_id', $bDate->id)->where('status', 'Success')->sum('amount');

            $datePaymentStatus = 'Unpaid';
            if ($bDate->amount > 0 && $datePaidSum >= $bDate->amount) {
                $datePaymentStatus = 'Paid';
                $anyDatePaid = true;
            } elseif ($datePaidSum > 0) {
                $datePaymentStatus = 'Partially Paid';
                $allDatesPaid = false;
                $anyDatePaid = true;
            } else {
                $allDatesPaid = false;
            }

            $bDate->update([
                'payment_status' => $datePaymentStatus,
                'paid_amount' => ($datePaymentStatus === 'Paid' && $datePaidSum < (float)$bDate->amount) ? (float)$bDate->amount : $datePaidSum,
                'balance_amount' => ($datePaymentStatus === 'Paid') ? 0.00 : max(0.00, round((float)$bDate->amount - $datePaidSum, 2)),
            ]);
        }

        $totalPaidSoFar = (float) Payment::where('booking_id', $booking->id)->where('status', 'Success')->sum('amount');
        $parentPaymentStatus = 'Unpaid';
        if ($allDatesPaid && $totalBookingAmount > 0) {
            $parentPaymentStatus = 'Paid';
        } elseif ($anyDatePaid) {
            $parentPaymentStatus = 'Partially Paid';
        }

        $booking->update([
            'payment_status' => $parentPaymentStatus,
            'payable_now' => ($parentPaymentStatus === 'Paid' && $totalPaidSoFar < (float)$booking->total_amount) ? (float)$booking->total_amount : $totalPaidSoFar,
            'balance_amount' => ($parentPaymentStatus === 'Paid') ? 0.00 : max(0.00, round((float)$booking->total_amount - $totalPaidSoFar, 2)),
        ]);
    }
}
