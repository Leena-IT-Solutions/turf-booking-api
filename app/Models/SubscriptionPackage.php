<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'amount',
        'days',
        'total_percentage',
        'payment_gateway_percentage',
        'commission_percentage',
        'is_active',
        'from_date',
        'to_date',
        'sort_order',
        'features',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'total_percentage' => 'decimal:2',
        'payment_gateway_percentage' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'from_date' => 'date:Y-m-d',
        'to_date' => 'date:Y-m-d',
        'days' => 'integer',
        'sort_order' => 'integer',
        'features' => 'array',
    ];
}
