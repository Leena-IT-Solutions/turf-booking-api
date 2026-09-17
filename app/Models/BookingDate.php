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
        'actual_amount',
        'amount',
        'coupon_discount',
        'additional_discount',
        'taxable_amount',
        'turf_gst_amount',
        'turf_cgst_amount',
        'turf_sgst_amount',
        'paid_amount',
        'balance_amount',
        'commission_rate',
        'commission_amount',
        'commission_gst_amount',
        'commission_cgst_amount',
        'commission_sgst_amount',
        'commission_igst_amount',
        'turf_payout_amount',
        'cash_held_amount',
        'cancellation_turf_fee',
        'cancellation_platform_fee',
        'estimated_refund_amount',
        'payment_status',
        'cancelled_at',
        'cancellation_fee_applied',
        'refund_amount',
        'refund_status',
        'refunded_at',
    ];

    protected $casts = [
        'booking_date' => 'string',
        'actual_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'coupon_discount' => 'decimal:2',
        'additional_discount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'turf_gst_amount' => 'decimal:2',
        'turf_cgst_amount' => 'decimal:2',
        'turf_sgst_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'commission_gst_amount' => 'decimal:2',
        'commission_cgst_amount' => 'decimal:2',
        'commission_sgst_amount' => 'decimal:2',
        'commission_igst_amount' => 'decimal:2',
        'turf_payout_amount' => 'decimal:2',
        'cash_held_amount' => 'decimal:2',
        'cancellation_turf_fee' => 'decimal:2',
        'cancellation_platform_fee' => 'decimal:2',
        'estimated_refund_amount' => 'decimal:2',
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

    public function bookingCancellations()
    {
        return $this->hasMany(BookingCancellation::class);
    }
}
