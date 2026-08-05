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
        'commission_percentage',
        'is_active',
        'sort_order',
        'features',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'days' => 'integer',
        'sort_order' => 'integer',
        'features' => 'array',
    ];


}
