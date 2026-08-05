<?php

namespace App\Console\Commands;

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
    protected $signature = 'wallet:reset {--force : Force the operation to run without confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset all wallet balances, due tracking dates, wallet transaction logs, payouts, settlements, and webhooks.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('force') && !$this->confirm('Are you sure you want to reset all user wallet balances, payouts, and transaction logs? This action cannot be undone.')) {
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
}
