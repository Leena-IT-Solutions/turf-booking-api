<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\CommissionWalletTransaction;
use App\Models\Payment;
use App\Models\Turf;
use App\Models\TurfPayout;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletLedgerService
{
    /**
     * Resolve the owner account for any viewer (e.g. staff manager or owner).
     */
    public function ownerFor(User $viewer): User
    {
        return User::findOrFail($viewer->getOwnerId());
    }

    /**
     * Build query for an owner's wallet transactions, newest first.
     */
    public function query(User $owner, ?string $start = null, ?string $end = null, ?string $group = null, bool $includeZero = false): Builder
    {
        $query = CommissionWalletTransaction::query()
            ->where('user_id', $owner->id);

        if ($start) {
            $startDate = Carbon::parse($start, 'Asia/Kolkata')->startOfDay();
            $query->where('created_at', '>=', $startDate);
        }

        if ($end) {
            $endDate = Carbon::parse($end, 'Asia/Kolkata')->endOfDay();
            $query->where('created_at', '<=', $endDate);
        }

        if ($group && isset(CommissionWalletTransaction::GROUPS[$group])) {
            if ($group === 'other') {
                $knownOther = [];
                foreach (CommissionWalletTransaction::GROUPS as $k => $g) {
                    if ($k !== 'other') {
                        $knownOther = array_merge($knownOther, $g['types']);
                    }
                }
                $query->whereNotIn('type', $knownOther);
            } else {
                $query->whereIn('type', CommissionWalletTransaction::GROUPS[$group]['types']);
            }
        }

        if (!$includeZero) {
            $query->where('amount', '!=', 0);
        }

        return $query
            ->with(['reference' => fn (MorphTo $m) => $m->morphWith([
                Payment::class => ['booking.turf'],
                BookingCancellation::class => ['booking.turf'],
            ])])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');
    }

    /**
     * Compute opening balance, period credits, debits, closing balance and group totals.
     */
    public function summary(User $owner, string $start, string $end): array
    {
        $startDate = Carbon::parse($start, 'Asia/Kolkata')->startOfDay();
        $endDate = Carbon::parse($end, 'Asia/Kolkata')->endOfDay();

        // 1. Opening balance: sum of all amounts before start date
        $openingBalance = (float) CommissionWalletTransaction::where('user_id', $owner->id)
            ->where('created_at', '<', $startDate)
            ->sum('amount');
        $openingBalance = round($openingBalance, 2);

        // 2. Period credits and debits
        $periodCredits = (float) CommissionWalletTransaction::where('user_id', $owner->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('amount', '>', 0)
            ->sum('amount');
        $periodCredits = round($periodCredits, 2);

        $periodDebits = abs((float) CommissionWalletTransaction::where('user_id', $owner->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('amount', '<', 0)
            ->sum('amount'));
        $periodDebits = round($periodDebits, 2);

        // Net period delta
        $periodNet = (float) CommissionWalletTransaction::where('user_id', $owner->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');
        $closingBalance = round($openingBalance + $periodNet, 2);

        // 3. Totals by group
        $byGroup = [];
        foreach (CommissionWalletTransaction::GROUPS as $groupKey => $g) {
            $byGroup[$groupKey] = 0.00;
        }

        $typeTotals = CommissionWalletTransaction::where('user_id', $owner->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('type, sum(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        foreach ($typeTotals as $type => $total) {
            $group = CommissionWalletTransaction::groupOf($type);
            $byGroup[$group] = round(($byGroup[$group] ?? 0.00) + (float) $total, 2);
        }

        // 4. Pending clearance for owner's turfs
        $ownerTurfIds = Turf::whereHas('location', fn ($q) => $q->where('user_id', $owner->id))->pluck('id')->all();
        $walletService = new WalletService();
        $pendingClearance = (float) $walletService->pendingCredits($ownerTurfIds)->sum('amount');

        return [
            'opening_balance' => $openingBalance,
            'period_credits' => $periodCredits,
            'period_debits' => $periodDebits,
            'closing_balance' => $closingBalance,
            'current_balance' => (float) $owner->commission_wallet_balance,
            'by_group' => $byGroup,
            'pending_clearance' => round($pendingClearance, 2),
        ];
    }

    /**
     * Resolve the associated Booking model for a transaction.
     */
    public static function bookingFor(CommissionWalletTransaction $tx): ?Booking
    {
        if ($tx->reference instanceof Payment) {
            return $tx->reference->booking;
        }
        if ($tx->reference instanceof BookingCancellation) {
            return $tx->reference->booking;
        }
        if (!empty($tx->meta['booking_id'])) {
            return Booking::find($tx->meta['booking_id']);
        }
        return null;
    }

    /**
     * Resolve the turf name for a transaction.
     */
    public static function turfNameFor(CommissionWalletTransaction $tx): string
    {
        if ($tx->reference instanceof TurfPayout 
            || in_array($tx->type, ['payout_debit', 'payout_reversal'], true)) {
            return 'All turfs';
        }

        $booking = static::bookingFor($tx);
        if ($booking && $booking->turf) {
            return $booking->turf->name;
        }

        return '—';
    }
}
