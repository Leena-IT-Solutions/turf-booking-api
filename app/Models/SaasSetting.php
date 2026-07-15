<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaasSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_name',
        'contact_email',
        'contact_mobile',
        'address',
        'logo_path',
        'is_maintenance_mode',
        'gemini_api_key',
        'razorpay_key',
        'razorpay_secret',
        'mailgun_domain',
        'mailgun_secret',
        'mailgun_endpoint',
    ];

    protected $casts = [
        'is_maintenance_mode' => 'boolean',
    ];
}
