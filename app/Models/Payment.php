<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'booking_date_id',
        'payment_method',
        'amount',
        'commission_percentage',
        'commission_amount',
        'cash_held_amount',
        'turf_payout_amount',
        'wallet_cleared_at',
        'status',
        'paid_at',
        'refunded_amount',
        'refund_status',
        'refunded_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'cash_held_amount' => 'decimal:2',
        'turf_payout_amount' => 'decimal:2',
        'wallet_cleared_at' => 'datetime',
        'paid_at' => 'datetime',
        'refunded_amount' => 'decimal:2',
        'refunded_at' => 'datetime',
    ];


    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookingDate()
    {
        return $this->belongsTo(BookingDate::class);
    }

    public function paymentGateway()
    {
        return $this->hasOne(PaymentGateway::class);
    }
}
