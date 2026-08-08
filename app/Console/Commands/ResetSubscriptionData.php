<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetSubscriptionData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:reset {--force : Force the operation to run without confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset and truncate all turf subscriptions and subscription payments data.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('force') && !$this->confirm('Are you sure you want to delete all subscription data? This action cannot be undone.')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        Schema::disableForeignKeyConstraints();
        DB::table('turf_subscriptions')->truncate();
        DB::table('subscription_payments')->truncate();
        Schema::enableForeignKeyConstraints();

        $this->info('All subscription data has been successfully reset.');
        return 0;
    }
}
