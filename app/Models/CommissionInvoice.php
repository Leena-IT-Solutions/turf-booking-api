<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'turf_id',
        'owner_user_id',
        'period_start',
        'period_end',
        'is_gst_invoice',
        'is_inter_state',
        'taxable_amount',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'total_amount',
        'line_count',
        'line_items',
        'supplier_snapshot',
        'recipient_snapshot',
        'generated_at',
        'generated_by_user_id',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'generated_at' => 'datetime',
        'is_gst_invoice' => 'boolean',
        'is_inter_state' => 'boolean',
        'taxable_amount' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'line_count' => 'integer',
        'line_items' => 'array',
        'supplier_snapshot' => 'array',
        'recipient_snapshot' => 'array',
    ];

    public function turf()
    {
        return $this->belongsTo(Turf::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    /**
     * Get Indian Financial Year format (e.g. April 2025 to March 2026 -> '2526')
     */
    public static function getFinancialYearString(?Carbon $date = null): string
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
     * Generate sequential Commission Invoice number: CINV-{financial_year}-XXXXX
     */
    public static function generateInvoiceNumber(?Carbon $date = null): string
    {
        $fy = static::getFinancialYearString($date);
        $prefix = "CINV-{$fy}-";

        $last = static::where('invoice_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->first();

        if ($last && preg_match('/-(\d+)$/', $last->invoice_number, $matches)) {
            $seq = intval($matches[1]) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad((string)$seq, 5, '0', STR_PAD_LEFT);
    }
}
