<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingCancellation extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'booking_date_id',
        'cancelled_by_user_id',
        'canceller_role',
        'cancellation_scope',
        'reason',
        'gross_cancelled_amount',
        'turf_cancellation_fee',
        'platform_cancellation_fee',
        'total_cancellation_fee',
        'refund_amount',
        'refund_status',
        'resolution_mode',
        'disbursement_channel',
        'offline_reference',
        'resolved_by_user_id',
        'resolved_at',
        'razorpay_refund_id',
        'commission_reversed_amount',
    ];

    protected $casts = [
        'gross_cancelled_amount' => 'decimal:2',
        'turf_cancellation_fee' => 'decimal:2',
        'platform_cancellation_fee' => 'decimal:2',
        'total_cancellation_fee' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'commission_reversed_amount' => 'decimal:2',
        'resolved_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookingDate()
    {
        return $this->belongsTo(BookingDate::class);
    }

    public function cancelledByUser()
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function resolvedByUser()
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
