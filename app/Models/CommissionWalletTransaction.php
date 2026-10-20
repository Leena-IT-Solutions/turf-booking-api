<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionWalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'description',
        'amount',
        'balance_after',
        'meta',
        'reference_type',
        'reference_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public const GROUPS = [
        'earnings' => [
            'label' => 'Booking earnings',
            'types' => ['payment_credit'],
        ],
        'deductions' => [
            'label' => 'Platform fees & commission',
            'types' => ['platform_fee_debit', 'commission_debit', 'gateway_charge_debit', 'cancellation_fee_debit'],
        ],
        'returns' => [
            'label' => 'Returned on cancellation',
            'types' => ['commission_reversal', 'gateway_charge_reversal'],
        ],
        'refunds' => [
            'label' => 'Refunds to players',
            'types' => ['refund_adjustment'],
        ],
        'payouts' => [
            'label' => 'Bank payouts',
            'types' => ['payout_debit', 'payout_reversal'],
        ],
        'other' => [
            'label' => 'Other',
            'types' => ['offline_booking_record', 'commission_due_settlement', 'payment_settlement'],
        ],
    ];

    public static function groupOf(string $type): string
    {
        foreach (self::GROUPS as $groupKey => $group) {
            if ($groupKey !== 'other' && in_array($type, $group['types'], true)) {
                return $groupKey;
            }
        }
        return 'other';
    }

    public static function groupColor(string $group): string
    {
        return match ($group) {
            'earnings', 'returns' => 'bg-emerald-50 text-emerald-700 border-emerald-200/60',
            'deductions', 'refunds' => 'bg-rose-50 text-rose-700 border-rose-200/60',
            'payouts' => 'bg-indigo-50 text-indigo-700 border-indigo-200/60',
            default => 'bg-gray-100 text-gray-700 border-gray-200/60',
        };
    }

    /**
     * Get a human-readable label for a transaction type.
     */
    public static function typeLabel(string $type, float $amount = 0.0): string
    {
        return match ($type) {
            'payment_credit' => '+ Booking Credit',
            'platform_fee_debit' => '- Platform Fee',
            'commission_debit' => '- Commission',
            'cancellation_fee_debit' => '- Cancellation Fee',
            'gateway_charge_debit' => '- PG Charges',
            'offline_booking_record' => 'Pay at Venue',
            'refund_adjustment' => '- Refund Deduction',
            'payout_debit' => '- Bank Payout',
            'payout_reversal' => '+ Payout Reversal',
            'commission_reversal' => '+ Commission Returned',
            'gateway_charge_reversal' => '+ Gateway Charges Returned',
            'commission_due_settlement' => 'Debt Settlement',
            'payment_settlement' => $amount >= 0 ? '+ Payout Credit' : '- Fee & Commission',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }
}

