<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TurfPayout extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REJECTED = 'rejected';

    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
    ];

    protected $fillable = [
        'user_id',
        'requested_amount',
        'charge_applied',
        'net_amount',
        'status',
        'mode',
        'payout_method',
        'account_details',
        'triggered_by',
        'razorpay_contact_id',
        'razorpay_fund_account_id',
        'razorpay_payout_id',
        'utr',
        'paid_at',
        'failure_reason',
        'admin_note',
        'processed_by_user_id',
        'processed_at',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'charge_applied' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'account_details' => 'array',
        'paid_at' => 'date',
        'processed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }
}
