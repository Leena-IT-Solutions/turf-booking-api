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
    ];

    protected $casts = [
        'date_of_booking' => 'datetime',
        'additional_discount' => 'decimal:2',
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
}
