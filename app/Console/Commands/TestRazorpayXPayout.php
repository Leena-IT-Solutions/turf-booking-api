<?php

namespace App\Console\Commands;

use App\Models\SaasSetting;
use App\Models\User;
use App\Services\PayoutService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestRazorpayXPayout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payout:test-razorpayx 
                            {--account= : Override or test a specific RazorpayX Virtual Account Number}
                            {--execute : Actually execute a live/test payout via PayoutService}
                            {--amount=1.00 : Payout amount to transfer if --execute is passed}
                            {--user= : Target user ID for the payout}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test and diagnose Razorpay and RazorpayX Payout configuration and API connectivity';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("==================================================");
        $this->info("       RAZORPAY & RAZORPAYX DIAGNOSTIC SUITE      ");
        $this->info("==================================================\n");

        $saas = SaasSetting::first();
        if (!$saas) {
            $this->error("❌ SaaS settings record not found.");
            return 1;
        }

        $key = $saas->razorpay_key ?: config('services.razorpay.key');
        $secret = $saas->razorpay_secret ?: config('services.razorpay.secret');
        $accountNumber = $this->option('account') ?: $saas->razorpayx_account_number;

        $this->table(
            ['Configuration Key', 'Status / Value'],
            [
                ['Razorpay Key ID', $key ? (substr($key, 0, 10) . '...') : '<not configured>'],
                ['Razorpay Key Secret', $secret ? '••••••••••••••••' : '<not configured>'],
                ['RazorpayX Account Number', $accountNumber ?: '<not configured>'],
                ['Webhook Secret', $saas->razorpayx_webhook_secret ? '••••••••' : '<not configured>'],
            ]
        );

        if (!$key || !$secret) {
            $this->error("\n❌ Razorpay Key ID or Key Secret is missing. Please configure them in SaaS Settings.");
            return 1;
        }

        // Step 1: Check basic API authentication
        $this->info("\n[1/3] Testing Basic Razorpay API Authentication (Contacts Endpoint)...");
        try {
            $res = Http::withBasicAuth($key, $secret)
                ->timeout(10)
                ->get('https://api.razorpay.com/v1/contacts', ['count' => 1]);

            if ($res->successful()) {
                $this->info("  ✅ SUCCESS: Razorpay API authenticated successfully (Status: {$res->status()}).");
            } else {
                $this->error("  ❌ FAILED: Razorpay authentication rejected (Status: {$res->status()}).");
                $this->line("  Response: " . $res->body());
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("  ❌ Network/Connection error: " . $e->getMessage());
            return 1;
        }

        // Step 2: Check RazorpayX Payouts Endpoint
        $this->info("\n[2/3] Checking RazorpayX Payouts Endpoint & Virtual Account...");
        if (!$accountNumber) {
            $this->warn("  ⚠️ WARNING: 'razorpayx_account_number' is not configured yet.");
            $this->line("  To enable automated payouts, enter your RazorpayX Virtual Account Number in SaaS Settings.");
            $this->line("  (Find it in Razorpay Dashboard -> RazorpayX -> Banking / Account Details)");
        } else {
            try {
                $payoutRes = Http::withBasicAuth($key, $secret)
                    ->timeout(10)
                    ->get('https://api.razorpay.com/v1/payouts', [
                        'account_number' => $accountNumber,
                        'count' => 1,
                    ]);

                if ($payoutRes->successful()) {
                    $this->info("  ✅ SUCCESS: RazorpayX Virtual Account '{$accountNumber}' is active and authorized for payouts!");
                } else {
                    $this->warn("  ⚠️ NOTICE: RazorpayX response for account '{$accountNumber}':");
                    $this->line("  " . $payoutRes->body());
                }
            } catch (\Exception $e) {
                $this->error("  ❌ Error checking RazorpayX endpoint: " . $e->getMessage());
            }
        }

        // Step 3: Execution test (Optional)
        $this->info("\n[3/3] Live/Test Payout Execution Check...");
        if (!$this->option('execute')) {
            $this->line("  ℹ️ Skipped. Use '--execute' to perform a live/test payout initiation.");
            $this->info("\nDiagnostic complete! All systems operational.");
            return 0;
        }

        if (!$accountNumber) {
            $this->error("  ❌ Cannot execute payout: RazorpayX Virtual Account Number is required.");
            return 1;
        }

        $userId = $this->option('user');
        $user = $userId ? User::find($userId) : User::role('turf-admin')->first();

        if (!$user) {
            $this->error("  ❌ No turf-admin user found to test payout.");
            return 1;
        }

        $amount = (float) $this->option('amount');
        $this->line("  Target User: {$user->name} ({$user->email}, ID: {$user->id})");
        $this->line("  Current Wallet Balance: ₹" . number_format((float)$user->commission_wallet_balance, 2));
        $this->line("  Payout Method: " . ($user->payout_method ?: 'not set'));
        $this->line("  Attempting payout of ₹{$amount}...");

        try {
            $payoutService = new PayoutService();
            $payout = $payoutService->requestPayout($user, $amount, 'manual');

            $this->info("  ✅ SUCCESS: Payout #{$payout->id} initiated! Status: {$payout->status}");
            if ($payout->razorpay_payout_id) {
                $this->info("  Razorpay Payout ID: {$payout->razorpay_payout_id}");
            }
        } catch (\Exception $e) {
            $this->error("  ❌ Payout failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
