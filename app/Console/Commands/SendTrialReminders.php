<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendTrialReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trials:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send push reminders to turf owners whose free trial ends in 3 days or today.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $owners = User::whereHas('roles', fn ($q) => $q->where('name', 'turf-admin'))
            ->whereNotNull('trial_ends_at')
            ->get();

        $count3d = 0;
        $count0d = 0;

        foreach ($owners as $owner) {
            $daysLeft = $owner->trialDaysLeft();

            // 1. Last-day reminder (today / 0-1 days left)
            if ($daysLeft <= 1 && $owner->trial_reminder_0d_sent_at === null) {
                NotificationService::sendToUser(
                    $owner->id,
                    'Last Day of Free Trial',
                    'Your free trial with 0% commission ends today. Subscribe now to continue enjoying 0% commission.',
                    ['type' => 'trial_expiring', 'days_left' => 0]
                );
                $owner->trial_reminder_0d_sent_at = now();
                $owner->save();
                $count0d++;
                continue;
            }

            // 2. 3-day reminder (between 2 and 3 days left)
            if ($daysLeft <= 3 && $daysLeft >= 2 && $owner->trial_reminder_3d_sent_at === null) {
                NotificationService::sendToUser(
                    $owner->id,
                    'Free Trial Ending in 3 Days',
                    "Your free trial with 0% commission ends in {$daysLeft} days. Subscribe to keep 0% commission without interruption.",
                    ['type' => 'trial_expiring', 'days_left' => $daysLeft]
                );
                $owner->trial_reminder_3d_sent_at = now();
                $owner->save();
                $count3d++;
            }
        }

        $this->info("Trial reminders sent: {$count3d} 3-day reminders, {$count0d} last-day reminders.");
        return 0;
    }
}
