<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_package_id',
        'billing_cycle',
        'amount',
        'turf_ids',
        'turf_count',
        'razorpay_order_id',
        'razorpay_payment_id',
        'razorpay_signature',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'turf_ids' => 'array',
        'turf_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function package()
    {
        return $this->belongsTo(SubscriptionPackage::class, 'subscription_package_id');
    }

    public function turfSubscriptions()
    {
        return $this->hasMany(TurfSubscription::class, 'subscription_payment_id');
    }
}
