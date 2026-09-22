<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_number',
        'turf_invoice_number',
        'saas_invoice_number',
        'user_id',
        'turf_id',
        'date_of_booking',
        'booking_type',
        'status',
        'payment_status',
        'actual_amount',
        'coupon_discount',
        'additional_discount',
        'taxable_amount',
        'turf_gst_amount',
        'turf_cgst_amount',
        'turf_sgst_amount',
        'turf_gst_rate',
        'turf_gst_type',
        'platform_fee',
        'platform_fee_gst',
        'platform_fee_cgst',
        'platform_fee_sgst',
        'platform_fee_igst',
        'total_amount',
        'payable_now',
        'balance_amount',
        'is_part_payment',
        'customer_gstin',
        'customer_company_name',
        'gateway_charge_amount',
        'gateway_tax_amount',
        'commission_rate',
        'commission_amount',
        'commission_gst_amount',
        'commission_cgst_amount',
        'commission_sgst_amount',
        'commission_igst_amount',
        'turf_payout_amount',
        'is_cancellation_active',
        'cancellation_hours',
        'cancellation_turf_fee',
        'cancellation_platform_fee_pct',
        'estimated_refund_amount',
        'cancelled_at',
        'cancellation_fee_applied',
        'refund_amount',
        'refund_status',
        'refunded_at',
    ];

    protected $casts = [
        'date_of_booking' => 'datetime',
        'actual_amount' => 'decimal:2',
        'coupon_discount' => 'decimal:2',
        'additional_discount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'turf_gst_amount' => 'decimal:2',
        'turf_cgst_amount' => 'decimal:2',
        'turf_sgst_amount' => 'decimal:2',
        'turf_gst_rate' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'platform_fee_gst' => 'decimal:2',
        'platform_fee_cgst' => 'decimal:2',
        'platform_fee_sgst' => 'decimal:2',
        'platform_fee_igst' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'payable_now' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'is_part_payment' => 'boolean',
        'gateway_charge_amount' => 'decimal:2',
        'gateway_tax_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'commission_gst_amount' => 'decimal:2',
        'commission_cgst_amount' => 'decimal:2',
        'commission_sgst_amount' => 'decimal:2',
        'commission_igst_amount' => 'decimal:2',
        'turf_payout_amount' => 'decimal:2',
        'is_cancellation_active' => 'boolean',
        'cancellation_hours' => 'integer',
        'cancellation_turf_fee' => 'decimal:2',
        'cancellation_platform_fee_pct' => 'decimal:2',
        'estimated_refund_amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'cancellation_fee_applied' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'refunded_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($booking) {
            if (empty($booking->booking_number)) {
                $booking->booking_number = static::generateBookingNumber();
            }
        });
    }

    /**
     * Generate sequential booking number TB-YYYYMM-XXXXX
     */
    public static function generateBookingNumber(): string
    {
        $prefix = 'TB-' . date('Ym') . '-';
        $last = static::where('booking_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->first();

        if ($last && preg_match('/-(\d+)$/', $last->booking_number, $matches)) {
            $seq = intval($matches[1]) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad((string)$seq, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get Indian Financial Year format (e.g. April 2025 to March 2026 -> '2526')
     */
    public static function getFinancialYearString(?\Carbon\Carbon $date = null): string
    {
        $d = $date ?: now();
        $year = (int)$d->format('Y');
        $month = (int)$d->format('n');

        if ($month >= 4) {
            $fyStart = $year;
            $fyEnd = $year + 1;
        } else {
            $fyStart = $year - 1;
            $fyEnd = $year;
        }

        return substr((string)$fyStart, -2) . substr((string)$fyEnd, -2);
    }

    /**
     * Generate sequential turf invoice number: INV-{turfId}-{financial_year}-XXXXX
     */
    public static function generateTurfInvoiceNumber(int $turfId): string
    {
        $fy = static::getFinancialYearString();
        $prefix = "INV-{$turfId}-{$fy}-";
        $last = static::where('turf_invoice_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->first();

        if ($last && preg_match('/-(\d+)$/', $last->turf_invoice_number, $matches)) {
            $seq = intval($matches[1]) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad((string)$seq, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Generate sequential SaaS invoice number: PINV-{financial_year}-XXXXX
     */
    public static function generateSaasInvoiceNumber(): string
    {
        $fy = static::getFinancialYearString();
        $prefix = "PINV-{$fy}-";
        $last = static::where('saas_invoice_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->first();

        if ($last && preg_match('/-(\d+)$/', $last->saas_invoice_number, $matches)) {
            $seq = intval($matches[1]) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad((string)$seq, 5, '0', STR_PAD_LEFT);
    }

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

    public function bookingCancellations()
    {
        return $this->hasMany(BookingCancellation::class);
    }

    public function getBookingReferenceAttribute(): string
    {
        return $this->booking_number ?? ('#' . $this->id);
    }
}
