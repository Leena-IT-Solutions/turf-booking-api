<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TurfSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_package_id',
        'billing_cycle',
        'price',
        'commission_percentage',
        'starts_at',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function package()
    {
        return $this->belongsTo(SubscriptionPackage::class, 'subscription_package_id');
    }
}
