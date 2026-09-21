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
        'turf_fee_gst_amount',
        'turf_fee_cgst_amount',
        'turf_fee_sgst_amount',
        'platform_fee_retained',
        'saas_cancellation_fee',
        'saas_fee_gst_amount',
        'saas_fee_cgst_amount',
        'saas_fee_sgst_amount',
        'saas_fee_igst_amount',
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
        'turf_fee_gst_amount' => 'decimal:2',
        'turf_fee_cgst_amount' => 'decimal:2',
        'turf_fee_sgst_amount' => 'decimal:2',
        'platform_fee_retained' => 'decimal:2',
        'saas_cancellation_fee' => 'decimal:2',
        'saas_fee_gst_amount' => 'decimal:2',
        'saas_fee_cgst_amount' => 'decimal:2',
        'saas_fee_sgst_amount' => 'decimal:2',
        'saas_fee_igst_amount' => 'decimal:2',
        'platform_cancellation_fee' => 'decimal:2',
        'total_cancellation_fee' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'commission_reversed_amount' => 'decimal:2',
        'resolved_at' => 'datetime',
    ];

    public function getDeductionsBreakupAttribute(): array
    {
        $turfFee = (float) $this->turf_cancellation_fee;
        $platformFeeRetained = (float) ($this->platform_fee_retained ?? 0);
        $saasFee = (float) ($this->saas_cancellation_fee ?? 0);

        // Fallback for older records where platform_fee_retained wasn't separated
        if ($platformFeeRetained <= 0 && $saasFee <= 0 && $this->platform_cancellation_fee > 0) {
            $bookingPlatFee = (float) ($this->booking?->platform_fee ?? 0) + (float) ($this->booking?->platform_fee_gst ?? 0);
            $platformFeeRetained = min((float) $this->platform_cancellation_fee, $bookingPlatFee);
            $saasFee = max(0.00, round((float) $this->platform_cancellation_fee - $platformFeeRetained, 2));
        }

        return [
            'turf_cancellation_fee' => $turfFee,
            'turf_fee_base' => round(max(0.00, $turfFee - (float)($this->turf_fee_gst_amount ?? 0)), 2),
            'turf_fee_gst' => (float)($this->turf_fee_gst_amount ?? 0),
            'turf_fee_cgst' => (float)($this->turf_fee_cgst_amount ?? 0),
            'turf_fee_sgst' => (float)($this->turf_fee_sgst_amount ?? 0),
            'platform_fee_retained' => $platformFeeRetained,
            'saas_cancellation_fee' => $saasFee,
            'saas_fee_base' => round(max(0.00, $saasFee - (float)($this->saas_fee_gst_amount ?? 0)), 2),
            'saas_fee_gst' => (float)($this->saas_fee_gst_amount ?? 0),
            'saas_fee_cgst' => (float)($this->saas_fee_cgst_amount ?? 0),
            'saas_fee_sgst' => (float)($this->saas_fee_sgst_amount ?? 0),
            'saas_fee_igst' => (float)($this->saas_fee_igst_amount ?? 0),
            'total' => (float) $this->total_cancellation_fee,
            'gross' => (float) $this->gross_cancelled_amount,
            'refund' => (float) $this->refund_amount,
        ];
    }

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
