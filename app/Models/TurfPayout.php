<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TurfPayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'requested_amount',
        'charge_applied',
        'net_amount',
        'status',
        'triggered_by',
        'razorpay_contact_id',
        'razorpay_fund_account_id',
        'razorpay_payout_id',
        'failure_reason',
        'processed_at',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'charge_applied' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
