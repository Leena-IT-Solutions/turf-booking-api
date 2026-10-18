<?php

namespace App\Support;

use App\Models\User;

/**
 * Where a turf owner's payouts go: a bank account or a UPI ID. One set of rules, used when the owner
 * saves the details and again when a payout is requested, so money is never sent to (or recorded
 * against) missing or malformed details.
 */
class PayoutDestination
{
    public const IFSC_REGEX = '/^[A-Z]{4}0[A-Z0-9]{6}$/';
    public const ACCOUNT_NUMBER_REGEX = '/^\d{9,18}$/';
    public const HOLDER_NAME_REGEX = "/^[A-Za-z][A-Za-z .'-]{1,149}$/";
    public const UPI_REGEX = '/^[A-Za-z0-9._-]{2,256}@[A-Za-z][A-Za-z0-9]{1,63}$/';

    /** Trim and case-normalise the details as they will be stored. */
    public static function normalize(?string $method, ?string $holder, ?string $account, ?string $ifsc, ?string $upi): array
    {
        return [
            'payout_method' => strtolower(trim((string) $method)),
            'bank_account_name' => trim(preg_replace('/\s+/', ' ', (string) $holder)),
            'bank_account_number' => preg_replace('/\s+/', '', (string) $account),
            'bank_ifsc' => strtoupper(trim((string) $ifsc)),
            'upi_id' => strtolower(trim((string) $upi)),
        ];
    }

    /**
     * First problem with the given (normalised) details, keyed by field, or null when they are usable.
     *
     * @return array{field: string, message: string}|null
     */
    public static function problem(array $d): ?array
    {
        $method = $d['payout_method'] ?? '';

        if ($method === 'bank') {
            if (!preg_match(self::HOLDER_NAME_REGEX, $d['bank_account_name'] ?? '')) {
                return ['field' => 'bank_account_name', 'message' => 'Enter the account holder name exactly as it appears at the bank.'];
            }
            if (!preg_match(self::ACCOUNT_NUMBER_REGEX, $d['bank_account_number'] ?? '')) {
                return ['field' => 'bank_account_number', 'message' => 'Enter a valid bank account number (9 to 18 digits).'];
            }
            if (!preg_match(self::IFSC_REGEX, $d['bank_ifsc'] ?? '')) {
                return ['field' => 'bank_ifsc', 'message' => 'Enter a valid 11-character IFSC code (e.g. SBIN0001234).'];
            }

            return null;
        }

        if ($method === 'upi') {
            if (!preg_match(self::UPI_REGEX, $d['upi_id'] ?? '')) {
                return ['field' => 'upi_id', 'message' => 'Enter a valid UPI ID (e.g. name@bank).'];
            }

            return null;
        }

        return ['field' => 'payout_method', 'message' => 'Choose Bank Account or UPI for your payouts.'];
    }

    /** Message explaining why this user can't receive a payout yet, or null when their saved details are usable. */
    public static function errorFor(User $user): ?string
    {
        $problem = self::problem(self::normalize(
            $user->payout_method, $user->bank_account_name, $user->bank_account_number, $user->bank_ifsc, $user->upi_id
        ));

        return $problem
            ? 'Payment details missing or invalid: ' . $problem['message'] . ' Please update your payout details before requesting a payout.'
            : null;
    }
}
