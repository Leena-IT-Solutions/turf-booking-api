<?php

namespace App\Services;

use App\Models\BookingCancellation;
use App\Models\BookingDate;
use App\Models\CommissionWalletTransaction;
use App\Models\Payment;
use App\Models\Turf;
use App\Models\TurfPayout;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * Resolve manageable turf IDs for a user based on optional selected turf ID.
     */
    public static function resolveTurfIds(User $user, ?int $activeTurfId = null): array
    {
        $manageableIds = Turf::manageable($user)->pluck('id')->toArray();
        if ($activeTurfId && in_array($activeTurfId, $manageableIds)) {
            return [$activeTurfId];
        }
        return $manageableIds;
    }

    /**
     * 1. Wallet Passbook Summary (Account-wide, chronological for running balance).
     */
    public function getWalletPassbookSummary(User $user, string $startDate, string $endDate, int $limit = 20): array
    {
        $ownerId = $user->getOwnerId();

        $baseQuery = CommissionWalletTransaction::where('user_id', $ownerId)
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);

        $totalTransactions = (clone $baseQuery)->count();
        $totalCredits = (float) (clone $baseQuery)->where('amount', '>', 0)->sum('amount');
        $totalDebits = (float) abs((clone $baseQuery)->where('amount', '<', 0)->sum('amount'));

        // For on-screen display, get latest chronological or preview
        $transactions = (clone $baseQuery)
            ->orderBy('id', 'asc')
            ->take($limit)
            ->get();

        return [
            'transactions' => $transactions,
            'total_count' => $totalTransactions,
            'total_credits' => $totalCredits,
            'total_debits' => $totalDebits,
            'current_balance' => (float) ($user->commission_wallet_balance ?? 0),
        ];
    }

    /**
     * 2. Turf Earnings Breakdown (Turf-scoped).
     */
    public function getTurfEarningsSummary(User $user, ?int $activeTurfId, string $startDate, string $endDate, int $limit = 20): array
    {
        $turfIds = self::resolveTurfIds($user, $activeTurfId);

        $query = Payment::with(['booking.turf', 'bookingDate'])
            ->whereHas('booking', fn($q) => $q->whereIn('turf_id', $turfIds))
            ->where('status', 'Success')
            ->whereBetween(\DB::raw('DATE(paid_at)'), [$startDate, $endDate]);

        $allPayments = (clone $query)->get();

        $grossRevenue = (float) $allPayments->sum('amount');
        $totalCommission = (float) $allPayments->sum('commission_amount');
        $totalPgCharges = (float) $allPayments->sum('gateway_charge_amount');
        $totalNetPayout = (float) $allPayments->sum('turf_payout_amount');

        // Platform fee is booking-level, not per-payment, so only count it on the one
        // payment that actually settled deductions for its booking -- matches the CSV
        // export's logic and avoids double-counting a booking paid across multiple payments.
        $totalPlatformFee = 0.0;
        foreach ($allPayments as $p) {
            if ($p->deductions_settled_at && $p->booking) {
                $totalPlatformFee += (float) ($p->booking->platform_fee + ($p->booking->platform_fee_gst ?? 0));
            }
        }

        $pendingClearance = (float) Payment::whereHas('booking', fn($q) => $q->whereIn('turf_id', $turfIds))
            ->where('status', 'Success')
            ->whereNull('wallet_cleared_at')
            ->where('turf_payout_amount', '>', 0)
            ->sum('turf_payout_amount');

        $previewPayments = (clone $query)->orderBy('paid_at', 'desc')->take($limit)->get();

        return [
            'payments' => $previewPayments,
            'total_count' => $allPayments->count(),
            'gross_revenue' => $grossRevenue,
            'total_platform_fee' => $totalPlatformFee,
            'total_commission' => $totalCommission,
            'total_pg_charges' => $totalPgCharges,
            'total_net_payout' => $totalNetPayout,
            'pending_clearance' => $pendingClearance,
        ];
    }

    /**
     * 3. Turf GST Report (Turf-scoped Output GST).
     */
    public function getTurfGstSummary(User $user, ?int $activeTurfId, string $startDate, string $endDate, int $limit = 20): array
    {
        $turfIds = self::resolveTurfIds($user, $activeTurfId);

        $query = BookingDate::with(['booking.turf.turfSetting'])
            ->whereHas('booking', fn($q) => $q->whereIn('turf_id', $turfIds))
            ->whereBetween('booking_date', [$startDate, $endDate])
            ->where('status', '!=', 'Cancelled');

        $allDates = (clone $query)->get();

        $totalTaxable = (float) $allDates->sum('taxable_amount');
        $totalCgst = (float) $allDates->sum('turf_cgst_amount');
        $totalSgst = (float) $allDates->sum('turf_sgst_amount');
        $totalGst = (float) $allDates->sum('turf_gst_amount');
        $totalGross = (float) $allDates->sum('amount');

        $previewDates = (clone $query)->orderBy('booking_date', 'desc')->take($limit)->get();

        // Check if single turf selected for header GSTIN display
        $turfGstin = 'Not registered';
        $companyName = null;
        if (count($turfIds) === 1) {
            $turf = Turf::with('turfSetting')->find($turfIds[0]);
            if ($turf?->turfSetting?->is_gst_billing_active && !empty($turf->turfSetting->gst_number)) {
                $turfGstin = $turf->turfSetting->gst_number;
                $companyName = $turf->turfSetting->company_name;
            }
        }

        return [
            'booking_dates' => $previewDates,
            'total_count' => $allDates->count(),
            'total_taxable' => $totalTaxable,
            'total_cgst' => $totalCgst,
            'total_sgst' => $totalSgst,
            'total_gst' => $totalGst,
            'total_gross' => $totalGross,
            'turf_gstin' => $turfGstin,
            'company_name' => $companyName,
            'is_single_turf' => count($turfIds) === 1,
        ];
    }

    /**
     * 4. Cancellation & Refund Report (Turf-scoped).
     */
    public function getCancellationSummary(User $user, ?int $activeTurfId, string $startDate, string $endDate, int $limit = 20): array
    {
        $turfIds = self::resolveTurfIds($user, $activeTurfId);

        $query = BookingCancellation::with(['booking.turf', 'bookingDate', 'cancelledByUser'])
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ])
            ->whereHas('booking', fn($q) => $q->whereIn('turf_id', $turfIds));

        $allCancellations = (clone $query)->get();

        $totalCancelledGross = (float) $allCancellations->sum('gross_cancelled_amount');
        $totalTurfFee = (float) $allCancellations->sum('turf_cancellation_fee');
        $totalSaasFee = (float) $allCancellations->sum('saas_cancellation_fee');
        $totalRefund = (float) $allCancellations->sum('refund_amount');

        $previewCancellations = (clone $query)->orderBy('created_at', 'desc')->take($limit)->get();

        return [
            'cancellations' => $previewCancellations,
            'total_count' => $allCancellations->count(),
            'total_gross' => $totalCancelledGross,
            'total_turf_fee' => $totalTurfFee,
            'total_saas_fee' => $totalSaasFee,
            'total_refund' => $totalRefund,
        ];
    }

    /**
     * 5. Commission & Platform Fee Monthly Summary (Account-wide, driver-agnostic).
     */
    public function getCommissionFeeSummary(User $user, string $startDate, string $endDate): array
    {
        $ownerId = $user->getOwnerId();

        $txs = CommissionWalletTransaction::where('user_id', $ownerId)
            ->whereIn('type', [
                'platform_fee_debit',
                'commission_debit',
                'gateway_charge_debit',
                'payment_credit',
            ])
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ])
            ->get()
            ->groupBy(fn($tx) => $tx->created_at->format('Y-m'));

        $months = [];
        $overallGross = 0.0;
        $overallPlatformFee = 0.0;
        $overallCommission = 0.0;
        $overallPgCharges = 0.0;
        $overallCut = 0.0;

        foreach ($txs as $month => $group) {
            $gross = (float) $group->where('type', 'payment_credit')->sum('amount');
            $platformFee = abs((float) $group->where('type', 'platform_fee_debit')->sum('amount'));
            $commission = abs((float) $group->where('type', 'commission_debit')->sum('amount'));
            $pgCharges = abs((float) $group->where('type', 'gateway_charge_debit')->sum('amount'));
            $totalCut = $platformFee + $commission + $pgCharges;
            $effectiveRate = $gross > 0 ? round(($totalCut / $gross) * 100, 2) : 0.0;

            $overallGross += $gross;
            $overallPlatformFee += $platformFee;
            $overallCommission += $commission;
            $overallPgCharges += $pgCharges;
            $overallCut += $totalCut;

            $months[] = [
                'month' => $month,
                'month_label' => Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                'gross_revenue' => $gross,
                'platform_fee' => $platformFee,
                'commission' => $commission,
                'pg_charges' => $pgCharges,
                'total_cut' => $totalCut,
                'effective_rate' => $effectiveRate,
            ];
        }

        usort($months, fn($a, $b) => strcmp($b['month'], $a['month']));

        $overallEffectiveRate = $overallGross > 0 ? round(($overallCut / $overallGross) * 100, 2) : 0.0;

        return [
            'months' => $months,
            'overall_gross' => $overallGross,
            'overall_platform_fee' => $overallPlatformFee,
            'overall_commission' => $overallCommission,
            'overall_pg_charges' => $overallPgCharges,
            'overall_total_cut' => $overallCut,
            'overall_effective_rate' => $overallEffectiveRate,
        ];
    }

    /**
     * 6. Payout History Summary (Account-wide).
     */
    public function getPayoutHistorySummary(User $user, string $startDate, string $endDate, int $limit = 20): array
    {
        $ownerId = $user->getOwnerId();

        $query = TurfPayout::where('user_id', $ownerId)
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);

        $allPayouts = (clone $query)->get();

        $totalRequested = (float) $allPayouts->sum('requested_amount');
        $totalCharges = (float) $allPayouts->sum('charge_applied');
        $totalNet = (float) $allPayouts->sum('net_amount');
        $successfulNet = (float) $allPayouts->where('status', 'Processed')->sum('net_amount');

        $previewPayouts = (clone $query)->orderBy('created_at', 'desc')->take($limit)->get();

        return [
            'payouts' => $previewPayouts,
            'total_count' => $allPayouts->count(),
            'total_requested' => $totalRequested,
            'total_charges' => $totalCharges,
            'total_net' => $totalNet,
            'successful_net' => $successfulNet,
        ];
    }
}
