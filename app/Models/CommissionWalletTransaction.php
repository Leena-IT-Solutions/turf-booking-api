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

    /**
     * Get a human-readable label for a transaction type.
     */
    public static function typeLabel(string $type, float $amount = 0.0): string
    {
        return match ($type) {
            'payment_credit' => '+ Booking Credit',
            'platform_fee_debit' => '- Platform Fee',
            'commission_debit' => '- Commission',
            'gateway_charge_debit' => '- PG Charges',
            'offline_booking_record' => 'Pay at Venue',
            'refund_adjustment' => '- Refund Deduction',
            'payout_debit' => '- Bank Payout',
            'payout_reversal' => '+ Payout Reversal',
            'commission_due_settlement' => 'Debt Settlement',
            'payment_settlement' => $amount >= 0 ? '+ Payout Credit' : '- Fee & Commission',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }
}
