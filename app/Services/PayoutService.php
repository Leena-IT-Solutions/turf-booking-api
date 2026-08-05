<?php

namespace App\Services;

use App\Models\SaasSetting;
use App\Models\TurfPayout;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class PayoutService
{
    /**
     * Initiate a payout request for a turf admin owner.
     *
     * @param User $owner
     * @param float $requestedAmount
     * @param string $triggeredBy ('manual' or 'scheduled')
     * @return TurfPayout
     * @throws \Exception
     */
    public function requestPayout(User $owner, float $requestedAmount, string $triggeredBy = 'manual'): TurfPayout
    {
        $walletService = new WalletService();
        $saas = SaasSetting::first();

        $payoutHours = (int) ($saas?->payout_hours ?? 24);
        $payoutCharges = (float) ($saas?->payout_charges ?? 40.00);

        return \DB::transaction(function () use ($owner, $requestedAmount, $triggeredBy, $walletService, $saas, $payoutHours, $payoutCharges) {
            $lockedUser = User::where('id', $owner->id)->lockForUpdate()->first();
            $availableBalance = (float) $lockedUser->commission_wallet_balance;

            if ($availableBalance <= 0) {
                throw new \Exception("Insufficient wallet balance. You currently have a balance/due of ₹" . number_format(abs($availableBalance), 2));
            }

            if ($requestedAmount <= 0 || $requestedAmount > $availableBalance) {
                throw new \Exception("Invalid requested amount. Maximum withdrawable amount is ₹" . number_format($availableBalance, 2));
            }

            // Check payout cooldown
            $lastPayout = TurfPayout::where('user_id', $lockedUser->id)
                ->where('status', 'completed')
                ->latest('processed_at')
                ->first();

            $chargeApplied = 0.00;
            if ($lastPayout && $lastPayout->processed_at) {
                $hoursSince = now()->diffInHours($lastPayout->processed_at);
                if ($hoursSince < $payoutHours) {
                    $chargeApplied = $payoutCharges;
                }
            }

            $netAmount = max(0.00, $requestedAmount - $chargeApplied);
            if ($netAmount <= 0) {
                throw new \Exception("Requested amount ₹{$requestedAmount} is less than or equal to the payout fee ₹{$chargeApplied}.");
            }

            // Ensure bank/UPI details exist
            if (!$lockedUser->payout_method || ($lockedUser->payout_method === 'bank' && (!$lockedUser->bank_account_number || !$lockedUser->bank_ifsc)) || ($lockedUser->payout_method === 'upi' && !$lockedUser->upi_id)) {
                throw new \Exception("Payment details missing. Please save your bank account or UPI details before requesting a payout.");
            }

            $payout = TurfPayout::create([
                'user_id' => $lockedUser->id,
                'requested_amount' => $requestedAmount,
                'charge_applied' => $chargeApplied,
                'net_amount' => $netAmount,
                'status' => 'processing',
                'triggered_by' => $triggeredBy,
                'razorpay_contact_id' => $lockedUser->razorpay_contact_id,
                'razorpay_fund_account_id' => $lockedUser->razorpay_fund_account_id,
            ]);

            // Debit requested amount from wallet
            $walletService->applyDelta($lockedUser, -$requestedAmount, 'payout_debit', $payout);

            // Execute RazorpayX Payout API if configured
            $rzpKey = $saas?->razorpay_key ?: config('services.razorpay.key');
            $rzpSecret = $saas?->razorpay_secret ?: config('services.razorpay.secret');
            $accountNumber = $saas?->razorpayx_account_number;

            if ($rzpKey && $rzpSecret && $accountNumber) {
                try {
                    $this->processRazorpayXPayout($payout, $lockedUser, $rzpKey, $rzpSecret, $accountNumber);
                } catch (\Exception $e) {
                    $payout->update([
                        'status' => 'failed',
                        'failure_reason' => $e->getMessage(),
                    ]);

                    // Reverse the debited amount back to wallet
                    $walletService->applyDelta($lockedUser, $requestedAmount, 'payout_reversal', $payout);
                }
            }

            return $payout;
        });
    }


    private function processRazorpayXPayout(TurfPayout $payout, User $user, string $key, string $secret, string $accountNumber): void
    {
        // 1. Create or reuse Razorpay Contact
        if (!$user->razorpay_contact_id) {
            $contactRes = Http::withBasicAuth($key, $secret)
                ->post('https://api.razorpay.com/v1/contacts', [
                    'name' => $user->name,
                    'email' => $user->email,
                    'contact' => $user->mobile ?? '9999999999',
                    'type' => 'vendor',
                    'reference_id' => 'user_' . $user->id,
                ]);

            if ($contactRes->successful()) {
                $contactId = $contactRes->json('id');
                $user->update(['razorpay_contact_id' => $contactId]);
            } else {
                throw new \Exception("Razorpay Contact creation failed: " . $contactRes->body());
            }
        }

        // 2. Create or reuse Fund Account
        if (!$user->razorpay_fund_account_id) {
            $fundPayload = [
                'contact_id' => $user->razorpay_contact_id,
                'account_type' => $user->payout_method === 'upi' ? 'vpa' : 'bank_account',
            ];

            if ($user->payout_method === 'upi') {
                $fundPayload['vpa'] = [
                    'address' => $user->upi_id,
                ];
            } else {
                $fundPayload['bank_account'] = [
                    'name' => $user->bank_account_name ?: $user->name,
                    'ifsc' => $user->bank_ifsc,
                    'account_number' => $user->bank_account_number,
                ];
            }

            $fundRes = Http::withBasicAuth($key, $secret)
                ->post('https://api.razorpay.com/v1/fund_accounts', $fundPayload);

            if ($fundRes->successful()) {
                $fundAccountId = $fundRes->json('id');
                $user->update(['razorpay_fund_account_id' => $fundAccountId]);
            } else {
                throw new \Exception("Razorpay Fund Account creation failed: " . $fundRes->body());
            }
        }

        // 3. Initiate Payout
        $payoutAmountPaise = (int) round($payout->net_amount * 100);
        $payoutRes = Http::withBasicAuth($key, $secret)
            ->post('https://api.razorpay.com/v1/payouts', [
                'account_number' => $accountNumber,
                'fund_account_id' => $user->razorpay_fund_account_id,
                'amount' => $payoutAmountPaise,
                'currency' => 'INR',
                'mode' => $user->payout_method === 'upi' ? 'UPI' : 'IMPS',
                'purpose' => 'payout',
                'queue_if_low_balance' => true,
                'reference_id' => 'payout_' . $payout->id,
                'notes' => [
                    'payout_id' => $payout->id,
                    'user_id' => $user->id,
                ],
            ]);

        if ($payoutRes->successful()) {
            $payoutData = $payoutRes->json();
            $payout->update([
                'razorpay_contact_id' => $user->razorpay_contact_id,
                'razorpay_fund_account_id' => $user->razorpay_fund_account_id,
                'razorpay_payout_id' => $payoutData['id'] ?? null,
            ]);
        } else {
            throw new \Exception("Razorpay Payout API failed: " . $payoutRes->body());
        }
    }
}
