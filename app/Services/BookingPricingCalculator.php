<?php

namespace App\Services;

use App\Models\SaasSetting;
use App\Models\Turf;
use App\Models\TurfSetting;
use App\Models\User;
use Carbon\Carbon;

class BookingPricingCalculator
{
    protected CommissionCalculator $commissionCalculator;

    public function __construct(?CommissionCalculator $commissionCalculator = null)
    {
        $this->commissionCalculator = $commissionCalculator ?? new CommissionCalculator();
    }

    /**
     * Validate pre-booking guardrails.
     *
     * @param Turf $turf
     * @param User|null $user
     * @param array $dates Array of Y-m-d strings
     * @param array $slotIds Array of slot IDs
     * @param string $paymentMethod
     * @param string|null $paymentOption 'full' or 'part'
     * @return array ['valid' => bool, 'message' => string|null, 'status_code' => int]
     */
    public function validateGuardrails(
        Turf $turf,
        ?User $user,
        array $dates,
        array $slotIds,
        string $paymentMethod = 'App',
        ?string $paymentOption = 'full'
    ): array {
        $saas = SaasSetting::first();
        $isStaffOrAdmin = $user && $user->hasAnyRole(['saas-admin', 'turf-admin', 'manager']);

        // 1. SaaS Maintenance Mode
        if ($saas && $saas->is_maintenance_mode && (!$user || !$user->hasRole('saas-admin'))) {
            return [
                'valid' => false,
                'message' => 'Platform is currently under scheduled maintenance. Please try again shortly.',
                'status_code' => 503,
            ];
        }

        // 2. Customer Booking Open
        if (!$isStaffOrAdmin && isset($turf->is_booking_open) && !$turf->is_booking_open) {
            return [
                'valid' => false,
                'message' => 'Online bookings are temporarily paused for this venue.',
                'status_code' => 422,
            ];
        }

        // 3. Booking Availability Window
        $bookingOpenDays = (int) ($turf->booking_open_days ?? 30);
        if (!$isStaffOrAdmin && $bookingOpenDays > 0) {
            $maxAllowedDate = Carbon::today('Asia/Kolkata')->addDays($bookingOpenDays);
            foreach ($dates as $d) {
                if (Carbon::parse($d, 'Asia/Kolkata')->gt($maxAllowedDate)) {
                    return [
                        'valid' => false,
                        'message' => "Bookings for this venue are only open up to {$bookingOpenDays} days in advance.",
                        'status_code' => 422,
                    ];
                }
            }
        }

        // 4. Minimum Slots Booking
        $minSlots = (int) ($saas?->min_slots_booking ?? 2);
        if (count($slotIds) < $minSlots) {
            return [
                'valid' => false,
                'message' => "You must book a minimum of {$minSlots} slots.",
                'status_code' => 422,
            ];
        }

        // 5. Payment Method & Options Validation for Customer
        if (!$isStaffOrAdmin) {
            if ($paymentMethod === 'App' && isset($turf->is_online_payment_active) && !$turf->is_online_payment_active) {
                return [
                    'valid' => false,
                    'message' => 'Online payment is currently unavailable for this venue.',
                    'status_code' => 422,
                ];
            }

            if (in_array($paymentMethod, ['Cash', 'UPI', 'Other', 'offline']) && isset($turf->is_pay_at_location_active) && !$turf->is_pay_at_location_active) {
                return [
                    'valid' => false,
                    'message' => 'Pay at venue / offline payment is not allowed for this venue.',
                    'status_code' => 422,
                ];
            }

            if ($paymentOption === 'part' && isset($turf->is_part_payment_active) && !$turf->is_part_payment_active) {
                return [
                    'valid' => false,
                    'message' => 'Part payment option is not available for this venue.',
                    'status_code' => 422,
                ];
            }
        }

        return ['valid' => true, 'message' => null, 'status_code' => 200];
    }

    /**
     * Compute full itemized pricing, taxes, commission, part-payments, and cancellation policies.
     *
     * @param Turf $turf
     * @param array $dateItems Array of ['date' => 'Y-m-d', 'subtotal' => float, 'coupon_discount' => float, 'additional_discount' => float, 'slots' => array]
     * @param float $manualDiscount
     * @param string $paymentMethod
     * @param string $paymentOption 'full' or 'part'
     * @return array
     */
    public function calculatePricing(
        Turf $turf,
        array $dateItems,
        float $manualDiscount = 0.00,
        string $paymentMethod = 'App',
        string $paymentOption = 'full'
    ): array {
        if ($paymentMethod === 'razorpay' || $paymentMethod === 'razorpay_full' || $paymentMethod === 'razorpay_part') {
            $paymentMethod = 'App';
        }
        $saas = SaasSetting::first();
        $turfSetting = $turf->setting ?? $turf->turfSetting;

        // Turf GST Settings
        $isTurfGstActive = $turfSetting ? (bool) $turfSetting->is_gst_billing_active : false;
        $turfGstRate = $isTurfGstActive ? (float) ($turfSetting->gst_percentage ?? 0.00) : 0.00;
        $turfGstType = $isTurfGstActive ? ($turfSetting->gst_pricing_type ?? 'included') : 'exempt';
        if ($turfGstRate <= 0) {
            $turfGstType = 'exempt';
        }

        // SaaS Platform Settings
        $isSaasGstActive = $saas ? (bool) $saas->is_gst_billing_active : false;
        $platformFeeBase = $saas ? (float) ($saas->platform_fee ?? 0.00) : 0.00;
        $saasBookingGstRate = $isSaasGstActive && $saas ? (float) ($saas->booking_gst_percentage ?? 18.00) : 0.00;
        $saasCancellationFeePct = $saas ? (float) ($saas->cancellation_fee_percentage ?? 5.00) : 0.00;

        // State code comparison for SaaS Tax (Inter vs Intra state)
        $saasState = trim((string) ($saas?->state_code ?? '27'));
        $turfState = trim((string) ($turfSetting?->state_code ?? $saasState));
        $isSaaSIntraState = ($saasState === '' || $turfState === '' || $saasState === $turfState);

        // Cancellation policy snapshot from Turf
        $isCancellationActive = (bool) ($turf->is_cancellation_active ?? false);
        $cancellationHours = (int) ($turf->cancellation_hours ?? 0);
        $cancellationTurfFee = (float) ($turf->cancellation_fee ?? 0.00);

        // 1. Process each date's slot base and Turf GST
        $processedDates = [];
        $totalTurfTaxable = 0.00;
        $totalTurfGst = 0.00;
        $totalTurfCgst = 0.00;
        $totalTurfSgst = 0.00;
        $totalTurfTotal = 0.00;
        $totalGrossSubtotal = 0.00;
        $totalCouponDiscount = 0.00;
        $totalAdditionalDiscount = 0.00;

        foreach ($dateItems as $item) {
            $subtotal = (float) ($item['subtotal'] ?? 0.00);
            $couponDiscount = (float) ($item['coupon_discount'] ?? 0.00);
            $additionalDiscount = (float) ($item['additional_discount'] ?? 0.00);

            $netSlotBase = max(0.00, round($subtotal - $couponDiscount - $additionalDiscount, 2));

            // Turf GST Calculation (Place of supply is always venue -> Intra-state CGST + SGST only)
            $dateTaxableAmount = $netSlotBase;
            $dateTurfGstAmount = 0.00;
            $dateTurfTotal = $netSlotBase;

            if ($turfGstType === 'included' && $turfGstRate > 0) {
                $dateTaxableAmount = round($netSlotBase / (1 + ($turfGstRate / 100)), 2);
                $dateTurfGstAmount = round($netSlotBase - $dateTaxableAmount, 2);
                $dateTurfTotal = $netSlotBase;
            } elseif ($turfGstType === 'excluded' && $turfGstRate > 0) {
                $dateTaxableAmount = $netSlotBase;
                $dateTurfGstAmount = round($netSlotBase * ($turfGstRate / 100), 2);
                $dateTurfTotal = round($netSlotBase + $dateTurfGstAmount, 2);
            }

            // CGST + SGST with 1-paisa rounding guardrail
            $dateTurfCgst = round($dateTurfGstAmount / 2, 2);
            $dateTurfSgst = round($dateTurfGstAmount - $dateTurfCgst, 2);

            $totalGrossSubtotal += $subtotal;
            $totalCouponDiscount += $couponDiscount;
            $totalAdditionalDiscount += $additionalDiscount;
            $totalTurfTaxable += $dateTaxableAmount;
            $totalTurfGst += $dateTurfGstAmount;
            $totalTurfCgst += $dateTurfCgst;
            $totalTurfSgst += $dateTurfSgst;
            $totalTurfTotal += $dateTurfTotal;

            $processedDates[] = [
                'date' => $item['date'] ?? '',
                'day_name' => $item['day_name'] ?? '',
                'subtotal' => $subtotal,
                'coupon_discount' => $couponDiscount,
                'additional_discount' => $additionalDiscount,
                'discount' => round($couponDiscount + $additionalDiscount, 2),
                'net_amount' => $dateTurfTotal,
                'net_slot_base' => $netSlotBase,
                'taxable_amount' => $dateTaxableAmount,
                'turf_gst_amount' => $dateTurfGstAmount,
                'turf_cgst_amount' => $dateTurfCgst,
                'turf_sgst_amount' => $dateTurfSgst,
                'turf_total' => $dateTurfTotal,
                'slots' => $item['slots'] ?? [],
                'coupon' => $item['coupon'] ?? null,
            ];
        }

        // 2. SaaS Platform Fee & GST Calculation
        $platformFee = $platformFeeBase;
        $platformFeeGst = 0.00;
        $platformFeeCgst = 0.00;
        $platformFeeSgst = 0.00;
        $platformFeeIgst = 0.00;

        if ($platformFee > 0 && $isSaasGstActive && $saasBookingGstRate > 0) {
            $platformFeeGst = round($platformFee * ($saasBookingGstRate / 100), 2);
            if ($isSaaSIntraState) {
                $platformFeeCgst = round($platformFeeGst / 2, 2);
                $platformFeeSgst = round($platformFeeGst - $platformFeeCgst, 2);
            } else {
                $platformFeeIgst = $platformFeeGst;
            }
        }

        // 3. Total Order Amount
        $grandTotalAmount = round($totalTurfTotal + $platformFee + $platformFeeGst, 2);

        // 4. Part Payment vs Full Payment
        $isPartPayment = false;
        $payableNow = $grandTotalAmount;
        $balanceAmount = 0.00;

        $partPaymentActive = (bool) ($turf->is_part_payment_active ?? false);
        if ($partPaymentActive && $paymentOption === 'part' && $grandTotalAmount > 0) {
            $isPartPayment = true;
            $partType = $turf->part_payment_type ?? 'percentage';
            $partVal = (float) ($turf->part_payment_value ?? 0.00);

            if ($partType === 'flat') {
                $payableNow = min($partVal, $grandTotalAmount);
            } else {
                $payableNow = round($grandTotalAmount * ($partVal / 100), 2);
            }
            $balanceAmount = max(0.00, round($grandTotalAmount - $payableNow, 2));
        }

        // 5. Pro-rate Paid Amount and Balance Amount across Dates
        $dateCount = count($processedDates);
        $runningPaidSum = 0.00;
        $runningBalanceSum = 0.00;

        $totalCommissionAmount = 0.00;
        $totalCommissionGst = 0.00;
        $totalCommissionCgst = 0.00;
        $totalCommissionSgst = 0.00;
        $totalCommissionIgst = 0.00;
        $totalTurfPayout = 0.00;
        $totalEstimatedRefund = 0.00;

        foreach ($processedDates as $index => &$pDate) {
            // Allocate paid_amount and balance_amount
            if ($index === $dateCount - 1) {
                $datePaid = round($payableNow - $runningPaidSum, 2);
                $dateBalance = round($balanceAmount - $runningBalanceSum, 2);
            } else {
                $ratio = ($totalTurfTotal > 0) ? ($pDate['turf_total'] / $totalTurfTotal) : (1.0 / $dateCount);
                $datePaid = round($payableNow * $ratio, 2);
                $dateBalance = round($balanceAmount * $ratio, 2);
            }

            $runningPaidSum += $datePaid;
            $runningBalanceSum += $dateBalance;

            // Date Commission Calculation (on net slot base / taxable earnings)
            // Commission base is the date's taxable amount (net turf slot earnings before taxes)
            $commissionBase = $pDate['taxable_amount'];
            $commResult = $this->commissionCalculator->calculate($turf, $paymentMethod, $commissionBase);

            $dateCommRate = $commResult['rate'];
            $dateCommAmount = $commResult['commission_amount'];
            $dateCommGst = $commResult['commission_gst_amount'];
            $dateCommCgst = $commResult['commission_cgst_amount'];
            $dateCommSgst = $commResult['commission_sgst_amount'];
            $dateCommIgst = $commResult['commission_igst_amount'];
            $dateTotalCommDeduction = $commResult['total_commission_deduction'];

            // Cash held for this date = paid amount if App, otherwise 0
            $dateCashHeld = ($paymentMethod === 'App') ? $datePaid : 0.00;
            // Payout contribution = cash held minus commission deduction
            $datePayoutAmount = round($dateCashHeld - $dateTotalCommDeduction, 2);

            // Date Cancellation Policy Snapshot & Prospective Estimated Refund
            $dateTurfFeeSnapshot = min($cancellationTurfFee, $pDate['turf_total']);
            $datePlatformFeeSnapshot = round($pDate['turf_total'] * ($saasCancellationFeePct / 100), 2);
            $dateEstimatedRefund = max(0.00, round($datePaid - $dateTurfFeeSnapshot - $datePlatformFeeSnapshot, 2));

            $pDate['paid_amount'] = $datePaid;
            $pDate['balance_amount'] = $dateBalance;
            $pDate['commission_rate'] = $dateCommRate;
            $pDate['commission_amount'] = $dateCommAmount;
            $pDate['commission_gst_amount'] = $dateCommGst;
            $pDate['commission_cgst_amount'] = $dateCommCgst;
            $pDate['commission_sgst_amount'] = $dateCommSgst;
            $pDate['commission_igst_amount'] = $dateCommIgst;
            $pDate['turf_payout_amount'] = $datePayoutAmount;
            $pDate['cash_held_amount'] = $dateCashHeld;
            $pDate['cancellation_turf_fee'] = $dateTurfFeeSnapshot;
            $pDate['cancellation_platform_fee'] = $datePlatformFeeSnapshot;
            $pDate['estimated_refund_amount'] = $dateEstimatedRefund;

            $totalCommissionAmount += $dateCommAmount;
            $totalCommissionGst += $dateCommGst;
            $totalCommissionCgst += $dateCommCgst;
            $totalCommissionSgst += $dateCommSgst;
            $totalCommissionIgst += $dateCommIgst;
            $totalTurfPayout += $datePayoutAmount;
            $totalEstimatedRefund += $dateEstimatedRefund;
        }
        unset($pDate);

        // Overall prospective order refund
        $orderPlatformFeeRetention = round($grandTotalAmount * ($saasCancellationFeePct / 100), 2);
        $orderTotalRetention = $cancellationTurfFee + $orderPlatformFeeRetention;
        $orderEstimatedRefund = max(0.00, round($payableNow - $orderTotalRetention, 2));

        return [
            // Slot Subtotals & Discounts
            'subtotal' => round($totalGrossSubtotal, 2),
            'coupon_discount' => round($totalCouponDiscount, 2),
            'additional_discount' => round($totalAdditionalDiscount, 2),
            'discount' => round($totalCouponDiscount + $totalAdditionalDiscount, 2),

            // Turf GST Breakdown
            'taxable_amount' => round($totalTurfTaxable, 2),
            'turf_gst_rate' => $turfGstRate,
            'turf_gst_type' => $turfGstType,
            'turf_gst_amount' => round($totalTurfGst, 2),
            'turf_cgst_amount' => round($totalTurfCgst, 2),
            'turf_sgst_amount' => round($totalTurfSgst, 2),
            'turf_total' => round($totalTurfTotal, 2),

            // SaaS Platform Fee Breakdown
            'platform_fee' => round($platformFee, 2),
            'platform_fee_gst_rate' => $saasBookingGstRate,
            'platform_fee_gst' => round($platformFeeGst, 2),
            'platform_fee_cgst' => round($platformFeeCgst, 2),
            'platform_fee_sgst' => round($platformFeeSgst, 2),
            'platform_fee_igst' => round($platformFeeIgst, 2),

            // Totals & Part-Payment
            'total_amount' => $grandTotalAmount,
            'payable_now' => $payableNow,
            'balance_amount' => $balanceAmount,
            'is_part_payment' => $isPartPayment,
            'part_payment_active' => $partPaymentActive,

            // Platform Commission Breakdown
            'commission_rate' => (float) $turf->commission_percentage,
            'commission_amount' => round($totalCommissionAmount, 2),
            'commission_gst_amount' => round($totalCommissionGst, 2),
            'commission_cgst_amount' => round($totalCommissionCgst, 2),
            'commission_sgst_amount' => round($totalCommissionSgst, 2),
            'commission_igst_amount' => round($totalCommissionIgst, 2),
            'turf_payout_amount' => round($totalTurfPayout, 2),

            // Cancellation Policy Snapshot & Estimated Refund
            'is_cancellation_active' => $isCancellationActive,
            'cancellation_hours' => $cancellationHours,
            'cancellation_turf_fee' => $cancellationTurfFee,
            'cancellation_platform_fee_pct' => $saasCancellationFeePct,
            'estimated_refund_amount' => $orderEstimatedRefund,

            // Per-Date Array
            'dates' => $processedDates,
        ];
    }
}
