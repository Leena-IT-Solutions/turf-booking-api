<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'gateway_name',
        'gateway_order_id',
        'gateway_payment_id',
        'gateway_signature',
        'response_payload',
    ];

    protected $casts = [
        'response_payload' => 'array',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
