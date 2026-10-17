<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingReschedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'booking_date_id',
        'from_date',
        'to_date',
        'from_slot_ids',
        'to_slot_ids',
        'old_amount',
        'new_amount',
        'price_difference',
        'settlement_type',
        'refund_amount',
        'refund_channel',
        'razorpay_refund_id',
        'reason',
        'rescheduled_by_user_id',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'from_slot_ids' => 'array',
        'to_slot_ids' => 'array',
        'old_amount' => 'decimal:2',
        'new_amount' => 'decimal:2',
        'price_difference' => 'decimal:2',
        'refund_amount' => 'decimal:2',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookingDate()
    {
        return $this->belongsTo(BookingDate::class);
    }

    public function rescheduledBy()
    {
        return $this->belongsTo(User::class, 'rescheduled_by_user_id');
    }
}
