<?php

namespace App\Services;

use App\Models\SaasSetting;
use App\Models\Turf;

class CommissionCalculator
{
    /**
     * Calculate per-payment commission rate, commission amount, GST breakdown, cash held, and net turf payout contribution.
     *
     * @param Turf $turf
     * @param string $paymentMethod ('App', 'Cash', 'UPI', 'Other')
     * @param float $amount
     * @return array
     */
    public function calculate(Turf $turf, string $paymentMethod, float $amount): array
    {
        $rate = (float) $turf->commission_percentage;

        // $commissionTotal is the GST-INCLUSIVE total commission owed by the turf owner:
        // rate% of the taxable base. This figure must NOT change based on whether SaaS GST
        // billing is toggled on/off -- only how it splits into base + GST changes.
        $commissionTotal = round($amount * $rate / 100, 2);

        $saas = SaasSetting::first();
        $isSaasGstActive = $saas ? (bool) $saas->is_gst_billing_active : false;
        $commissionGstRate = $isSaasGstActive && $saas ? (float) ($saas->commission_gst_percentage ?? 18.00) : 0.00;

        $commissionAmount = $commissionTotal;
        $commissionGstAmount = 0.00;
        $commissionCgstAmount = 0.00;
        $commissionSgstAmount = 0.00;
        $commissionIgstAmount = 0.00;

        if ($isSaasGstActive && $commissionTotal > 0 && $commissionGstRate > 0) {
            // Extract GST from within the GST-inclusive total (mirrors BookingPricingCalculator's
            // turf-GST 'included' extraction pattern), instead of adding GST on top of it.
            $commissionAmount = round($commissionTotal / (1 + ($commissionGstRate / 100)), 2);
            $commissionGstAmount = round($commissionTotal - $commissionAmount, 2);

            $saasState = trim((string) ($saas->state_code ?? '27'));
            $turfSetting = $turf->setting ?? $turf->turfSetting;
            $turfState = trim((string) ($turfSetting?->state_code ?? $saasState));

            if ($saasState === '' || $turfState === '' || $saasState === $turfState) {
                // Intra-State (CGST + SGST) with 1-paisa rounding guardrail
                $commissionCgstAmount = round($commissionGstAmount / 2, 2);
                $commissionSgstAmount = round($commissionGstAmount - $commissionCgstAmount, 2);
                $commissionIgstAmount = 0.00;
            } else {
                // Inter-State (IGST)
                $commissionIgstAmount = $commissionGstAmount;
                $commissionCgstAmount = 0.00;
                $commissionSgstAmount = 0.00;
            }
        }

        $cashHeldAmount   = $paymentMethod === 'App' ? $amount : 0.00;
        // Total deduction from turf = commission base + commission GST, which by construction
        // always equals $commissionTotal (rate% of taxable base), regardless of GST toggle.
        $totalCommissionDeduction = round($commissionAmount + $commissionGstAmount, 2);
        $turfPayoutAmount = round($cashHeldAmount - $totalCommissionDeduction, 2);

        return [
            'rate' => $rate,
            'commission_percentage' => $rate,
            'commission_rate' => $rate,
            'commissionAmount' => $commissionAmount,
            'commission_amount' => $commissionAmount,
            'commission_gst_rate' => $commissionGstRate,
            'commission_gst_amount' => $commissionGstAmount,
            'commission_cgst_amount' => $commissionCgstAmount,
            'commission_sgst_amount' => $commissionSgstAmount,
            'commission_igst_amount' => $commissionIgstAmount,
            'total_commission_deduction' => $totalCommissionDeduction,
            'cashHeldAmount' => $cashHeldAmount,
            'cash_held_amount' => $cashHeldAmount,
            'turfPayoutAmount' => $turfPayoutAmount,
            'turf_payout_amount' => $turfPayoutAmount,
        ];
    }
}
