<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'razorpay_order_id',
        'razorpay_payment_id',
        'razorpay_signature',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
