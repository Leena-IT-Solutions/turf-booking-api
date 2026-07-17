<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'turf_id',
        'code',
        'description',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'minimum_slots_to_be_ordered',
        'usage_limit',
        'usage_limit_per_user',
        'used_count',
        'is_active',
        'mon',
        'tue',
        'wed',
        'thu',
        'fri',
        'sat',
        'sun',
        'starts_at',
        'expires_at',
    ];

    protected $casts = [
        'discount_value' => 'float',
        'max_discount_amount' => 'float',
        'minimum_slots_to_be_ordered' => 'integer',
        'usage_limit' => 'integer',
        'usage_limit_per_user' => 'integer',
        'used_count' => 'integer',
        'is_active' => 'boolean',
        'mon' => 'boolean',
        'tue' => 'boolean',
        'wed' => 'boolean',
        'thu' => 'boolean',
        'fri' => 'boolean',
        'sat' => 'boolean',
        'sun' => 'boolean',
        'starts_at' => 'date',
        'expires_at' => 'date',
    ];

    public function turf(): BelongsTo
    {
        return $this->belongsTo(Turf::class);
    }
}
