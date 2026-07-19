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
        'amount',
        'additional_discount',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'amount' => 'decimal:2',
        'additional_discount' => 'decimal:2',
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
}
