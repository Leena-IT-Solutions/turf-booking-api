<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TurfSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'turf_id',
        'company_name',
        'company_email',
        'company_phone',
        'address',
        'city',
        'state',
        'state_code',
        'country',
        'pincode',
        'gst_number',
        'is_gst_billing_active',
        'gst_pricing_type',
        'gst_percentage',
    ];

    protected $casts = [
        'is_gst_billing_active' => 'boolean',
        'gst_percentage' => 'decimal:2',
    ];

    public function turf(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Turf::class);
    }
}
