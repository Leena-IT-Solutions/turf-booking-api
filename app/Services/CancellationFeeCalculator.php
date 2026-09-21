<?php

namespace App\Services;

use App\Models\SaasSetting;
use App\Models\Turf;

class CancellationFeeCalculator
{
    /**
     * Compute the turf cancellation fee, SaaS cancellation retention, their GST breakdown,
     * total deductions, and refund amount for one booking date being cancelled.
     *
     * $turfFee and $saasFee below are the TOTAL amounts actually retained (unchanged from the
     * existing formula) -- GST is extracted FROM WITHIN each total for reporting/tax purposes,
     * it is never added on top, so total_deductions / refund_amount are identical to before.
     */
    public function calculate(Turf $turf, float $datePaidAmount, float $datePlatformFee, int $slotCount): array
    {
        $saas = SaasSetting::first();
        $platformFeePercentage = $saas ? (float) ($saas->cancellation_fee_percentage ?? 5.00) : 0.00;
        $cancellationFeePerSlot = (float) ($turf->cancellation_fee ?? 0.00);

        $refundableBase = max(0.00, $datePaidAmount - $datePlatformFee);

        // SaaS cancellation retention (unchanged formula/total)
        $saasFee = round($refundableBase * ($platformFeePercentage / 100), 2);

        // Turf owner cancellation fee (unchanged formula/total)
        $remainingForTurf = max(0.00, $refundableBase - $saasFee);
        $turfFee = min($remainingForTurf, $cancellationFeePerSlot * max(1, $slotCount));

        $totalDeductions = min($datePaidAmount, round($datePlatformFee + $saasFee + $turfFee, 2));
        $refundAmount = max(0.00, round($datePaidAmount - $totalDeductions, 2));

        $turfGst = $this->extractTurfFeeGst($turf, $turfFee);
        $saasGst = $this->extractSaasFeeGst($turf, $saas, $saasFee);

        return [
            'turf_fee' => $turfFee,
            'turf_fee_base' => $turfGst['base'],
            'turf_fee_gst' => $turfGst['gst'],
            'turf_fee_cgst' => $turfGst['cgst'],
            'turf_fee_sgst' => $turfGst['sgst'],
            'saas_fee' => $saasFee,
            'saas_fee_base' => $saasGst['base'],
            'saas_fee_gst' => $saasGst['gst'],
            'saas_fee_cgst' => $saasGst['cgst'],
            'saas_fee_sgst' => $saasGst['sgst'],
            'saas_fee_igst' => $saasGst['igst'],
            'date_platform_fee' => $datePlatformFee,
            'total_deductions' => $totalDeductions,
            'refund_amount' => $refundAmount,
        ];
    }

    /**
     * Extract GST from within the turf's cancellation fee total, using the turf's OWN GST rate
     * (same source as slot pricing). Turf GST is always intra-state (place of supply = venue),
     * matching the existing comment/pattern in BookingPricingCalculator.
     */
    private function extractTurfFeeGst(Turf $turf, float $turfFeeTotal): array
    {
        $turfSetting = $turf->setting ?? $turf->turfSetting;
        $isTurfGstActive = $turfSetting ? (bool) $turfSetting->is_gst_billing_active : false;
        $turfGstRate = $isTurfGstActive ? (float) ($turfSetting->gst_percentage ?? 0.00) : 0.00;

        if ($turfFeeTotal <= 0 || !$isTurfGstActive || $turfGstRate <= 0) {
            return ['base' => $turfFeeTotal, 'gst' => 0.00, 'cgst' => 0.00, 'sgst' => 0.00];
        }

        $base = round($turfFeeTotal / (1 + $turfGstRate / 100), 2);
        $gst = round($turfFeeTotal - $base, 2);
        $cgst = round($gst / 2, 2);
        $sgst = round($gst - $cgst, 2);

        return ['base' => $base, 'gst' => $gst, 'cgst' => $cgst, 'sgst' => $sgst];
    }

    /**
     * Extract GST from within the SaaS cancellation retention total, using the SaaS GST rate
     * (same booking_gst_percentage already used for platform fee in Part 1), with the same
     * inter/intra-state comparison already used for platform fee.
     */
    private function extractSaasFeeGst(Turf $turf, ?SaasSetting $saas, float $saasFeeTotal): array
    {
        $isSaasGstActive = $saas ? (bool) $saas->is_gst_billing_active : false;
        $saasGstRate = $isSaasGstActive && $saas ? (float) ($saas->booking_gst_percentage ?? 18.00) : 0.00;

        if ($saasFeeTotal <= 0 || !$isSaasGstActive || $saasGstRate <= 0) {
            return ['base' => $saasFeeTotal, 'gst' => 0.00, 'cgst' => 0.00, 'sgst' => 0.00, 'igst' => 0.00];
        }

        $base = round($saasFeeTotal / (1 + $saasGstRate / 100), 2);
        $gst = round($saasFeeTotal - $base, 2);

        $saasState = trim((string) ($saas->state_code ?? '27'));
        $turfSetting = $turf->setting ?? $turf->turfSetting;
        $turfState = trim((string) ($turfSetting?->state_code ?? $saasState));

        if ($saasState === '' || $turfState === '' || $saasState === $turfState) {
            $cgst = round($gst / 2, 2);
            $sgst = round($gst - $cgst, 2);
            return ['base' => $base, 'gst' => $gst, 'cgst' => $cgst, 'sgst' => $sgst, 'igst' => 0.00];
        }

        return ['base' => $base, 'gst' => $gst, 'cgst' => 0.00, 'sgst' => 0.00, 'igst' => $gst];
    }
}
