<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PayoutService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunAutomaticPayouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payouts:run-automatic';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process automated scheduled payouts for turf admins configured for daily/weekly payout schedules.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $payoutService = new PayoutService();
        $todayDayOfWeek = Carbon::now()->dayOfWeek; // 0 = Sunday, 6 = Saturday

        $eligibleUsers = User::where('commission_wallet_balance', '>', 0)
            ->where(function ($query) use ($todayDayOfWeek) {
                $query->where('payout_schedule', 'daily')
                    ->orWhere(function ($q) use ($todayDayOfWeek) {
                        $q->where('payout_schedule', 'weekly')
                            ->where('payout_schedule_day', $todayDayOfWeek);
                    });
            })
            ->get();

        $processedCount = 0;

        foreach ($eligibleUsers as $user) {
            try {
                $payoutService->requestPayout($user, (float)$user->commission_wallet_balance, 'scheduled');
                $processedCount++;
            } catch (\Exception $e) {
                $this->error("Failed auto payout for User #{$user->id}: " . $e->getMessage());
            }
        }

        $this->info("Successfully processed {$processedCount} scheduled payouts.");
        return Command::SUCCESS;
    }
}
