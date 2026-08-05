<?php

namespace App\Services;

use App\Models\SaasSetting;
use App\Models\User;

class CommissionCalculator
{
    /**
     * Calculate per-payment commission rate, commission amount, cash held, and net turf payout contribution.
     *
     * @param User $turfAdminOwner
     * @param string $paymentMethod ('App', 'Cash', 'UPI', 'Other')
     * @param float $amount
     * @return array
     */
    public function calculate(User $turfAdminOwner, string $paymentMethod, float $amount): array
    {
        $saas = SaasSetting::first();
        $effectiveRate = $turfAdminOwner->commission_percentage;
        $gatewayPct = (float) ($saas?->payment_gateway_percentage ?? 2.00);

        // Deduct payment gateway percentage for offline payments
        $rate = $paymentMethod === 'App'
            ? $effectiveRate
            : max(0.00, $effectiveRate - $gatewayPct);

        $commissionAmount = round($amount * $rate / 100, 2);
        $cashHeldAmount   = $paymentMethod === 'App' ? $amount : 0.00;
        $turfPayoutAmount = round($cashHeldAmount - $commissionAmount, 2); // Can be negative for offline payments

        return [
            'rate' => $rate,
            'commission_percentage' => $rate,
            'commissionAmount' => $commissionAmount,
            'commission_amount' => $commissionAmount,
            'cashHeldAmount' => $cashHeldAmount,
            'cash_held_amount' => $cashHeldAmount,
            'turfPayoutAmount' => $turfPayoutAmount,
            'turf_payout_amount' => $turfPayoutAmount,
        ];
    }
}

