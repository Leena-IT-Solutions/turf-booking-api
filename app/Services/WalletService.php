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
    public function settlePaymentWithTraits(User $user, Payment $payment, bool $isOnline): User
    {
        $booking = $payment->booking;
        $bookingPaymentIds = $booking ? Payment::where('booking_id', $booking->id)->pluck('id')->toArray() : [$payment->id];

        // 1. Check if Platform Fee was already debited for this booking
        $feeAlreadyDebited = CommissionWalletTransaction::where('user_id', $user->id)
            ->where('type', 'platform_fee_debit')
            ->where('reference_type', Payment::class)
            ->whereIn('reference_id', $bookingPaymentIds)
            ->exists();

        // 2. Check if Commission was already debited for this specific payment
        $commAlreadyDebited = CommissionWalletTransaction::where('user_id', $user->id)
            ->where('type', 'commission_debit')
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->exists();

        // 3. Compute one-time fees
        $platformFee = 0.00;
        if (!$feeAlreadyDebited && $booking) {
            $platformFee = round((float)$booking->platform_fee + (float)$booking->platform_fee_gst, 2);
        }

        $commission = 0.00;
        if (!$commAlreadyDebited) {
            $commission = round((float)$payment->commission_amount + (float)$payment->commission_gst_amount, 2);
        }

        // 4. Compute PG charges
        $gatewayCharges = 0.00;
        if ($isOnline) {
            $gatewayCharges = round((float)$payment->gateway_charge_amount + (float)$payment->gateway_tax_amount, 2);
        }

        // 5. Compute gross
        $grossAmount = $isOnline ? round((float)$payment->amount, 2) : 0.00;

        $bookingDisplayId = $booking ? ($booking->booking_id ?? $booking->id) : $payment->id;
        $traits = [];

        if ($isOnline) {
            $isPart = ($booking && (float)$booking->balance_amount > 0);
            $creditLabel = $isPart
                ? "Booking #{$bookingDisplayId} Online Part Payment Received"
                : "Booking #{$bookingDisplayId} Online Payment Received";

            // 1. Payment Credit
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

            // 2. Platform Fee (one-time)
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

            // 3. Commission (for this payment)
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

            // 4. PG charges
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
        } else {
            // For Offline / Pay at Venue:
            // 1. Offline record (informational, 0 amount) - anchors booking at venue first
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

            // 2. Commission (for this payment)
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

            // 3. Platform fee (one-time)
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
        }

        // Net contribution for this payment record
        $netContribution = round($grossAmount - $platformFee - $commission - $gatewayCharges, 2);
        $payment->update([
            'turf_payout_amount' => $netContribution,
            'wallet_cleared_at' => now(),
        ]);

        return $this->recordTraits($user, $traits, $payment);
    }
}
