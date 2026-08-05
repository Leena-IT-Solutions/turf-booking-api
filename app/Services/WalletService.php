<?php

namespace App\Services;

use App\Models\CommissionWalletTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Apply signed amount delta to user's wallet balance inside a locked DB transaction
     * and log the audit entry in commission_wallet_transactions.
     *
     * @param User $user
     * @param float $amount Signed delta (+credit / -debit)
     * @param string $type ('payment_settlement', 'payout_debit', 'payout_reversal', 'commission_due_settlement', 'refund_adjustment')
     * @param Model|null $reference
     * @return User Updated user instance
     */
    public function applyDelta(User $user, float $amount, string $type, ?Model $reference = null): User
    {
        return DB::transaction(function () use ($user, $amount, $type, $reference) {
            // Lock user row for update
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();

            $oldBalance = (float) $lockedUser->commission_wallet_balance;
            $newBalance = round($oldBalance + $amount, 2);

            // Manage commission_due_since timestamp
            $dueSince = $lockedUser->commission_due_since;
            if ($newBalance < 0 && $oldBalance >= 0) {
                $dueSince = now();
            } elseif ($newBalance >= 0) {
                $dueSince = null;
            }

            $lockedUser->update([
                'commission_wallet_balance' => $newBalance,
                'commission_due_since' => $dueSince,
            ]);

            // Record immutable audit transaction
            CommissionWalletTransaction::create([
                'user_id' => $lockedUser->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference ? $reference->getKey() : null,
            ]);

            return $lockedUser;
        });
    }
}
