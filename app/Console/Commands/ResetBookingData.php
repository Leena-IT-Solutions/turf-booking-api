<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\CommissionInvoice;
use App\Models\CommissionWalletTransaction;
use App\Models\Payment;
use App\Models\SlotLock;
use App\Models\Turf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetBookingData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:reset
        {--turf= : Optional Turf ID to delete bookings for only that specific turf}
        {--force : Force the operation to run without confirmation prompt}
        {--with-wallet : Also reset wallet balances/transactions (or recalculate owner wallet for --turf)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset bookings, booking dates, booking slots, payments, and related records (globally or for a single --turf=ID).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $turfId = $this->option('turf');
        if ($turfId !== null && $turfId !== '') {
            return $this->handleSingleTurf((int) $turfId);
        }

        if (! $this->option('force') && ! $this->confirm('Are you sure you want to delete all bookings and payment records? This action cannot be undone.')) {
            $this->info('Operation cancelled.');

            return 0;
        }

        $resetWallet = (bool) $this->option('with-wallet');
        if (! $resetWallet && ! $this->option('force')) {
            $resetWallet = $this->confirm('Do you also want to reset wallet balances, payouts, and ledger entries back to ₹0.00?', true);
        }

        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('booking_cancellations')) {
            DB::table('booking_cancellations')->truncate();
        }
        DB::table('payment_gateways')->truncate();
        DB::table('payments')->truncate();
        DB::table('coupon_usages')->truncate();
        DB::table('booking_slots')->truncate();
        DB::table('booking_dates')->truncate();
        DB::table('bookings')->truncate();

        if (Schema::hasTable('slot_locks')) {
            DB::table('slot_locks')->truncate();
        }

        Schema::enableForeignKeyConstraints();

        $this->info('All bookings, booking dates, booking slots, payments, and related records have been successfully reset.');

        if ($resetWallet) {
            $this->call('wallet:reset', ['--force' => true]);
        }

        return 0;
    }

    protected function handleSingleTurf(int $turfId): int
    {
        $turf = Turf::with('location.user')->find($turfId);
        if (! $turf) {
            $this->error("Turf #{$turfId} not found.");

            return 1;
        }

        if (! $this->option('force') && ! $this->confirm("Delete all bookings for turf #{$turf->id} ({$turf->name}) while keeping the turf itself?")) {
            $this->info('Operation cancelled.');

            return 0;
        }

        $withWallet = (bool) $this->option('with-wallet');

        $deletedCount = DB::transaction(function () use ($turf, $withWallet) {
            $owner = $turf->location?->user;
            $bookingIds = Booking::where('turf_id', $turf->id)->pluck('id')->toArray();
            $count = count($bookingIds);

            $paymentIds = ! empty($bookingIds)
                ? Payment::whereIn('booking_id', $bookingIds)->pluck('id')->toArray()
                : [];
            $cancellationIds = ! empty($bookingIds)
                ? BookingCancellation::whereIn('booking_id', $bookingIds)->pluck('id')->toArray()
                : [];

            if ($withWallet && $owner && (! empty($bookingIds) || ! empty($paymentIds) || ! empty($cancellationIds))) {
                $txQuery = CommissionWalletTransaction::where('user_id', $owner->id)
                    ->where(function ($q) use ($bookingIds, $paymentIds, $cancellationIds) {
                        if (! empty($paymentIds)) {
                            $q->orWhere(fn ($sq) => $sq->where('reference_type', Payment::class)->whereIn('reference_id', $paymentIds));
                        }
                        if (! empty($bookingIds)) {
                            $q->orWhere(fn ($sq) => $sq->where('reference_type', Booking::class)->whereIn('reference_id', $bookingIds));
                        }
                        if (! empty($cancellationIds)) {
                            $q->orWhere(fn ($sq) => $sq->where('reference_type', BookingCancellation::class)->whereIn('reference_id', $cancellationIds));
                        }
                    });

                $txIdsToDelete = $txQuery->pluck('id')->toArray();

                foreach (CommissionWalletTransaction::where('user_id', $owner->id)->get(['id', 'meta']) as $tx) {
                    $metaBookingId = $tx->meta['booking_id'] ?? null;
                    if ($metaBookingId && in_array((int) $metaBookingId, array_map('intval', $bookingIds), true)) {
                        $txIdsToDelete[] = $tx->id;
                    }
                }

                $txIdsToDelete = array_values(array_unique($txIdsToDelete));
                if (! empty($txIdsToDelete)) {
                    CommissionWalletTransaction::whereIn('id', $txIdsToDelete)->delete();
                }

                $runningBalance = 0.0;
                foreach (CommissionWalletTransaction::where('user_id', $owner->id)->orderBy('id', 'asc')->get() as $rTx) {
                    $runningBalance = round($runningBalance + (float) $rTx->amount, 2);
                    if ((float) $rTx->balance_after !== $runningBalance) {
                        $rTx->update(['balance_after' => $runningBalance]);
                    }
                }

                $owner->update([
                    'commission_wallet_balance' => $runningBalance,
                    'commission_due_since' => $runningBalance < 0 ? ($owner->commission_due_since ?: now()) : null,
                ]);

                CommissionInvoice::where('turf_id', $turf->id)->delete();
            }

            SlotLock::where('turf_id', $turf->id)->delete();
            Booking::where('turf_id', $turf->id)->delete();

            return $count;
        });

        $this->info("Deleted {$deletedCount} booking(s) for turf #{$turf->id} ({$turf->name}). The turf itself was kept intact.");

        return 0;
    }
}
