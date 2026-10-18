<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ClearMaturedWalletEntries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:clear-matured-entries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear matured future online booking payment contributions into turf admin wallet balances.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $walletService = new WalletService();
        $today = Carbon::today()->format('Y-m-d');

        $pendingPayments = Payment::with(['booking.turf.location.user', 'bookingDate'])
            ->whereNull('wallet_cleared_at')
            ->where('status', 'Success')
            ->where('turf_payout_amount', '>', 0)
            ->whereHas('bookingDate', function ($query) use ($today) {
                $query->where('booking_date', '<=', $today)
                      ->where('status', '!=', 'Cancelled');
            })
            ->get();

        $clearedCount = 0;

        foreach ($pendingPayments as $payment) {
            $owner = $payment->booking?->turf?->location?->user ?? null;
            if ($owner) {
                // Defensive: settle deductions too, in case they somehow didn't post earlier
                // (e.g. no wallet owner existed at payment time). No-ops via the dedup checks
                // if already posted.
                $walletService->settleDeductions($owner, $payment, isOnline: true);
                $walletService->settleCredit($owner, $payment, isOnline: true);
                $clearedCount++;
            }
        }

        // Final balancing, once per booking and only after all regular payouts above have run (so a
        // second matured session's own payout isn't pre-empted). This also releases online money left
        // on cancelled sessions (e.g. the turf's cancellation fee), which the query above deliberately
        // excludes. trueUpBookingCredit() itself waits while any session is still in the future.
        $cancelledSessionBookings = Payment::with(['booking.turf.location.user'])
            ->whereNull('wallet_cleared_at')
            ->where('status', 'Success')
            ->where('payment_method', 'App')
            ->whereHas('bookingDate', fn ($query) => $query->where('status', 'Cancelled'))
            ->get()
            ->pluck('booking');

        $bookingsToBalance = $pendingPayments->pluck('booking')
            ->merge($cancelledSessionBookings)
            ->filter()
            ->unique('id');

        $balancedCount = 0;
        foreach ($bookingsToBalance as $booking) {
            $owner = $booking->turf?->location?->user;
            if ($owner) {
                $walletService->trueUpBookingCredit($owner, $booking);
                $balancedCount++;
            }
        }

        $this->info("Cleared {$clearedCount} matured wallet payment contributions; balanced {$balancedCount} booking(s).");
        return Command::SUCCESS;
    }
}
