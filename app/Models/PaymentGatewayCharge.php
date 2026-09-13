<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGatewayCharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'charge_percentage',
        'tax_percentage',
        'total_percentage',
        'flat_fee',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'charge_percentage' => 'float',
        'tax_percentage' => 'float',
        'total_percentage' => 'float',
        'flat_fee' => 'float',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Calculate effective total percentage: charge_percentage * (1 + tax_percentage / 100)
     */
    public static function calculateTotalPercentage(float $chargePercentage, float $taxPercentage): float
    {
        return round($chargePercentage * (1 + ($taxPercentage / 100)), 4);
    }
}
