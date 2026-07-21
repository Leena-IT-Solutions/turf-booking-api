<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'turf_id',
        'date_of_booking',
        'booking_type',
        'status',
        'payment_status',
        'additional_discount',
        'cancelled_at',
        'cancellation_fee_applied',
        'refund_amount',
        'refund_status',
        'refunded_at',
    ];

    protected $casts = [
        'date_of_booking' => 'datetime',
        'additional_discount' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'cancellation_fee_applied' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'refunded_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function turf()
    {
        return $this->belongsTo(Turf::class);
    }

    public function bookingDates()
    {
        return $this->hasMany(BookingDate::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
