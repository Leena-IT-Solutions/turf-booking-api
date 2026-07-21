<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'booking_date',
        'status',
        'amount',
        'additional_discount',
        'payment_status',
        'cancelled_at',
        'cancellation_fee_applied',
        'refund_amount',
        'refund_status',
        'refunded_at',
    ];

    protected $casts = [
        'booking_date' => 'string',
        'amount' => 'decimal:2',
        'additional_discount' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'cancellation_fee_applied' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'refunded_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookingSlots()
    {
        return $this->hasMany(BookingSlot::class);
    }

    public function couponUsage()
    {
        return $this->hasOne(CouponUsage::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
