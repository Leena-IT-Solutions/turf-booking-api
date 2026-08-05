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
                $query->where('booking_date', '<=', $today);
            })
            ->get();

        $clearedCount = 0;

        foreach ($pendingPayments as $payment) {
            $owner = $payment->booking->turf->location->user ?? null;
            if ($owner) {
                $walletService->applyDelta($owner, (float)$payment->turf_payout_amount, 'payment_settlement', $payment);
                $payment->update(['wallet_cleared_at' => Carbon::now()]);
                $clearedCount++;
            }
        }

        $this->info("Cleared {$clearedCount} matured wallet payment contributions.");
        return Command::SUCCESS;
    }
}
