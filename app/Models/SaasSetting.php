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
        'company_name',
        'company_email',
        'company_phone',
        'company_address',
        'pincode',
        'city',
        'state',
        'country',
        'gst_number',
        'udyam_registration_number',
        'subscription_gst_sac',
        'subscription_gst_percentage',
        'commission_gst_sac',
        'commission_gst_percentage',
        'booking_gst_sac',
        'booking_gst_percentage',
        'logo_path',
        'is_maintenance_mode',
        'gemini_api_key',
        'whatsapp_token',
        'whatsapp_phone_number_id',
        'whatsapp_business_account_id',
        'whatsapp_otp_template',
        'google_maps_api_key',
        'razorpay_key',
        'razorpay_secret',
        'mailgun_domain',
        'mailgun_secret',
        'mailgun_endpoint',
        'turf_search_km',
        'min_slots_booking',
        'commission_percentage',
        'platform_fee',
        'payment_gateway_percentage',
        'payout_hours',
        'payout_charges',
        'razorpayx_account_number',
        'razorpayx_webhook_secret',
        'max_commission_due',
        'commission_due_grace_days',
    ];

    protected $casts = [
        'is_maintenance_mode' => 'boolean',
        'turf_search_km' => 'integer',
        'min_slots_booking' => 'integer',
        'commission_percentage' => 'float',
        'platform_fee' => 'float',
        'payment_gateway_percentage' => 'float',
        'payout_hours' => 'integer',
        'payout_charges' => 'float',
        'max_commission_due' => 'float',
        'commission_due_grace_days' => 'integer',
        'subscription_gst_percentage' => 'float',
        'commission_gst_percentage' => 'float',
        'booking_gst_percentage' => 'float',
    ];

}
