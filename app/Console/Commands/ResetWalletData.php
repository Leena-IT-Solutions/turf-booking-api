<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\CommissionSettlement;
use App\Models\CommissionWalletTransaction;
use App\Models\Payment;
use App\Models\Turf;
use App\Models\TurfPayout;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetWalletData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:reset
        {--user= : Optional User ID to reset wallet, passbook entries, settlements, and payouts for only that user}
        {--force : Force the operation to run without confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset wallet balances, due tracking dates, wallet transaction logs, payouts, settlements, and webhooks (globally or for a single --user=ID).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->option('user');
        if ($userId !== null && $userId !== '') {
            return $this->handleSingleUser((int) $userId);
        }

        if (! $this->option('force') && ! $this->confirm('Are you sure you want to reset all user wallet balances, payouts, and transaction logs? This action cannot be undone.')) {
            $this->info('Operation cancelled.');

            return 0;
        }

        Schema::disableForeignKeyConstraints();

        // 1. Truncate transaction log and payout tables
        DB::table('commission_wallet_transactions')->truncate();
        DB::table('turf_payouts')->truncate();

        if (Schema::hasTable('payout_webhooks')) {
            DB::table('payout_webhooks')->truncate();
        }

        if (Schema::hasTable('commission_settlements')) {
            DB::table('commission_settlements')->truncate();
        }

        // 2. Reset user wallet balances & due timestamps to zero/null
        DB::table('users')->update([
            'commission_wallet_balance' => 0.00,
            'commission_due_since' => null,
        ]);

        // 3. Clear cleared timestamp on payments table if present
        if (Schema::hasColumn('payments', 'wallet_cleared_at')) {
            DB::table('payments')->update([
                'wallet_cleared_at' => null,
            ]);
        }

        Schema::enableForeignKeyConstraints();

        $this->info('All wallet balances, transactions, payouts, and settlements have been successfully reset.');

        return 0;
    }

    protected function handleSingleUser(int $userId): int
    {
        $user = User::find($userId);
        if (! $user) {
            $this->error("User #{$userId} not found.");

            return 1;
        }

        if (! $this->option('force') && ! $this->confirm("Reset wallet balance, passbook transactions, settlements, and payouts for {$user->name} (#{$user->id})?")) {
            $this->info('Operation cancelled.');

            return 0;
        }

        static::purgeUserWalletAndPayouts($user);

        $this->info("Reset wallet, passbook entries, settlements, and payouts for {$user->name} (#{$user->id}) back to ₹0.00.");

        return 0;
    }

    /**
     * Delete all wallet transactions, payouts, and settlements for a single user
     * and reset their wallet balance back to ₹0.00.
     */
    public static function purgeUserWalletAndPayouts(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $txCount = CommissionWalletTransaction::where('user_id', $user->id)->delete();
            $payoutCount = TurfPayout::where('user_id', $user->id)->delete();
            $settlementCount = Schema::hasTable('commission_settlements')
                ? CommissionSettlement::where('user_id', $user->id)->delete()
                : 0;

            if (Schema::hasColumn('payments', 'wallet_cleared_at')) {
                $turfIds = Turf::whereHas('location', fn ($q) => $q->where('user_id', $user->id))->pluck('id')->all();
                if (! empty($turfIds)) {
                    $bookingIds = Booking::whereIn('turf_id', $turfIds)->pluck('id')->all();
                    if (! empty($bookingIds)) {
                        Payment::whereIn('booking_id', $bookingIds)->update(['wallet_cleared_at' => null]);
                    }
                }
            }

            $user->update([
                'commission_wallet_balance' => 0.00,
                'commission_due_since' => null,
            ]);

            return [
                'transactions_deleted' => $txCount,
                'payouts_deleted' => $payoutCount,
                'settlements_deleted' => $settlementCount,
            ];
        });
    }
}
