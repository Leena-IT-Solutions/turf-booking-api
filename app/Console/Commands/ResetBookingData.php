<?php

namespace App\Console\Commands;

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
    protected $signature = 'bookings:reset {--force : Force the operation to run without confirmation prompt} {--with-wallet : Also reset wallet balances, transactions, and payouts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset and truncate all bookings, booking dates, booking slots, payment records, and related booking data.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('force') && !$this->confirm('Are you sure you want to delete all bookings and payment records? This action cannot be undone.')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $resetWallet = (bool) $this->option('with-wallet');
        if (!$resetWallet && !$this->option('force')) {
            $resetWallet = $this->confirm('Do you also want to reset wallet balances, payouts, and ledger entries back to ₹0.00?', true);
        }

        Schema::disableForeignKeyConstraints();

        // Truncate booking children and related tables first
        if (Schema::hasTable('booking_cancellations')) {
            DB::table('booking_cancellations')->truncate();
        }
        DB::table('payment_gateways')->truncate();
        DB::table('payments')->truncate();
        DB::table('coupon_usages')->truncate();
        DB::table('booking_slots')->truncate();
        DB::table('booking_dates')->truncate();
        DB::table('bookings')->truncate();

        // Also reset slot locks if present
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
}
