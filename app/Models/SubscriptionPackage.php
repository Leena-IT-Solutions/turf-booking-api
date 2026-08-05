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
        'monthly_amount',
        'yearly_amount',
        'commission_percentage',
        'is_active',
        'sort_order',
        'features',
    ];

    protected $casts = [
        'monthly_amount' => 'decimal:2',
        'yearly_amount' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'features' => 'array',
    ];



}
