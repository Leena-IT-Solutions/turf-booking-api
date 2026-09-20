<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\CommissionWalletTransaction;
use App\Services\WalletService;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $walletService = new WalletService();

        $offlinePayments = Payment::with(['booking.turf.location.user', 'bookingDate'])
            ->where('payment_method', '!=', 'App')
            ->where('status', 'Success')
            ->get();

        foreach ($offlinePayments as $payment) {
            $booking = $payment->booking;
            if (!$booking) {
                continue;
            }

            $owner = $booking->turf?->location?->user;
            if (!$owner) {
                continue;
            }

            // Check if this payment or booking was already debited in commission_wallet_transactions
            $alreadyDebited = CommissionWalletTransaction::where('user_id', $owner->id)
                ->where(function ($q) use ($payment, $booking) {
                    $q->where(function ($sub) use ($payment) {
                        $sub->where('reference_type', get_class($payment))
                            ->where('reference_id', $payment->id);
                    })->orWhere(function ($sub) use ($booking) {
                        $sub->where('reference_type', get_class($booking))
                            ->where('reference_id', $booking->id);
                    });
                })->exists();

            if (!$alreadyDebited) {
                $commission = (float)($payment->commission_amount + $payment->commission_gst_amount);
                if ($commission <= 0) {
                    $commission = (float)($booking->commission_amount + $booking->commission_gst_amount);
                }
                $platformFee = (float)($booking->platform_fee + $booking->platform_fee_gst);
                $totalSaaSCut = round($commission + $platformFee, 2);

                if ($totalSaaSCut > 0) {
                    $payment->update([
                        'turf_payout_amount' => -$totalSaaSCut,
                        'wallet_cleared_at' => now(),
                    ]);
                    $walletService->applyDelta($owner, -$totalSaaSCut, 'commission_debit', $payment);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversal needed for ledger sync
    }
};
