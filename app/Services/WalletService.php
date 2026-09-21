<?php

namespace App\Services;

use App\Models\CommissionWalletTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WalletService
{
    protected static ?bool $hasDescription = null;
    protected static ?bool $hasMeta = null;

    /**
     * Ensure database schema has description and meta columns; dynamically migrate if missing.
     */
    protected static function ensureSchemaCompatibility(): void
    {
        if (static::$hasDescription === null) {
            try {
                static::$hasDescription = \Illuminate\Support\Facades\Schema::hasColumn('commission_wallet_transactions', 'description');
                static::$hasMeta = \Illuminate\Support\Facades\Schema::hasColumn('commission_wallet_transactions', 'meta');

                if (!static::$hasDescription) {
                    \Illuminate\Support\Facades\Schema::table('commission_wallet_transactions', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->string('description')->nullable()->after('type');
                    });
                    static::$hasDescription = true;
                }

                if (!static::$hasMeta) {
                    \Illuminate\Support\Facades\Schema::table('commission_wallet_transactions', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->json('meta')->nullable()->after('balance_after');
                    });
                    static::$hasMeta = true;
                }
            } catch (\Throwable $e) {
                static::$hasDescription = static::$hasDescription ?? false;
                static::$hasMeta = static::$hasMeta ?? false;
            }
        }
    }

    /**
     * Apply signed amount delta to user's wallet balance inside a locked DB transaction
     * and log the audit entry in commission_wallet_transactions.
     *
     * @param User $user
     * @param float $amount Signed delta (+credit / -debit)
     * @param string $type
     * @param Model|null $reference
     * @param string|null $description
     * @param array|null $meta
     * @return User Updated user instance
     */

    public function applyDelta(
        User $user,
        float $amount,
        string $type,
        ?Model $reference = null,
        ?string $description = null,
        ?array $meta = null
    ): User {
        static::ensureSchemaCompatibility();

        return DB::transaction(function () use ($user, $amount, $type, $reference, $description, $meta) {
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
            $txData = [
                'user_id' => $lockedUser->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference ? $reference->getKey() : null,
            ];

            if (static::$hasDescription) {
                $txData['description'] = $description;
            }
            if (static::$hasMeta) {
                $txData['meta'] = $meta;
            }

            CommissionWalletTransaction::create($txData);

            return $lockedUser;
        });
    }

    /**
     * Record multiple sequential traits inside a single locked DB transaction,
     * updating the wallet balance step-by-step with rolling balance_after.
     *
     * @param User $user
     * @param array $traits Array of ['type' => string, 'amount' => float, 'description' => ?string, 'meta' => ?array]
     * @param Model|null $reference
     * @return User Updated user instance
     */
    public function recordTraits(User $user, array $traits, ?Model $reference = null): User
    {
        static::ensureSchemaCompatibility();

        return DB::transaction(function () use ($user, $traits, $reference) {
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
            $oldInitialBalance = (float) $lockedUser->commission_wallet_balance;
            $currentBalance = $oldInitialBalance;

            foreach ($traits as $trait) {
                $amount = round((float) ($trait['amount'] ?? 0.00), 2);
                $newBalance = round($currentBalance + $amount, 2);

                $txData = [
                    'user_id' => $lockedUser->id,
                    'type' => $trait['type'],
                    'amount' => $amount,
                    'balance_after' => $newBalance,
                    'reference_type' => $reference ? get_class($reference) : null,
                    'reference_id' => $reference ? $reference->getKey() : null,
                ];

                if (static::$hasDescription) {
                    $txData['description'] = $trait['description'] ?? null;
                }
                if (static::$hasMeta) {
                    $txData['meta'] = $trait['meta'] ?? null;
                }

                CommissionWalletTransaction::create($txData);

                $currentBalance = $newBalance;
            }

            // Manage commission_due_since timestamp based on final balance
            $dueSince = $lockedUser->commission_due_since;
            if ($currentBalance < 0 && $oldInitialBalance >= 0) {
                $dueSince = now();
            } elseif ($currentBalance >= 0) {
                $dueSince = null;
            }

            $lockedUser->update([
                'commission_wallet_balance' => $currentBalance,
                'commission_due_since' => $dueSince,
            ]);

            return $lockedUser;
        });
    }

    /**
     * Log an informational, zero-value ledger entry noting an actual offline collection (amount +
     * method) against a booking whose commission/platform fee were already charged earlier — e.g. a
     * "Pay at Location" booking is debited its commission/fee upfront at creation, before the real
     * collection amount/method is known; when staff later collect it in tranches (Cash then UPI),
     * each tranche needs its own audit-trail note even though the wallet was already settled.
     * Does NOT touch commission/fee dedup or the payout figures — purely a ₹0 audit-trail record.
     */
    public function recordOfflineCollectionNote(User $user, Payment $payment): User
    {
        $booking = $payment->booking;
        $bookingDisplayId = $booking ? ($booking->booking_id ?? $booking->id) : $payment->id;

        return $this->recordTraits($user, [[
            'type' => 'offline_booking_record',
            'amount' => 0.00,
            'description' => "Booking #{$bookingDisplayId} Pay at Venue (₹" . number_format((float)$payment->amount, 2) . " collected at venue)",
            'meta' => [
                'booking_id' => $booking?->id,
                'payment_id' => $payment->id,
                'cash_amount' => (float)$payment->amount,
                'payment_method' => $payment->payment_method,
            ],
        ]], $payment);
    }

    /**
     * Settle a payment with itemized money traits:
     * - Online: Credit gross paid amount, debit full one-time platform fee, debit full one-time commission, debit PG charges.
     * - Offline: Record 0 cash at venue, debit full one-time platform fee, debit full one-time commission.
     * Commission and Platform Fee are charged ONE TIME upfront (not prorated on part payments).
     *
     * @param User $user
     * @param Payment $payment
     * @param bool $isOnline
     * @return User
     */
    /**
     * Phase 1: post the SaaS's non-refundable deductions (platform fee, commission, PG charges)
     * to the turf owner's wallet. Fires the moment a payment succeeds, independent of booking-date
     * maturity -- these charges are earned immediately and are never contingent on the booking
     * actually happening (see BookingCancellationService reversal logic: cancelling a booking never
     * reverses these, by design -- the platform retains its cut regardless of refunds).
     */
    public function settleDeductions(User $user, Payment $payment, bool $isOnline): User
    {
        if ($payment->deductions_settled_at) {
            return $user;
        }

        $booking = $payment->booking;
        $bookingPaymentIds = $booking ? Payment::where('booking_id', $booking->id)->pluck('id')->toArray() : [$payment->id];

        $feeAlreadyDebited = CommissionWalletTransaction::where('user_id', $user->id)
            ->where('type', 'platform_fee_debit')
            ->where('reference_type', Payment::class)
            ->whereIn('reference_id', $bookingPaymentIds)
            ->exists();

        $commAlreadyDebited = CommissionWalletTransaction::where('user_id', $user->id)
            ->where('type', 'commission_debit')
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->exists();

        $platformFee = 0.00;
        if (!$feeAlreadyDebited && $booking) {
            $platformFee = round((float)$booking->platform_fee + (float)$booking->platform_fee_gst, 2);
        }

        $commission = 0.00;
        if (!$commAlreadyDebited) {
            $commission = round((float)$payment->commission_amount + (float)$payment->commission_gst_amount, 2);
        }

        $gatewayCharges = 0.00;
        if ($isOnline) {
            $gatewayCharges = round((float)$payment->gateway_charge_amount + (float)$payment->gateway_tax_amount, 2);
        }

        $bookingDisplayId = $booking ? ($booking->booking_id ?? $booking->id) : $payment->id;
        $traits = [];

        if ($platformFee > 0) {
            $traits[] = [
                'type' => 'platform_fee_debit',
                'amount' => -$platformFee,
                'description' => "Booking #{$bookingDisplayId} Platform Fee",
                'meta' => [
                    'booking_id' => $booking?->id,
                    'fee_amount' => (float)($booking?->platform_fee ?? 0),
                    'fee_gst' => (float)($booking?->platform_fee_gst ?? 0),
                    'is_one_time' => true,
                ],
            ];
        }

        if ($commission > 0) {
            $traits[] = [
                'type' => 'commission_debit',
                'amount' => -$commission,
                'description' => "Booking #{$bookingDisplayId} Platform Commission",
                'meta' => [
                    'booking_id' => $booking?->id,
                    'payment_id' => $payment->id,
                    'commission_amount' => (float)($payment->commission_amount ?? 0),
                    'commission_gst' => (float)($payment->commission_gst_amount ?? 0),
                ],
            ];
        }

        if ($gatewayCharges > 0) {
            $traits[] = [
                'type' => 'gateway_charge_debit',
                'amount' => -$gatewayCharges,
                'description' => "Booking #{$bookingDisplayId} Payment Gateway Charges",
                'meta' => [
                    'booking_id' => $booking?->id,
                    'payment_id' => $payment->id,
                    'gateway_charge' => (float)$payment->gateway_charge_amount,
                    'gateway_tax' => (float)$payment->gateway_tax_amount,
                ],
            ];
        }

        $payment->update(['deductions_settled_at' => now()]);

        if (empty($traits)) {
            return $user;
        }

        return $this->recordTraits($user, $traits, $payment);
    }

    /**
     * Phase 2: release the turf owner's payout credit (or, for offline, log the informational
     * collection note) once the booking date matures or the contribution is already <= 0. The
     * credited amount is net of any refund already applied to this payment (payment.amount minus
     * payment.refunded_amount) so a partial refund issued before maturity is never over-credited.
     */
    public function settleCredit(User $user, Payment $payment, bool $isOnline): User
    {
        if ($payment->wallet_cleared_at) {
            return $user;
        }

        $booking = $payment->booking;
        $bookingDisplayId = $booking ? ($booking->booking_id ?? $booking->id) : $payment->id;
        $traits = [];

        $grossAmount = 0.00;
        if ($isOnline) {
            $grossAmount = round((float)$payment->amount - (float)($payment->refunded_amount ?? 0), 2);
            if ($grossAmount > 0) {
                $isPart = ($booking && (float)$booking->balance_amount > 0);
                $creditLabel = $isPart
                    ? "Booking #{$bookingDisplayId} Online Part Payment Received"
                    : "Booking #{$bookingDisplayId} Online Payment Received";

                $traits[] = [
                    'type' => 'payment_credit',
                    'amount' => $grossAmount,
                    'description' => $creditLabel,
                    'meta' => [
                        'booking_id' => $booking?->id,
                        'payment_id' => $payment->id,
                        'payment_method' => $payment->payment_method,
                        'gross_amount' => $grossAmount,
                    ],
                ];
            }
        } else {
            $traits[] = [
                'type' => 'offline_booking_record',
                'amount' => 0.00,
                'description' => "Booking #{$bookingDisplayId} Pay at Venue (₹" . number_format((float)$payment->amount, 2) . " collected at venue)",
                'meta' => [
                    'booking_id' => $booking?->id,
                    'payment_id' => $payment->id,
                    'cash_amount' => (float)$payment->amount,
                ],
            ];
        }

        // Net payout = credited gross (refund-adjusted) minus whatever deductions were actually
        // posted for THIS payment specifically (sum straight from the ledger -- always accurate
        // regardless of when settleDeductions() ran, and naturally zero for any deduction type
        // that was deduped because an earlier payment on the same booking/date already covered it).
        $postedDeductions = (float) CommissionWalletTransaction::where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->whereIn('type', ['platform_fee_debit', 'commission_debit', 'gateway_charge_debit'])
            ->sum('amount');
        $netContribution = round($grossAmount + $postedDeductions, 2);

        $payment->update([
            'turf_payout_amount' => $netContribution,
            'wallet_cleared_at' => now(),
        ]);

        if (empty($traits)) {
            return $user;
        }

        return $this->recordTraits($user, $traits, $payment);
    }

    /**
     * Backwards-compatible proxy: settles deductions then settles credit.
     */
    public function settlePaymentWithTraits(User $user, Payment $payment, bool $isOnline): User
    {
        $user = $this->settleDeductions($user, $payment, $isOnline);
        return $this->settleCredit($user, $payment, $isOnline);
    }
}
